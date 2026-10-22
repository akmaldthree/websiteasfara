<?php

namespace App\Services;

use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LoyaltyService
{
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
        $value = max(1, (int) Setting::get('loyalty_point_value', 100));
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
        $points = intdiv($eligible, $spendPerPoint);
        if ($points > 0) {
            LoyaltyTransaction::firstOrCreate(['reference' => 'order:'.$order->id.':earn'], ['user_id' => $order->user_id, 'order_id' => $order->id, 'type' => 'earn', 'points' => $points, 'expires_at' => now()->addDays((int) Setting::get('loyalty_expiry_days', 365))]);
        }
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
