@foreach(config('storefront.campaign_groups') as $group => $label)
    @php($menuEntries = $campaignMenu->get($group, collect())->take(8))
    @continue($menuEntries->isEmpty())
    <section class="store-nav-group" aria-label="{{ $label }}">
        <p class="store-nav-heading">{{ $label }}</p>
        <ul class="store-nav-links">
            @foreach($menuEntries as $campaignLink)
                <li><a href="{{ route('content.show', ['campaign', $campaignLink->slug]) }}">{{ $campaignLink->title }}</a></li>
            @endforeach
        </ul>
    </section>
@endforeach
