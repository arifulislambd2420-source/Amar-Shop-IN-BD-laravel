<?php

namespace App\Providers;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\CartService;
use App\Support\AdminLang;
use App\Support\Money;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        $this->app->scoped(CartService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Bangla default labels for admin fields / columns / filters (see AdminLang).
        AdminLang::register();

        // Behind the host's proxy the app can see the request as plain http
        // (X-Forwarded-Proto ignored — see TrustedProxies), which makes
        // asset()/@vite emit http:// CSS and JS links that browsers block as
        // mixed content on an https page. APP_URL is the source of truth.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // @taka(1234) — storefront money formatting, ported from the old
        // app's formatTaka() helper (see App\Support\Money).
        // Customer SMS on order confirmed / shipped / delivered.
        Order::observe(OrderObserver::class);

        // Per-IP throttles for public forms (applied in routes/web.php; the
        // checkout and landing order forms are Livewire actions and use
        // RateLimiter directly). Real client IPs depend on the trusted-proxy
        // setting in bootstrap/app.php.
        RateLimiter::for('customer-login', fn (Request $r) => [
            Limit::perMinute(6)->by($r->ip().'|'.mb_strtolower((string) $r->input('phone'))),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        RateLimiter::for('customer-register', fn (Request $r) => [
            Limit::perMinute(3)->by($r->ip()),
            Limit::perHour(10)->by($r->ip()),
        ]);
        RateLimiter::for('review', fn (Request $r) => Limit::perMinutes(10, 5)->by($r->ip()));
        RateLimiter::for('contact', fn (Request $r) => Limit::perMinutes(10, 5)->by($r->ip()));

        Blade::directive('taka', fn ($amount) => "<?php echo \App\Support\Money::taka({$amount}); ?>");
    }
}
