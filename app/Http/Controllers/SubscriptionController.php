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
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        if (! Auth::check() || Auth::id() !== $business->user_id) {
            abort(403, 'Unauthorized to manage subscription for this listing.');
        }

        $tierKey = $validated['tier'];
        $cycle = $validated['billing_cycle'];

        $stripeSecret = config('services.stripe.secret');
        $priceId = config("services.stripe.prices.{$tierKey}.{$cycle}");

        if (blank($stripeSecret) || str_ends_with((string) $stripeSecret, '...')) {
            abort(503, 'Stripe checkout is not configured. Add STRIPE_SECRET to your environment.');
        }

        if (blank($priceId) || str_ends_with((string) $priceId, '...')) {
            abort(503, "Stripe price is not configured for the {$tierKey} {$cycle} plan.");
        }

        try {
            Stripe::setApiKey($stripeSecret);

            $user = Auth::user();

            // Reuse the customer's Stripe identity across repeat checkouts.
            $stripeCustomerId = $business->stripe_customer_id ?? $user->stripe_customer_id;
            if (! $stripeCustomerId) {
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

            $session = StripeSession::create([
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
            ]);

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'checkout_url' => $session->url,
                    'session_id' => $session->id,
                ]);
            }

            return redirect()->away($session->url);
        } catch (\Throwable $e) {
            Log::error('Stripe checkout error: '.$e->getMessage(), [
                'business_id' => $business->id,
                'tier' => $tierKey,
                'billing_cycle' => $cycle,
            ]);

            throw new HttpException(502, 'Stripe could not start checkout. Please try again.');
        }
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
