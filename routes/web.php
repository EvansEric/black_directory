<?php

use App\Http\Controllers\Admin\BusinessVerificationController;
use App\Http\Controllers\BusinessLinkController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PlacesAutocompleteController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');
Route::livewire('/pricing', 'pages::pricing')->name('pricing');
Route::livewire('/mission', 'pages::mission')->name('mission');
Route::livewire('/listing-disclosure', 'pages::listing-disclosure')->name('listing-disclosure');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemaps/pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemaps/businesses/{page}.xml', [SitemapController::class, 'businesses'])
    ->where('page', '[1-9][0-9]*')->name('sitemap.businesses');
Route::redirect('/login', '/')->name('login');
Route::get('/b/{slug}', BusinessLinkController::class)->where('slug', '[a-z0-9-]+')->name('business.link');
Route::get('/businesses/{business}', BusinessProfileController::class)->name('businesses.show');
Route::get('/businesses/{business}/reviews', [ReviewController::class, 'index'])->name('reviews.index');

Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/dashboard', 'pages::admin-dashboard')->name('dashboard');
    Route::get('/businesses', [BusinessVerificationController::class, 'index'])->name('businesses.index');
    Route::patch('/businesses/{business}/verification', [BusinessVerificationController::class, 'update'])->name('businesses.verification');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/profile', 'pages::profile')->name('profile');
    Route::get('/places/autocomplete', [PlacesAutocompleteController::class, 'index'])
        ->middleware('throttle:60,1')->name('places.autocomplete');
    Route::get('/places/{place}', [PlacesAutocompleteController::class, 'show'])
        ->middleware('throttle:60,1')->name('places.show');
    Route::post('/email/verification-notification', function (Request $request) {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'If your email is not verified, a verification link has been sent.');
    })->middleware('throttle:3,60')->name('verification.send');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->route('home')->with('status', 'Your email address has been verified.');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
});

// Subscription Routes
Route::get('/subscriptions/pricing', [SubscriptionController::class, 'pricing'])->name('subscriptions.pricing');
Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout'])->name('subscriptions.checkout');
Route::get('/subscriptions/success', [SubscriptionController::class, 'accountSuccess'])->name('subscriptions.account-success');
Route::get('/subscriptions/success/{business}', [SubscriptionController::class, 'success'])->name('subscriptions.success');
Route::post('/subscriptions/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

// Stripe webhooks (Cashier also serves them at /stripe/webhook)
Route::post('/api/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook'])->name('webhooks.stripe');

Route::redirect('/forgot-password', '/')->name('password.request');

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::get('/reset-password/{token}', [PasswordResetController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'store'])->name('password.update');
