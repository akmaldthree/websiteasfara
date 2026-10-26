<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * Remove stale or malformed session lines so one bad value cannot block the
     * whole storefront cart. The request that adds an item remains validated
     * separately by the controller.
     */
    public function normalize(array $cart): array
    {
        $normalized = [];
        foreach (array_slice($cart, 0, 50, true) as $variantId => $quantity) {
            $id = filter_var($variantId, FILTER_VALIDATE_INT);
            $qty = filter_var($quantity, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1 || $qty === false || $qty < 1 || $qty > 100) {
                continue;
            }
            $normalized[(int) $id] = (int) $qty;
        }

        return $normalized;
    }

    public function merge(User $user, array $cart, array $customizations): array
    {
        $cart = $this->normalize($cart);
        foreach ($user->cartItems()->get() as $item) {
            $id = $item->variant_id;
            $cart[$id] = min(100, (int) ($cart[$id] ?? 0) + $item->quantity);
            if (! isset($customizations[$id]) && $item->customization) {
                $customizations[$id] = $item->customization;
            }
        }
        $cart = array_slice($cart, 0, 50, true);
        $customizations = array_intersect_key($customizations, $cart);
        $this->sync($user, $cart, $customizations);

        return compact('cart', 'customizations');
    }

    public function sync(User $user, array $cart, array $customizations = []): void
    {
        $cart = $this->normalize($cart);
        DB::transaction(function () use ($user, $cart, $customizations) {
            $ids = array_map('intval', array_keys($cart));
            $user->cartItems()->when($ids, fn ($query) => $query->whereNotIn('variant_id', $ids))->when(! $ids, fn ($query) => $query)->delete();
            foreach ($cart as $variantId => $quantity) {
                $user->cartItems()->updateOrCreate(['variant_id' => $variantId], [
                    'quantity' => max(1, min(100, (int) $quantity)),
                    'customization' => $customizations[$variantId] ?? null,
                ]);
            }
        });
    }
}
