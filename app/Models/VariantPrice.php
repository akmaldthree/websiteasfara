<?php

namespace App\Models;

use Database\Factories\VariantPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariantPrice extends Model
{
    /** @use HasFactory<VariantPriceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['minimum_quantity' => 'integer', 'price' => 'integer', 'active' => 'boolean'];
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function priceGroup()
    {
        return $this->belongsTo(PriceGroup::class);
    }
}
