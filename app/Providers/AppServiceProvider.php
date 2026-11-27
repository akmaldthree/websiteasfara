<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CuratedLink;
use App\Models\Setting;
use App\Models\StoreLocation;
use App\Models\Variant;
use App\Services\PricingService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    private function popupCoupon(ContentEntry $campaign): ?Coupon
    {
        $selected = Setting::get('welcome_voucher_coupon_id');
        $couponId = $selected ?: Setting::get('campaign_coupon_'.$campaign->id);
        $coupon = $couponId ? Coupon::find($couponId) : null;
        if ($selected === null && ! $couponId) {
            $code = strtoupper(trim((string) Setting::get('welcome_voucher_code', '')));
            $coupon = $code === '' ? null : Coupon::where('code', $code)->first();
        }
        if (! $coupon?->isAvailableAt(now())) {
            return null;
        }

        $user = request()->user();
        $groupId = app(PricingService::class)->groupFor($user)?->id;
        if (! $coupon->appliesToAudience($user !== null, $groupId)) {
            return null;
        }
        if ($coupon->per_user_limit && (! $user || CouponRedemption::where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)->count() >= $coupon->per_user_limit)) {
            return null;
        }

        return $coupon;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        View::composer('layouts.store', function ($view) {
            $campaignMenu = ContentEntry::published()->where('type', 'campaign')->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'title', 'slug', 'blocks'])
                ->filter(fn (ContentEntry $campaign) => $campaign->campaignProductIds()->isNotEmpty())
                ->groupBy(fn (ContentEntry $campaign) => $campaign->campaignGroup());
            $view->with('campaignMenu', $campaignMenu);
            $popupCampaignId = Setting::get('welcome_voucher_enabled', false) ? Setting::get('welcome_voucher_campaign') : null;
            $popupCampaign = $popupCampaignId
                ? ContentEntry::published()->where('type', 'campaign')->find($popupCampaignId)
                : null;
            $view->with('popupCampaign', $popupCampaign);
            $view->with('popupCoupon', $popupCampaign ? $this->popupCoupon($popupCampaign) : null);
        });
        View::composer(['layouts.store', 'store.*'], function ($view) {
            if (request()->hasSession()) {
                app()->setLocale(in_array(session('locale'), ['id', 'en'], true) ? session('locale') : 'id');
            }
            $view->with('categories', Category::where('active', true)->with(['series' => fn ($query) => $query->published()->orderBy('name')])->orderBy('name')->get());
            $view->with('shop', Setting::whereNotIn('key', ['shipping_api_token', 'whatsapp_api_token', 'home_layout_draft'])->pluck('value', 'key')->all());
            $locale = app()->getLocale();
            $copyOverrides = array_filter(Setting::get('storefront_copy_'.$locale, []), fn ($value) => trim((string) $value) !== '');
            $view->with('storeCopy', array_replace(config('storefront.copy_defaults.'.$locale, config('storefront.copy_defaults.id')), $copyOverrides));
            $view->with('cmsMenus', ContentEntry::published()->where('type', 'menu')->orderBy('sort_order')->get());
            $view->with('curatedLinks', CuratedLink::where('active', true)->orderBy('sort_order')->get()->groupBy('section'));
            $view->with('storeLocations', StoreLocation::where('active', true)->orderBy('sort_order')->get());
        });
        View::composer('partials.product-card', function ($view) {
            $pricing = app(PricingService::class);
            $attributes = request()->attributes;
            if (! $attributes->has('asfara_card_price_group')) {
                $attributes->set('asfara_card_price_group', $pricing->groupFor(request()->user()));
            }
            $priceGroup = $attributes->get('asfara_card_price_group');
            if (! $priceGroup) {
                return;
            }

            $variants = $view->getData()['collection']->variants;
            $variants->loadMissing(['prices' => fn ($prices) => $prices->where('price_group_id', $priceGroup->id)->where('active', true)]);
            $view->with('cardUnitPrices', $variants->mapWithKeys(fn (Variant $variant) => [
                $variant->id => $pricing->resolveForGroup($variant, 1, $priceGroup)['price'],
            ]));
        });
        Blade::directive('rupiah', fn ($expression) => "<?php echo 'Rp '.number_format((float)($expression),0,',','.'); ?>");
    }
}
