<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMedia extends Model
{
    protected $table = 'product_media';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['focal_x' => 'integer', 'focal_y' => 'integer', 'sort_order' => 'integer', 'active' => 'boolean'];
    }

    public function series()
    {
        return $this->belongsTo(Series::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function getImageUrlAttribute(): string
    {
        return asset('storage/'.$this->image);
    }
}
