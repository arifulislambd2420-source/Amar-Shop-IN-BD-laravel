<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Payment\BkashCallbackController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Public product feed (Google Merchant / Meta compatible), matching the old
// Next.js app's exact path so any already-configured Facebook/Google feed
// subscription keeps working unchanged. No auth — it's a public feed.
Route::get('/api/feed/facebook', [FeedController::class, 'facebook'])->name('feed.facebook');

Route::get('/shop', [ShopController::class, 'index'])->name('shop');
Route::get('/offers', [ShopController::class, 'offers'])->name('offers');

Route::get('/product/{slug}', [ProductController::class, 'show'])->name('product.show');
Route::post('/product/{slug}/reviews', [ProductController::class, 'storeReview'])->name('product.reviews.store');

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

// Invariant #1: tracking always requires a phone number — enforced inside
// App\Livewire\Track\TrackForm / App\Services\OrderService::track().
Route::get('/track', [TrackController::class, 'show'])->name('track');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/returns', [PageController::class, 'returns'])->name('returns');
Route::get('/delivery', [PageController::class, 'delivery'])->name('delivery');

// Customer auth — separate `web` guard from the admin (Filament) guard.
Route::middleware('guest:web')->group(function () {
    Route::get('/customer/login', [CustomerAuthController::class, 'showLogin'])->name('customer.login');
    Route::post('/customer/login', [CustomerAuthController::class, 'login'])->name('customer.login.submit');
    Route::get('/customer/register', [CustomerAuthController::class, 'showRegister'])->name('customer.register');
    Route::post('/customer/register', [CustomerAuthController::class, 'register'])->name('customer.register.submit');
});

Route::middleware('auth:web')->group(function () {
    Route::post('/customer/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');
});
