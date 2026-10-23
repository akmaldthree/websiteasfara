<?php

namespace App\Services;

use App\Models\BusinessAccount;
use App\Models\CommissionEntry;
use App\Models\Order;
use App\Models\Refund;

class CommissionService
{
    public function earn(Order $order): void
    {
        $snapshot = $order->business_snapshot ?? [];
        $salesUserId = (int) ($snapshot['sales_user_id'] ?? 0);
        $accountId = (int) ($snapshot['business_account_id'] ?? 0);
        $percent = (float) ($snapshot['commission_percent'] ?? 0);
        if (! $salesUserId || ! $accountId || $percent <= 0 || ! BusinessAccount::whereKey($accountId)->exists()) {
            return;
        }
        $base = max(0, $order->subtotal + $order->custom_cost - $order->discount - $order->loyalty_discount);
        $amount = (int) floor($base * $percent / 100);
        if ($amount > 0) {
            CommissionEntry::firstOrCreate(['reference' => 'order:'.$order->id.':commission'], [
                'sales_user_id' => $salesUserId, 'business_account_id' => $accountId, 'order_id' => $order->id,
                'type' => 'earned', 'amount' => $amount, 'base_amount' => $base, 'commission_percent' => $percent,
            ]);
        }
    }

    public function adjustRefund(Order $order, Refund $refund, int $cumulativeRefund): void
    {
        $earned = CommissionEntry::where('reference', 'order:'.$order->id.':commission')->first();
        if (! $earned || $order->total <= 0) {
            return;
        }
        $reversed = abs((int) CommissionEntry::where('order_id', $order->id)->where('type', 'refund_adjustment')->sum('amount'));
        $target = min($earned->amount, (int) floor($earned->amount * $cumulativeRefund / $order->total));
        if ($target > $reversed) {
            CommissionEntry::create([
                'sales_user_id' => $earned->sales_user_id, 'business_account_id' => $earned->business_account_id,
                'order_id' => $order->id, 'refund_id' => $refund->id, 'type' => 'refund_adjustment',
                'amount' => -($target - $reversed), 'base_amount' => $refund->amount,
                'commission_percent' => $earned->commission_percent, 'reference' => 'refund:'.$refund->id.':commission',
            ]);
        }
    }
}
