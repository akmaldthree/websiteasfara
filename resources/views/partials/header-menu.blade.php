@php
    $panel = $item['panel'];
    $features = $mobile ? collect() : match ($panel) {
        'collections' => $campaignMenu->map(fn ($entries) => $entries->first(fn ($entry) => $entry->image))
            ->filter()->take(2)->map(fn ($entry) => ['type' => 'campaign', 'entry' => $entry]),
        'editorial' => $headerEditorial->map(fn ($entries, $type) => ['type' => $type, 'entry' => $entries->firstWhere('image', '!=', null)])->filter(fn ($photo) => $photo['entry']?->image),
        default => collect(),
    };
    $photos = $features->map(fn ($photo) => [
        'image' => $photo['entry']->image_url,
        'title' => $photo['entry']->title,
        'url' => route('content.show', [$photo['type'], $photo['entry']->slug]),
    ]);
    if (!$mobile && $panel === 'collections' && $photos->isEmpty()) {
        $photos = $curatedLinks->get('promo', collect())->filter->image->take(2)->map(fn ($link) => [
            'image' => $link->image_url, 'title' => $link->title, 'url' => $link->url,
        ]);
    }
    $extraMenus = $item['extras'] ?? collect();
    if ($panel === 'editorial' && $extraMenus->isNotEmpty()) {
        $articleUrls = $headerEditorial->flatMap(fn ($entries, $type) => $entries->flatMap(fn ($entry) => [
            route('content.show', [$type, $entry->slug]),
            route('content.show', [$type, $entry->slug], false),
        ]));
        $extraMenus = $extraMenus->whereNotIn('link_url', $articleUrls->all());
    }
@endphp
<div @class([
    'mobile-discovery-links' => $mobile,
    'container-xxl store-nav-content' => !$mobile,
    'has-nav-media' => $photos->isNotEmpty(),
])>
    <div class="store-nav-text">
        <div class="store-nav-groups">
            @switch($panel)
                @case('shop')
                    <section class="store-nav-group">
                        <p class="store-nav-heading">{{ $storeCopy['mega_shop_by_category'] }}</p>
                        <ul class="store-nav-links">
                            <li><a href="{{ $item['href'] }}">{{ $storeCopy['mega_view_all'] }}</a></li>
                            @if($shop['header_categories_enabled'] ?? true)
                                @foreach($categories as $category)
                                    <li><a href="{{ route('catalog', ['category' => $category->slug]) }}" @if(request()->routeIs('catalog') && request('category') === $category->slug) aria-current="page" @endif>{{ $category->name }}</a></li>
                                @endforeach
                            @endif
                        </ul>
                    </section>
                    @foreach(['fit' => 'mega_find_fit', 'promo' => 'mega_promo_label'] as $section => $label)
                        @php($links = $curatedLinks->get($section, collect()))
                        @continue($links->isEmpty())
                        <section class="store-nav-group">
                            <p class="store-nav-heading">{{ $storeCopy[$label] }}</p>
                            <ul class="store-nav-links">
                                @foreach($links as $link)<li><a href="{{ $link->url }}">{{ $link->title }}</a></li>@endforeach
                            </ul>
                        </section>
                    @endforeach
                    @break
                @case('collections')
                    @include('partials.campaign-navigation')
                    @break
                @case('editorial')
                    @foreach(['lookbook' => ['Lookbook', 'nav_lookbook_all'], 'journal' => [__('store.nav_journal'), 'nav_journal_all']] as $type => [$title, $archive])
                        <section class="store-nav-group">
                            <p class="store-nav-heading">{{ $title }}</p>
                            <ul class="store-nav-links">
                                @foreach($headerEditorial->get($type) as $entry)
                                    <li><a href="{{ route('content.show', [$type, $entry->slug]) }}">{{ $entry->title }}</a></li>
                                @endforeach
                                <li><a class="store-nav-archive" href="{{ route('content.index', $type) }}">{{ __('store.'.$archive) }} <span aria-hidden="true">→</span></a></li>
                            </ul>
                        </section>
                    @endforeach
                    @break
            @endswitch
            @if($extraMenus->isNotEmpty())
                <section class="store-nav-group">
                    <p class="store-nav-heading">{{ $storeCopy['mega_explore'] }}</p>
                    <ul class="store-nav-links">
                        @foreach($extraMenus as $menu)<li><a href="{{ $menu->link_url ?: '#' }}">{{ $menu->title }}</a></li>@endforeach
                    </ul>
                </section>
            @endif
        </div>
        @if($panel === 'collections')
            <a class="store-nav-archive" href="{{ route('content.index', 'campaign') }}">{{ __('store.nav_campaigns_all') }} <span aria-hidden="true">→</span></a>
        @endif
    </div>
    @if($photos->isNotEmpty())
        <div class="store-nav-media">
            @foreach($photos as $photo)
                <a class="store-nav-card" href="{{ $photo['url'] }}">
                    <img src="{{ $photo['image'] }}" alt="" width="480" height="300" loading="lazy" decoding="async">
                    <span>{{ $photo['title'] }} <span aria-hidden="true">→</span></span>
                </a>
            @endforeach
        </div>
    @endif
</div>
