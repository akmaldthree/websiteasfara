<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessOrderRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['minimum_quantity' => 'integer', 'minimum_amount' => 'integer', 'active' => 'boolean'];
    }

    public function priceGroup()
    {
        return $this->belongsTo(PriceGroup::class);
    }
}
