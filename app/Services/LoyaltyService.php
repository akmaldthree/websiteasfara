<?php

namespace App\Services;

use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class LoyaltyService
{
    public function tier(User $user): array
    {
        $earned = (int) LoyaltyTransaction::where('user_id', $user->id)->where('type', 'earn')->where('points', '>', 0)->sum('points');
        $tiers = collect(Setting::get('loyalty_tiers', [['name' => 'Member', 'minimum' => 0]]))->sortBy('minimum')->values();
        $current = $tiers->last(fn ($tier) => $earned >= (int) $tier['minimum']) ?? ['name' => 'Member', 'minimum' => 0];
        $next = $tiers->first(fn ($tier) => (int) $tier['minimum'] > $earned);

        return [
            'name' => $current['name'],
            'minimum' => (int) $current['minimum'],
            'benefit' => $current['benefit'] ?? null,
            'value_bonus_percent' => (int) ($current['value_bonus_percent'] ?? 0),
            'lifetime_points' => $earned,
            'next' => $next,
        ];
    }

    public function balance(User $user): int
    {
        $points = LoyaltyTransaction::where('user_id', $user->id)
            ->where(fn ($query) => $query->where('points', '<', 0)->orWhereNull('expires_at')->orWhere('expires_at', '>', now()))->sum('points');

        return max(0, (int) $points);
    }

    public function redemption(User $user, int $requested, int $eligibleAmount): array
    {
        if (! Setting::get('loyalty_enabled', false) || $requested === 0) {
            return ['points' => 0, 'discount' => 0];
        }
        $minimum = (int) Setting::get('loyalty_min_redeem', 100);
        if ($requested < $minimum || $requested > $this->balance($user)) {
            throw ValidationException::withMessages(['loyalty_points' => 'Poin tidak mencukupi atau belum mencapai minimum penukaran.']);
        }
        $tierBonus = $this->tier($user)['value_bonus_percent'];
        $value = max(1, (int) floor((int) Setting::get('loyalty_point_value', 100) * (100 + $tierBonus) / 100));
        $discount = min($eligibleAmount, $requested * $value);
        $points = (int) ceil($discount / $value);

        return ['points' => $points, 'discount' => $discount];
    }

    public function redeem(Order $order): void
    {
        if (! $order->user_id || ! $order->loyalty_points_redeemed) {
            return;
        }
        LoyaltyTransaction::firstOrCreate(['reference' => 'order:'.$order->id.':redeem'], ['user_id' => $order->user_id, 'order_id' => $order->id, 'type' => 'redeem', 'points' => -$order->loyalty_points_redeemed]);
    }

    public function earn(Order $order): void
    {
        if (! $order->user_id || ! Setting::get('loyalty_enabled', false)) {
            return;
        }
        $spendPerPoint = max(1, (int) Setting::get('loyalty_spend_per_point', 10000));
        $eligible = max(0, $order->subtotal + $order->custom_cost - $order->discount - $order->loyalty_discount);
        $basePoints = intdiv($eligible, $spendPerPoint);
        $bonus = $this->bonus($order, $order->user, $eligible, $spendPerPoint);
        $points = $basePoints + $bonus['points'];
        if ($points > 0) {
            LoyaltyTransaction::firstOrCreate(['reference' => 'order:'.$order->id.':earn'], ['user_id' => $order->user_id, 'order_id' => $order->id, 'type' => 'earn', 'points' => $points, 'metadata' => ['base_points' => $basePoints, 'bonus_points' => $bonus['points'], 'multiplier' => $bonus['multiplier'], 'campaign' => $bonus['points'] > 0 ? Setting::get('loyalty_bonus_name', 'Bonus poin') : null, 'target' => $bonus['target']], 'expires_at' => now()->addDays((int) Setting::get('loyalty_expiry_days', 365))]);
        }
    }

    private function bonus(Order $order, User $user, int $eligible, int $spendPerPoint): array
    {
        $multiplier = max(1, (int) Setting::get('loyalty_bonus_multiplier', 1));
        $start = Setting::get('loyalty_bonus_start');
        $end = Setting::get('loyalty_bonus_end');
        if ($multiplier === 1 || ! $start || ! $end || ! now()->between(Carbon::parse($start)->startOfDay(), Carbon::parse($end)->endOfDay())) {
            return ['points' => 0, 'multiplier' => 1, 'target' => 'all'];
        }

        $segment = Setting::get('loyalty_bonus_segment', 'all');
        if (($segment === 'business' && ! $order->business_snapshot) || ($segment === 'customer' && $order->business_snapshot)) {
            return ['points' => 0, 'multiplier' => 1, 'target' => 'all'];
        }
        $requiredTier = trim((string) Setting::get('loyalty_bonus_tier', ''));
        if ($requiredTier !== '' && mb_strtolower($this->tier($user)['name']) !== mb_strtolower($requiredTier)) {
            return ['points' => 0, 'multiplier' => 1, 'target' => 'all'];
        }
        $targetType = Setting::get('loyalty_bonus_target_type', 'all');
        $targetId = (int) Setting::get('loyalty_bonus_target_id', 0);
        $targetEligible = $eligible;
        if ($targetType !== 'all') {
            $targetEligible = min($eligible, (int) $order->items()->with('variant.series')->get()->filter(fn ($item) => match ($targetType) {
                'category' => $item->variant?->series?->category_id === $targetId,
                'series' => $item->variant?->series_id === $targetId,
                'variant' => $item->variant_id === $targetId,
                default => false,
            })->sum(fn ($item) => $item->total + $item->custom_cost));
        }

        return ['points' => intdiv($targetEligible, $spendPerPoint) * ($multiplier - 1), 'multiplier' => $multiplier, 'target' => $targetType.($targetId ? ':'.$targetId : '')];
    }

    public function restoreCancelled(Order $order): void
    {
        if (! $order->user_id || ! $order->loyalty_points_redeemed) {
            return;
        }
        LoyaltyTransaction::firstOrCreate(['reference' => 'order:'.$order->id.':cancel-restore'], ['user_id' => $order->user_id, 'order_id' => $order->id, 'type' => 'redemption_restore', 'points' => $order->loyalty_points_redeemed]);
    }

    public function adjustRefund(Order $order, Refund $refund, int $cumulativeRefund): void
    {
        if (! $order->user_id || $order->total <= 0) {
            return;
        }
        $earned = max(0, (int) LoyaltyTransaction::where('reference', 'order:'.$order->id.':earn')->value('points'));
        $reversed = abs((int) LoyaltyTransaction::where('order_id', $order->id)->where('type', 'refund_reversal')->sum('points'));
        $targetReversal = min($earned, (int) floor($earned * $cumulativeRefund / $order->total));
        if ($targetReversal > $reversed) {
            LoyaltyTransaction::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'refund_id' => $refund->id, 'type' => 'refund_reversal', 'points' => -($targetReversal - $reversed), 'reference' => 'refund:'.$refund->id.':reversal']);
        }
        $restored = (int) LoyaltyTransaction::where('order_id', $order->id)->where('type', 'redemption_restore')->sum('points');
        $targetRestore = min($order->loyalty_points_redeemed, (int) floor($order->loyalty_points_redeemed * $cumulativeRefund / $order->total));
        if ($targetRestore > $restored) {
            LoyaltyTransaction::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'refund_id' => $refund->id, 'type' => 'redemption_restore', 'points' => $targetRestore - $restored, 'reference' => 'refund:'.$refund->id.':restore']);
        }
    }
}
