<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\MediaFileController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Payment\BkashCallbackController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Search-engine files. robots.txt is dynamic so its Sitemap line carries the
// real domain (a static public/robots.txt cannot know it).
Route::get('/sitemap.xml', \App\Http\Controllers\SitemapController::class)->name('sitemap');
Route::get('/robots.txt', function () {
    $lines = [
        'User-agent: *',
        'Disallow: /admin',
        'Disallow: /cart',
        'Disallow: /checkout',
        'Disallow: /track',
        'Disallow: /customer',
        'Disallow: /order/',
        'Disallow: /livewire',
        'Allow: /',
        '',
        'Sitemap: '.url('/sitemap.xml'),
    ];

    return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

// Fallback for uploaded images when the public/storage symlink is missing
// (storage:link fails on this host) — never reached when the symlink
// exists. No session/cookie middleware: an image shouldn't start a session.
Route::get('/storage/media/{file}', [MediaFileController::class, 'show'])
    ->name('media.file')
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
    ]);

// Public product feed (Google Merchant / Meta compatible), matching the old
// Next.js app's exact path so any already-configured Facebook/Google feed
// subscription keeps working unchanged. No auth — it's a public feed.
Route::get('/api/feed/facebook', [FeedController::class, 'facebook'])->name('feed.facebook');

Route::get('/shop', [ShopController::class, 'index'])->name('shop');
Route::get('/offers', [ShopController::class, 'offers'])->name('offers');

Route::get('/product/{slug}', [ProductController::class, 'show'])->name('product.show');
Route::post('/product/{slug}/reviews', [ProductController::class, 'storeReview'])->middleware('throttle:review')->name('product.reviews.store');

Route::get('/brands', [BrandController::class, 'index'])->name('brands');

// Campaign landing pages — standalone single-product funnel pages, no site
// header/footer/cart. Only ever serves is_active=true pages (404 otherwise).
Route::get('/lp/{slug}', [LandingPageController::class, 'show'])->name('landing.show');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');

// bKash redirects the customer's browser here after the hosted payment
// page — see App\Http\Controllers\Payment\BkashCallbackController for why
// the query string alone is never trusted to confirm anything.
Route::get('/payment/bkash/callback', [BkashCallbackController::class, 'callback'])->name('payment.bkash.callback');

// Invariant #2: order confirmation is looked up ONLY by the random
// order_token, never the sequential numeric id.
Route::get('/order/{token}', [OrderController::class, 'show'])->name('order.show');

// Printable invoice — token-gated, so only the order's customer (who has the
// token) or an admin (who links here from the panel) can reach it.
Route::get('/order/{token}/invoice', [InvoiceController::class, 'show'])->name('order.invoice');

// Invariant #1: tracking always requires a phone number — enforced inside
// App\Livewire\Track\TrackForm / App\Services\OrderService::track().
Route::get('/track', [TrackController::class, 'show'])->name('track');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/returns', [PageController::class, 'returns'])->name('returns');
Route::get('/delivery', [PageController::class, 'delivery'])->name('delivery');

// Customer auth — separate `web` guard from the admin (Filament) guard.
Route::middleware('guest:web')->group(function () {
    Route::get('/customer/login', [CustomerAuthController::class, 'showLogin'])->name('customer.login');
    Route::post('/customer/login', [CustomerAuthController::class, 'login'])->middleware('throttle:customer-login')->name('customer.login.submit');
    Route::get('/customer/register', [CustomerAuthController::class, 'showRegister'])->name('customer.register');
    Route::post('/customer/register', [CustomerAuthController::class, 'register'])->middleware('throttle:customer-register')->name('customer.register.submit');
});

Route::middleware('auth:web')->group(function () {
    Route::post('/customer/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');

    // Account: order history, order details, saved addresses.
    Route::get('/customer/account', [\App\Http\Controllers\Customer\AccountController::class, 'index'])->name('customer.account');
    Route::get('/customer/orders/{order}', [\App\Http\Controllers\Customer\AccountController::class, 'order'])->whereNumber('order')->name('customer.orders.show');
    Route::post('/customer/addresses', [\App\Http\Controllers\Customer\AccountController::class, 'storeAddress'])->name('customer.addresses.store');
    Route::put('/customer/addresses/{address}', [\App\Http\Controllers\Customer\AccountController::class, 'updateAddress'])->whereNumber('address')->name('customer.addresses.update');
    Route::delete('/customer/addresses/{address}', [\App\Http\Controllers\Customer\AccountController::class, 'destroyAddress'])->whereNumber('address')->name('customer.addresses.destroy');
    Route::post('/customer/addresses/{address}/default', [\App\Http\Controllers\Customer\AccountController::class, 'defaultAddress'])->whereNumber('address')->name('customer.addresses.default');
});
