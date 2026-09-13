<?php

use App\Http\Controllers\LeadController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

Route::livewire('/', 'pages::home')->name('home');

// Subscription Routes
Route::get('/subscriptions/pricing', [SubscriptionController::class, 'pricing'])->name('subscriptions.pricing');
Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout'])->name('subscriptions.checkout');
Route::post('/subscriptions/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

// Stripe Webhook Handlers
Route::post('/api/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// Lead Generation Endpoint for Listings
Route::post('/listings/{business}/leads', [LeadController::class, 'store'])
    ->middleware('subscription.feature:lead_forms')
    ->name('listings.leads.store');

Route::get('/forgot-password', function () {
    return redirect()->route('home');
})->name('password.request');

Route::get('/reset-password/{token}', function (string $token, Request $request) {
    return view('auth.reset-password', [
        'token' => $token,
        'email' => $request->query('email'),
    ]);
})->name('password.reset');

Route::post('/reset-password', function (Request $request) {
    $request->validate([
        'token' => ['required', 'string'],
        'email' => ['required', 'email'],
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        }
    );

    return $status === Password::PASSWORD_RESET
        ? redirect()->route('home')->with('status', __($status))
        : back()->withErrors(['email' => [__($status)]]);
})->name('password.update');
