<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @taka(1234) — storefront money formatting, ported from the old
        // app's formatTaka() helper (see App\Support\Money).
        Blade::directive('taka', fn ($amount) => "<?php echo \App\Support\Money::taka({$amount}); ?>");
    }
}
