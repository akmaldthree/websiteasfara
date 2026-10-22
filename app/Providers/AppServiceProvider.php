<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\ContentEntry;
use App\Models\Setting;
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
            $view->with('categories', Category::where('active', true)->orderBy('name')->get());
            $view->with('shop', Setting::whereNotIn('key', ['shipping_api_token', 'whatsapp_api_token'])->pluck('value', 'key')->all());
            $view->with('cmsMenus', ContentEntry::published()->where('type', 'menu')->orderBy('sort_order')->get());
        });
        Blade::directive('rupiah', fn ($expression) => "<?php echo 'Rp '.number_format((float)($expression),0,',','.'); ?>");
    }
}
