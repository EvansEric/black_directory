<?php

use App\Http\Controllers\Admin\BusinessVerificationController;
use App\Http\Controllers\BusinessLinkController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');
Route::livewire('/pricing', 'pages::pricing')->name('pricing');
Route::livewire('/mission', 'pages::mission')->name('mission');
Route::get('/b/{slug}', BusinessLinkController::class)->where('slug', '[a-z0-9-]+')->name('business.link');

Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/dashboard', 'pages::admin-dashboard')->name('dashboard');
    Route::get('/businesses', [BusinessVerificationController::class, 'index'])->name('businesses.index');
    Route::patch('/businesses/{business}/verification', [BusinessVerificationController::class, 'update'])->name('businesses.verification');
});

// Subscription Routes
Route::get('/subscriptions/pricing', [SubscriptionController::class, 'pricing'])->name('subscriptions.pricing');
Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout'])->name('subscriptions.checkout');
Route::get('/subscriptions/success', [SubscriptionController::class, 'accountSuccess'])->name('subscriptions.account-success');
Route::get('/subscriptions/success/{business}', [SubscriptionController::class, 'success'])->name('subscriptions.success');
Route::post('/subscriptions/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

// Stripe webhooks (Cashier also serves them at /stripe/webhook)
Route::post('/api/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook'])->name('webhooks.stripe');

// Lead Generation Endpoint for Listings
Route::post('/listings/{business}/leads', [LeadController::class, 'store'])
    ->middleware('subscription.feature:lead_forms')
    ->name('listings.leads.store');

Route::redirect('/forgot-password', '/')->name('password.request');

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::get('/reset-password/{token}', [PasswordResetController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'store'])->name('password.update');
