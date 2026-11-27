<?php

namespace App\Support;

use App\Models\ContentEntry;
use App\Models\Page;
use Illuminate\Support\Collection;

class StorefrontNavigation
{
    public const HEADER_TYPES = [
        'new_in' => 'New In',
        'shop' => 'Shop',
        'collections' => 'Collections',
        'editorial' => 'Lookbook & Journal',
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
                ['kind' => 'editorial', 'placement' => 'both'],
                ['kind' => 'locations', 'placement' => 'both'],
                ['kind' => 'offers', 'placement' => 'both'],
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

    /** @param Collection<int, ContentEntry> $menus */
    public static function headerItems(Collection $links, Collection $menus, bool $hasCampaigns): Collection
    {
        $hasEditorial = $links->contains('kind', 'editorial');
        $archives = collect(['lookbook', 'journal'])->flatMap(fn (string $type) => [
            route('content.index', $type), route('content.index', $type, false),
        ])->all();
        $editorialLinks = $menus->whereIn('link_url', $archives);
        $extras = $menus->whereNotIn('link_url', $archives);
        $hasCustomMenu = $links->contains('kind', 'cms');
        $panelFor = ['shop' => 'shop', 'collections' => $hasCampaigns ? 'collections' : null];

        return $links->map(function (array $link) use ($hasEditorial, $hasCustomMenu, $editorialLinks, $extras, $panelFor): ?array {
            if ($link['kind'] === 'editorial') {
                return $link + ['panel' => 'editorial', 'extras' => $hasCustomMenu ? collect() : $extras];
            }
            if ($link['kind'] !== 'cms') {
                return $link + ['panel' => $panelFor[$link['kind']] ?? null];
            }

            if ($editorialLinks->isNotEmpty() && ! $hasEditorial) {
                return array_replace($link, [
                    'label' => ($link['label_'.app()->getLocale()] ?? null) ?: $editorialLinks->first()->title,
                    'panel' => 'editorial',
                    'extras' => $extras,
                ]);
            }
            if ($extras->count() === 1) {
                return array_replace($link, [
                    'label' => ($link['label_'.app()->getLocale()] ?? null) ?: $extras->first()->title,
                    'href' => $extras->first()->link_url ?: '#',
                    'panel' => null,
                ]);
            }

            return $extras->isEmpty() ? null : $link + ['panel' => 'custom', 'extras' => $extras];
        })->filter()->values();
    }

    private static function resolve(array $link, string $locale, Collection $pages): ?array
    {
        [$label, $href] = match ($link['kind']) {
            'new_in' => ['New In', route('catalog', ['sort' => 'newest'])],
            'shop' => ['Shop', route('catalog')],
            'collections' => ['Collections', route('catalog', ['sort' => 'featured'])],
            'editorial' => ['Lookbook', route('content.index', 'lookbook')],
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
