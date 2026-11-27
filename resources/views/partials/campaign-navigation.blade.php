@foreach(config('storefront.campaign_groups') as $group => $label)
    @php($menuEntries = $campaignMenu->get($group, collect())->take(8))
    @continue($menuEntries->isEmpty())
    <section class="campaign-menu-group" aria-label="{{ $label }}">
        <strong>{{ $label }}</strong>
        @foreach($menuEntries as $campaignLink)
            <a href="{{ route('content.show', ['campaign', $campaignLink->slug]) }}">{{ $campaignLink->title }}</a>
        @endforeach
    </section>
@endforeach
