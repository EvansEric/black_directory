<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BusinessSubscriptionManager
{
    public function selectFreePlan(Business $business): void
    {
        $subscription = $business->subscription('default');

        if ($subscription && $subscription->valid()) {
            try {
                $subscription->cancelNow();
            } catch (\Throwable $exception) {
                Log::error('Stripe subscription cancellation error: '.$exception->getMessage(), [
                    'business_id' => $business->id,
                    'tier' => SubscriptionGating::TIER_FREE,
                ]);

                throw new HttpException(502, 'Stripe could not cancel the subscription. Please try again.', $exception);
            }
        }

        $business->update([
            'subscription_tier' => SubscriptionGating::TIER_FREE,
            'subscription_status' => 'active',
            'billing_cycle' => null,
            'subscription_ends_at' => null,
        ]);
    }

    /**
     * @return array{url: string, id: string}
     */
    public function createCheckout(Business $business, string $tier, string $cycle): array
    {
        $priceId = config("services.stripe.prices.{$tier}.{$cycle}");
        $stripeSecret = config('cashier.secret');

        if (blank($stripeSecret) || str_ends_with((string) $stripeSecret, '...')) {
            abort(503, 'Stripe checkout is not configured. Add STRIPE_SECRET to your environment.');
        }

        if (blank($priceId) || str_ends_with((string) $priceId, '...')) {
            abort(503, "Stripe price is not configured for the {$tier} {$cycle} plan.");
        }

        $metadata = [
            'business_id' => (string) $business->id,
            'tier' => $tier,
            'billing_cycle' => $cycle,
        ];

        try {
            $checkout = $business->newSubscription('default', $priceId)
                ->withMetadata($metadata)
                ->checkout([
                    'success_url' => url('/?subscription=success&business_id='.$business->id),
                    'cancel_url' => url('/?subscription=canceled&business_id='.$business->id),
                    'metadata' => $metadata,
                    'payment_method_types' => ['card', 'link'],
                ]);

            $session = $checkout->toArray();
            $url = $session['url'] ?? null;
            $id = $session['id'] ?? null;

            if (! is_string($url) || ! is_string($id)) {
                throw new \UnexpectedValueException('Stripe returned an incomplete checkout session.');
            }

            return ['url' => $url, 'id' => $id];
        } catch (\Throwable $exception) {
            Log::error('Stripe checkout error: '.$exception->getMessage(), [
                'business_id' => $business->id,
                'tier' => $tier,
                'billing_cycle' => $cycle,
            ]);

            throw new HttpException(502, 'Stripe could not start checkout. Please try again.', $exception);
        }
    }

    public function cancel(Business $business): Business
    {
        $subscription = $business->subscription('default');

        if ($subscription && $subscription->valid() && filled(config('cashier.secret'))) {
            try {
                $subscription->cancelNow();
            } catch (\Throwable $exception) {
                Log::error('Stripe cancellation error: '.$exception->getMessage(), [
                    'business_id' => $business->id,
                ]);

                throw new HttpException(502, 'Stripe could not cancel the subscription. Please try again.', $exception);
            }
        }

        $business->update(['subscription_status' => 'canceled']);

        return $business->refresh();
    }
}
