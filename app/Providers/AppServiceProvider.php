<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Support\Money;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One CartService per request, so its memoized lines() is shared by
        // the cart page, drawer, badge and checkout within that request.
        $this->app->scoped(\App\Services\CartService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @taka(1234) — storefront money formatting, ported from the old
        // app's formatTaka() helper (see App\Support\Money).
        // Customer SMS on order confirmed / shipped / delivered.
        Order::observe(OrderObserver::class);

        Blade::directive('taka', fn ($amount) => "<?php echo \App\Support\Money::taka({$amount}); ?>");
    }
}
