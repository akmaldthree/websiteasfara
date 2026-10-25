<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\CuratedLink;
use App\Models\Setting;
use App\Models\StoreLocation;
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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        View::composer(['layouts.store', 'store.*'], function ($view) {
            if (request()->hasSession()) {
                app()->setLocale(in_array(session('locale'), ['id', 'en'], true) ? session('locale') : 'id');
            }
            $view->with('categories', Category::where('active', true)->with(['series' => fn ($query) => $query->published()->orderBy('name')])->orderBy('name')->get());
            $view->with('shop', Setting::whereNotIn('key', ['shipping_api_token', 'whatsapp_api_token'])->pluck('value', 'key')->all());
            $view->with('cmsMenus', ContentEntry::published()->where('type', 'menu')->orderBy('sort_order')->get());
            $view->with('curatedLinks', CuratedLink::where('active', true)->orderBy('sort_order')->get()->groupBy('section'));
            $view->with('storeLocations', StoreLocation::where('active', true)->orderBy('sort_order')->get());
        });
        Blade::directive('rupiah', fn ($expression) => "<?php echo 'Rp '.number_format((float)($expression),0,',','.'); ?>");
    }
}
