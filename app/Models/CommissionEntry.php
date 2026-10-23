<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'base_amount' => 'integer', 'commission_percent' => 'decimal:2'];
    }

    public function salesUser()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payout()
    {
        return $this->belongsTo(CommissionPayout::class, 'commission_payout_id');
    }
}
