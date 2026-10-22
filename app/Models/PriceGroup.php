<?php

namespace App\Models;

use Database\Factories\PriceGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceGroup extends Model
{
    /** @use HasFactory<PriceGroupFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['minimum_order_quantity' => 'integer', 'minimum_order_amount' => 'integer', 'discount_percent' => 'integer', 'active' => 'boolean'];
    }

    public function prices()
    {
        return $this->hasMany(VariantPrice::class);
    }

    public function accounts()
    {
        return $this->hasMany(BusinessAccount::class);
    }

    public function orderRules()
    {
        return $this->hasMany(BusinessOrderRule::class);
    }
}
