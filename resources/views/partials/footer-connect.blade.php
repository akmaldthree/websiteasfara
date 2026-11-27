<div class="col-lg-3 footer-connect">
    <h2>{{ __('store.connect') }}</h2>
    @if($newsletterEnabled)
        <p class="small">{{ $storeCopy['newsletter_text'] }}</p>
        <form class="footer-newsletter" method="post" action="{{ route('newsletter.store') }}">
            @csrf
            <label class="visually-hidden" for="newsletter-email">Email</label>
            <div><input id="newsletter-email" type="email" name="email" placeholder="{{ __('store.email') }}" required><button aria-label="Daftar newsletter">→</button></div>
            <label><input type="checkbox" name="consent" value="1" required> {{ __('store.newsletter_consent') }}</label>
        </form>
    @endif
    @if($shop['footer_social_enabled'] ?? true)
        <div class="footer-social">
            @if(!empty($shop['whatsapp']))
                <a href="https://wa.me/{{ $shop['whatsapp'] }}" target="_blank" rel="noopener"><i>WA</i><span>WhatsApp</span></a>
            @endif
            @foreach(['instagram_url' => ['IG', 'Instagram'], 'tiktok_url' => ['TT', 'TikTok'], 'facebook_url' => ['FB', 'Facebook']] as $key => $social)
                @if(!empty($shop[$key]))<a href="{{ $shop[$key] }}" target="_blank" rel="noopener"><i>{{ $social[0] }}</i><span>{{ $social[1] }}</span></a>@endif
            @endforeach
        </div>
    @endif
    <details class="footer-language language-picker">
        <summary>{{ app()->getLocale() === 'id' ? 'Indonesia' : 'English' }}</summary>
        <div>
            <a href="{{ route('locale', 'id') }}" @class(['is-active' => app()->getLocale() === 'id'])><span>Indonesia</span><small>ID</small></a>
            <a href="{{ route('locale', 'en') }}" @class(['is-active' => app()->getLocale() === 'en'])><span>English</span><small>EN</small></a>
        </div>
    </details>
</div>
