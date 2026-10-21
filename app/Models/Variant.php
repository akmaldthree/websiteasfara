<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Variant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'price' => 'integer', 'stock' => 'integer', 'reserved' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function ($v) {
            $v->sku = strtoupper(trim($v->sku));
            $v->product_type = trim($v->product_type ?? '');
            $v->color = trim($v->color);
            $v->size = trim($v->size);
            if (! $v->sku) {
                throw ValidationException::withMessages(['sku' => 'SKU wajib diisi manual.']);
            }
            $series = Series::findOrFail($v->series_id);
            if ($series->uses_product_type !== ($v->product_type !== '')) {
                throw ValidationException::withMessages(['product_type' => 'Jenis produk harus sesuai struktur series.']);
            }
            $v->combination_key = hash('sha256', mb_strtolower(implode('|', [$v->series_id, $v->product_type, $v->color, $v->size])));
        });
    }

    public function series()
    {
        return $this->belongsTo(Series::class);
    }

    public function getAvailableAttribute(): int
    {
        return max(0, $this->stock - $this->reserved);
    }

    public function getLabelAttribute(): string
    {
        return implode(' · ', array_filter([$this->product_type, $this->color, $this->size]));
    }
}
