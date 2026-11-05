<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\BusinessSubscriptionManager;
use App\Services\SubscriptionGating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Get pricing tiers metadata.
     */
    public function pricing(): JsonResponse
    {
        return response()->json([
            'tiers' => array_intersect_key(SubscriptionGating::TIERS, array_flip([
                SubscriptionGating::TIER_FREE,
                SubscriptionGating::TIER_STANDARD,
                SubscriptionGating::TIER_PRO,
            ])),
        ]);
    }

    /**
     * Handle creation of a Cashier Checkout Session for subscription.
     */
    public function checkout(Request $request, BusinessSubscriptionManager $subscriptions): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'business_id' => ['required', 'exists:businesses,id'],
            'tier' => ['required', 'string', 'in:free,standard,pro'],
            'billing_cycle' => ['required', 'string', 'in:monthly,annual'],
        ]);

        /** @var Business $business */
        $business = Business::findOrFail($validated['business_id']);

        abort_unless($request->user()?->getKey() === $business->user_id, 403, 'Unauthorized to manage subscription for this listing.');

        $tierKey = $validated['tier'];
        $cycle = $validated['billing_cycle'];

        if ($tierKey === SubscriptionGating::TIER_FREE) {
            $subscriptions->selectFreePlan($business);

            return response()->json([
                'message' => 'Free plan selected successfully.',
                'tier' => SubscriptionGating::TIER_FREE,
            ]);
        }

        $checkout = $subscriptions->createCheckout($business, $tierKey, $cycle);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'checkout_url' => $checkout['url'],
                'session_id' => $checkout['id'],
            ]);
        }

        return redirect()->away($checkout['url'], 303);
    }

    /**
     * Landing page after Stripe Checkout: confirm the session and sync the plan.
     */
    public function success(Request $request, Business $business, BusinessSubscriptionManager $subscriptions): RedirectResponse
    {
        abort_unless($request->user()?->getKey() === $business->user_id, 403);

        $sessionId = $request->query('session_id');

        if (is_string($sessionId) && $subscriptions->syncFromCheckoutSession($business, $sessionId)) {
            return redirect('/')->with('status', 'Your plan has been upgraded.');
        }

        return redirect('/')->with('status', 'Payment received. Your plan will update shortly.');
    }

    /**
     * Landing page after Stripe Checkout for a user who had no listing yet.
     */
    public function accountSuccess(Request $request, BusinessSubscriptionManager $subscriptions): RedirectResponse
    {
        $user = $request->user();
        $sessionId = $request->query('session_id');

        abort_unless($user !== null, 403);

        if (is_string($sessionId) && $subscriptions->recordAccountCheckout($user, $sessionId)) {
            $message = $user->businesses()->exists()
                ? 'Your plan has been upgraded.'
                : 'Payment received! Create your business listing and your plan will be applied to it.';

            return redirect('/')->with('status', $message);
        }

        return redirect('/')->with('status', 'Payment received. Your plan will update shortly.');
    }

    /**
     * Cancel an existing subscription.
     */
    public function cancel(Request $request, BusinessSubscriptionManager $subscriptions): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => ['required', 'exists:businesses,id'],
        ]);

        /** @var Business $business */
        $business = Business::findOrFail($validated['business_id']);

        abort_unless($request->user()?->getKey() === $business->user_id, 403, 'Unauthorized to modify this subscription.');

        $business = $subscriptions->cancel($business);

        return response()->json([
            'message' => 'Subscription canceled successfully.',
            'business' => $business->fresh(),
        ]);
    }
}
