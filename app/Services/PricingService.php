<?php

namespace App\Services;

use App\Models\PriceGroup;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Support\Collection;

class PricingService
{
    public function groupFor(?User $customer): ?PriceGroup
    {
        if (! $customer || $customer->role !== 'customer') {
            return null;
        }

        $account = $customer->businessAccount()->with('priceGroup')->first();

        return $account?->status === 'approved' && $account->priceGroup?->active ? $account->priceGroup : null;
    }

    /** @return array{price: int, minimum_quantity: int|null, group: PriceGroup|null} */
    public function resolve(Variant $variant, int $quantity, ?User $customer): array
    {
        return $this->resolveForGroup($variant, $quantity, $this->groupFor($customer));
    }

    /** @return array{price: int, minimum_quantity: int|null, group: PriceGroup|null} */
    public function resolveForGroup(Variant $variant, int $quantity, ?PriceGroup $group): array
    {
        if (! $group) {
            return ['price' => $variant->price, 'minimum_quantity' => null, 'group' => null];
        }

        $tiers = $variant->relationLoaded('prices')
            ? $variant->prices
            : $variant->prices()->where('price_group_id', $group->id)->where('active', true)->get();
        $tier = $tiers->where('price_group_id', $group->id)->where('active', true)
            ->where('minimum_quantity', '<=', $quantity)->sortByDesc('minimum_quantity')->first();

        return ['price' => $tier?->price ?? $variant->price, 'minimum_quantity' => $tier?->minimum_quantity, 'group' => $group];
    }

    public function tiersFor(Variant $variant, ?User $customer): Collection
    {
        $group = $this->groupFor($customer);
        if (! $group) {
            return collect();
        }

        return $variant->prices()->where('price_group_id', $group->id)->where('active', true)->orderBy('minimum_quantity')->get();
    }
}
