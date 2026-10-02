<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\SubscriptionGating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
     * Handle creation of a Cashier Checkout Session for subscription.
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

        $priceId = config("services.stripe.prices.{$tierKey}.{$cycle}");
        $stripeSecret = config('cashier.secret');

        if (blank($stripeSecret) || str_ends_with((string) $stripeSecret, '...')) {
            abort(503, 'Stripe checkout is not configured. Add STRIPE_SECRET to your environment.');
        }

        if (blank($priceId) || str_ends_with((string) $priceId, '...')) {
            abort(503, "Stripe price is not configured for the {$tierKey} {$cycle} plan.");
        }

        try {
            $metadata = [
                'business_id' => (string) $business->id,
                'tier' => $tierKey,
                'billing_cycle' => $cycle,
            ];

            $checkout = $business->newSubscription('default', $priceId)
                ->withMetadata($metadata)
                ->checkout([
                    'success_url' => url('/?subscription=success&business_id='.$business->id),
                    'cancel_url' => url('/?subscription=canceled&business_id='.$business->id),
                    'metadata' => $metadata,
                ]);

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'checkout_url' => $checkout->url,
                    'session_id' => $checkout->id,
                ]);
            }

            return $checkout->redirect();
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

        $subscription = $business->subscription('default');

        if ($subscription && $subscription->valid() && filled(config('cashier.secret'))) {
            try {
                $subscription->cancelNow();
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
