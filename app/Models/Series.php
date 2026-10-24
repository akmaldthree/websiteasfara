<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Series extends Model
{
    protected $table = 'series';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'featured' => 'boolean', 'uses_product_type' => 'boolean', 'compare_at_price' => 'integer'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    public function media()
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orderItems()
    {
        return $this->hasManyThrough(OrderItem::class, Variant::class, 'series_id', 'variant_id');
    }

    public function questions()
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function scopePublished($q)
    {
        return $q->where('active', true)->whereHas('category', fn ($c) => $c->where('active', true));
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image ? asset('storage/'.$this->image) : asset('images/collection.svg');
    }
}
