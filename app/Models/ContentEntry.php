<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ContentEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'blocks' => 'array', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function scopePublished($query)
    {
        return $query->where('editorial_status', 'approved')->where('active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    /** @return Collection<int, int> */
    public function campaignProductIds(): Collection
    {
        return collect($this->blocks)->where('type', 'campaign_product')->pluck('series_id')
            ->map(fn ($id) => (int) $id)->filter()->unique()->values();
    }

    public function campaignProducts(): Collection
    {
        $ids = $this->campaignProductIds();
        if ($ids->isEmpty()) {
            return collect();
        }

        $products = Series::published()->withStoreCard()
            ->whereHas('variants', fn ($variants) => $variants->where('active', true))
            ->whereKey($ids->all())->get()->keyBy('id');

        return $ids->map(fn (int $id) => $products->get($id))->filter()->values();
    }

    public function revisions()
    {
        return $this->hasMany(ContentRevision::class, 'content_id')->where('content_type', 'content')->latest();
    }
}
