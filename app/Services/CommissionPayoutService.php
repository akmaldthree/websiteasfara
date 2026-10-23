<?php

namespace App\Services;

use App\Models\CommissionEntry;
use App\Models\CommissionPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommissionPayoutService
{
    /** @param array{recipient_name: string, bank_name: string, account_number: string, payment_reference: string, paid_at: string, proof_path?: string|null, notes?: string|null} $data */
    public function create(User $salesUser, User $actor, array $data): CommissionPayout
    {
        return DB::transaction(function () use ($salesUser, $actor, $data) {
            $entries = CommissionEntry::where('sales_user_id', $salesUser->id)
                ->whereNull('commission_payout_id')
                ->where('created_at', '<=', $data['paid_at'])
                ->lockForUpdate()
                ->get();
            $amount = (int) $entries->sum('amount');
            if ($entries->isEmpty() || $amount <= 0) {
                throw ValidationException::withMessages(['sales_user_id' => 'Tidak ada saldo komisi positif yang dapat dibayarkan sampai tanggal tersebut.']);
            }
            $payout = CommissionPayout::create($data + [
                'number' => 'CP-'.now()->format('Ymd').'-'.strtoupper(Str::random(8)),
                'sales_user_id' => $salesUser->id,
                'created_by' => $actor->id,
                'amount' => $amount,
            ]);
            CommissionEntry::whereIn('id', $entries->pluck('id'))->update(['commission_payout_id' => $payout->id]);
            Audit::record('commission.paid', 'commission_payout:'.$payout->id, null, $payout->only(['number', 'sales_user_id', 'amount', 'payment_reference', 'paid_at']));

            return $payout;
        }, 3);
    }
}
