<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Series extends Model
{
    protected $table = 'series';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'featured' => 'boolean', 'uses_product_type' => 'boolean', 'compare_at_price' => 'integer', 'size_chart' => 'array'];
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

    public function reviews()
    {
        return $this->hasManyThrough(Review::class, Variant::class)->where('reviews.status', 'approved');
    }

    public function scopePublished($q)
    {
        return $q->where('active', true)->whereHas('category', fn ($c) => $c->where('active', true));
    }

    public function scopeWithStoreCard(Builder $query): Builder
    {
        return $query->with([
            'category',
            'media' => fn ($media) => $media->where('active', true),
            'variants' => fn ($variants) => $variants->where('active', true),
        ])->withMin(['variants' => fn ($variants) => $variants->where('active', true)], 'price')
            ->withCount('reviews')->withAvg('reviews', 'rating');
    }

    public function scopeMatchingStorefrontSearch(Builder $query, string $search): Builder
    {
        $terms = array_unique(explode(' ', Str::squish(mb_substr($search, 0, 100))));

        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }

            $pattern = '%'.$term.'%';
            $query->where(fn (Builder $series) => $series
                ->whereLike('name', $pattern)
                ->orWhereLike('slug', $pattern)
                ->orWhereHas('category', fn (Builder $category) => $category->whereLike('name', $pattern)->orWhereLike('slug', $pattern))
                ->orWhereHas('variants', fn (Builder $variants) => $variants->where('active', true)
                    ->where(fn (Builder $variant) => $variant->whereLike('sku', $pattern)
                        ->orWhereLike('color', $pattern)
                        ->orWhereLike('size', $pattern)
                        ->orWhereLike('product_type', $pattern))));
        }

        return $query;
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image ? asset('storage/'.$this->image) : asset('images/collection.svg');
    }
}
