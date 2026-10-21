<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['address' => 'array', 'shipping' => 'array', 'bank_accounts' => 'array', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'shipped_at' => 'datetime', 'completed_at' => 'datetime'];
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
}
