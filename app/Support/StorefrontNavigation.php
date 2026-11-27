<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Collection;

class StorefrontNavigation
{
    public const HEADER_TYPES = [
        'new_in' => 'New In',
        'shop' => 'Shop',
        'collections' => 'Collections',
        'locations' => 'Find Us',
        'offers' => 'Special Offer',
        'tracking' => 'Lacak pesanan',
        'cms' => 'Menu dari konten',
        'link' => 'Tautan kustom',
    ];

    public const FOOTER_TYPES = [
        'catalog' => 'Semua koleksi',
        'campaigns' => 'Campaign',
        'lookbook' => 'Lookbook',
        'offers' => 'Special Offer',
        'account' => 'Akun saya',
        'tracking' => 'Lacak pesanan',
        'locations' => 'Lihat lokasi toko',
        'page' => 'Halaman informasi',
        'link' => 'Tautan kustom',
    ];

    public const FOOTER_GROUPS = ['brand' => 'Brand', 'shop' => 'Belanja', 'help' => 'Bantuan', 'stores' => 'Toko'];

    public const FOOTER_SECTIONS = ['shop' => 'Belanja', 'help' => 'Bantuan', 'stores' => 'Toko', 'connect' => 'Newsletter dan sosial'];

    public static function configuration(array $saved, Collection $pages): array
    {
        return $saved + [
            'header' => [
                ['kind' => 'new_in', 'placement' => 'both'],
                ['kind' => 'shop', 'placement' => 'both'],
                ['kind' => 'collections', 'placement' => 'both'],
                ['kind' => 'cms', 'placement' => 'desktop'],
                ['kind' => 'locations', 'placement' => 'desktop'],
                ['kind' => 'offers', 'placement' => 'both'],
                ['kind' => 'cms', 'placement' => 'mobile'],
                ['kind' => 'locations', 'placement' => 'mobile'],
                ['kind' => 'tracking', 'placement' => 'mobile'],
            ],
            'footer' => [
                ['kind' => 'catalog', 'group' => 'shop'],
                ['kind' => 'campaigns', 'group' => 'shop'],
                ['kind' => 'lookbook', 'group' => 'shop'],
                ['kind' => 'offers', 'group' => 'shop'],
                ['kind' => 'account', 'group' => 'help'],
                ['kind' => 'tracking', 'group' => 'help'],
                ...$pages->map(fn (Page $page) => [
                    'kind' => 'page', 'page_id' => $page->id, 'group' => $page->slug === 'tentang' ? 'brand' : 'help',
                ])->values()->all(),
                ['kind' => 'locations', 'group' => 'stores'],
            ],
            'footer_order' => array_keys(self::FOOTER_SECTIONS),
            'newsletter_enabled' => true,
        ];
    }

    public static function storefront(array $shop): array
    {
        $pages = ($shop['footer_pages_enabled'] ?? true)
            ? Page::published()->orderBy('title')->get(['id', 'title', 'slug'])->keyBy('id')
            : collect();
        $navigation = self::configuration($shop['storefront_navigation'] ?? [], $pages);
        $locale = app()->getLocale();
        $trackingEnabled = $shop['header_tracking_enabled'] ?? true;
        $isVisible = fn (array $link): bool => ($link['enabled'] ?? true) && ($link['kind'] !== 'tracking' || $trackingEnabled);
        $header = collect($navigation['header'])->filter($isVisible)->map(fn (array $link) => self::resolve($link, $locale, $pages));
        $footer = collect($navigation['footer'])->filter($isVisible)->map(fn (array $link) => self::resolve($link, $locale, $pages))->filter();

        return [
            'desktop' => $header->whereIn('placement', ['both', 'desktop']),
            'mobile' => $header->whereIn('placement', ['both', 'mobile']),
            'footer' => $footer->groupBy('group'),
            'footer_order' => $navigation['footer_order'],
            'newsletter_enabled' => $navigation['newsletter_enabled'],
        ];
    }

    private static function resolve(array $link, string $locale, Collection $pages): ?array
    {
        [$label, $href] = match ($link['kind']) {
            'new_in' => ['New In', route('catalog', ['sort' => 'newest'])],
            'shop' => ['Shop', route('catalog')],
            'collections' => ['Collections', route('catalog', ['sort' => 'featured'])],
            'catalog' => [__('store.all_collections'), route('catalog')],
            'campaigns' => ['Campaign', route('content.index', 'campaign')],
            'lookbook' => ['Lookbook', route('content.index', 'lookbook')],
            'offers' => ['Special Offer', route('catalog', ['sale' => 1])],
            'locations' => [isset($link['group']) ? __('store.view_locations') : 'Find Us', route('store.locations')],
            'account' => [__('store.account'), route('account')],
            'tracking' => [__('store.track'), route('track')],
            'cms' => [__('store.more_links'), null],
            'link' => [$link['label_id'], $link['url']],
            'page' => self::pageLink($pages->get($link['page_id'])),
        };

        if ($label === null) {
            return null;
        }

        return $link + [
            'label' => ($link['label_'.$locale] ?? null) ?: $label,
            'href' => $href,
        ];
    }

    private static function pageLink(?Page $page): array
    {
        return $page ? [$page->title, route('page', $page->slug)] : [null, null];
    }
}
