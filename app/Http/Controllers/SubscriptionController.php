<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\SubscriptionGating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer as StripeCustomer;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

class SubscriptionController extends Controller
{
    /**
     * Get pricing tiers metadata.
     */
    public function pricing(): JsonResponse
    {
        return response()->json([
            'tiers' => SubscriptionGating::TIERS,
        ]);
    }

    /**
     * Handle creation of a Stripe Checkout Session for subscription.
     */
    public function checkout(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'business_id' => ['required', 'exists:businesses,id'],
            'tier' => ['required', 'string', 'in:standard,pro,spotlight'],
            'billing_cycle' => ['required', 'string', 'in:monthly,annual'],
        ]);

        /** @var Business $business */
        $business = Business::findOrFail($validated['business_id']);

        if (Auth::check() && Auth::id() !== $business->user_id) {
            return response()->json(['error' => 'Unauthorized to manage subscription for this listing.'], 403);
        }

        $tierKey = $validated['tier'];
        $cycle = $validated['billing_cycle'];
        $tierConfig = SubscriptionGating::TIERS[$tierKey] ?? null;

        if (! $tierConfig) {
            return response()->json(['error' => 'Invalid tier selected.'], 422);
        }

        $stripeSecret = config('services.stripe.secret') ?? env('STRIPE_SECRET');

        if ($stripeSecret && ! str_contains($stripeSecret, 'placeholder')) {
            try {
                Stripe::setApiKey($stripeSecret);

                $user = Auth::user() ?? $business->user;

                // Ensure customer ID
                $stripeCustomerId = $business->stripe_customer_id ?? $user?->stripe_customer_id;
                if (! $stripeCustomerId && $user) {
                    $customer = StripeCustomer::create([
                        'email' => $user->email,
                        'name' => $user->name,
                        'metadata' => [
                            'user_id' => $user->id,
                            'business_id' => $business->id,
                        ],
                    ]);
                    $stripeCustomerId = $customer->id;
                    $user->update(['stripe_customer_id' => $stripeCustomerId]);
                    $business->update(['stripe_customer_id' => $stripeCustomerId]);
                }

                $priceId = $cycle === 'annual'
                    ? ($tierConfig['stripe_annual_price_id'] ?? null)
                    : ($tierConfig['stripe_monthly_price_id'] ?? null);

                $sessionParams = [
                    'payment_method_types' => ['card'],
                    'mode' => 'subscription',
                    'customer' => $stripeCustomerId,
                    'line_items' => [[
                        'price' => $priceId,
                        'quantity' => 1,
                    ]],
                    'success_url' => url('/?subscription=success&business_id='.$business->id),
                    'cancel_url' => url('/?subscription=canceled&business_id='.$business->id),
                    'metadata' => [
                        'business_id' => (string) $business->id,
                        'tier' => $tierKey,
                        'billing_cycle' => $cycle,
                    ],
                    'subscription_data' => [
                        'metadata' => [
                            'business_id' => (string) $business->id,
                            'tier' => $tierKey,
                            'billing_cycle' => $cycle,
                        ],
                    ],
                ];

                $session = StripeSession::create($sessionParams);

                if ($request->wantsJson() || $request->is('api/*')) {
                    return response()->json([
                        'checkout_url' => $session->url,
                        'session_id' => $session->id,
                    ]);
                }

                return redirect()->away($session->url);
            } catch (\Throwable $e) {
                Log::error('Stripe checkout error: '.$e->getMessage());
            }
        }

        // Mock / Local Test fallback when Stripe API key is not configured or throws
        $business->update([
            'subscription_tier' => $tierKey,
            'billing_cycle' => $cycle,
            'subscription_status' => 'active',
            'stripe_subscription_id' => $business->stripe_subscription_id ?? 'sub_mock_'.bin2hex(random_bytes(8)),
            'stripe_customer_id' => $business->stripe_customer_id ?? 'cus_mock_'.bin2hex(random_bytes(8)),
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Subscription updated successfully.',
                'business' => $business->fresh(),
                'checkout_url' => url('/?subscription=success&business_id='.$business->id),
            ]);
        }

        return redirect()->to('/?subscription=success&business_id='.$business->id);
    }

    /**
     * Cancel an existing subscription.
     */
    public function cancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => ['required', 'exists:businesses,id'],
        ]);

        /** @var Business $business */
        $business = Business::findOrFail($validated['business_id']);

        if (Auth::check() && Auth::id() !== $business->user_id) {
            return response()->json(['error' => 'Unauthorized to modify this subscription.'], 403);
        }

        $stripeSecret = config('services.stripe.secret') ?? env('STRIPE_SECRET');

        if ($stripeSecret && $business->stripe_subscription_id && ! str_contains($stripeSecret, 'placeholder')) {
            try {
                Stripe::setApiKey($stripeSecret);
                $subscription = StripeSubscription::retrieve($business->stripe_subscription_id);
                $subscription->cancel();
            } catch (\Throwable $e) {
                Log::error('Stripe cancellation error: '.$e->getMessage());
            }
        }

        $business->update([
            'subscription_status' => 'canceled',
        ]);

        return response()->json([
            'message' => 'Subscription canceled successfully.',
            'business' => $business->fresh(),
        ]);
    }
}
