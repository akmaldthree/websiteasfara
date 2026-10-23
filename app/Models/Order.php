<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['address' => 'array', 'shipping' => 'array', 'bank_accounts' => 'array', 'business_snapshot' => 'array', 'dropship_snapshot' => 'array', 'coupon_snapshot' => 'array', 'custom_cost' => 'integer', 'loyalty_points_redeemed' => 'integer', 'loyalty_discount' => 'integer', 'expires_at' => 'datetime', 'credit_due_at' => 'datetime', 'paid_at' => 'datetime', 'shipped_at' => 'datetime', 'completed_at' => 'datetime', 'received_at' => 'datetime', 'claimed_at' => 'datetime', 'shipping_overridden_at' => 'datetime'];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function proofs()
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function cases()
    {
        return $this->hasMany(ServiceCase::class);
    }

    public function customerUrl(): string
    {
        return URL::signedRoute('orders.show', ['order' => $this->id]);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
