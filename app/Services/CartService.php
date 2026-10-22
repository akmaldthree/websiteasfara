<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function merge(User $user, array $cart, array $customizations): array
    {
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
