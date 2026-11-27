<?php

namespace App\Support;

class HomeLayout
{
    public const NEW_IN_LIMIT = 12;

    public const CLOSING_PRODUCT_LIMIT = 4;

    /** @return list<string> */
    public static function order(array|string|null $saved): array
    {
        $sections = array_keys(config('storefront.home_sections'));
        $items = is_array($saved) ? $saved : preg_split('/[,\s]+/', $saved ?? '');
        $order = array_values(array_unique(array_intersect($items, $sections)));
        $banner = array_search('campaign', $order, true);

        // Keep the product section beside its banner when opening layouts saved before it was configurable.
        if ($banner !== false && ! in_array('campaign_products', $order, true)) {
            array_splice($order, $banner + 1, 0, ['campaign_products']);
        }

        return [...$order, ...array_diff($sections, $order)];
    }
}
