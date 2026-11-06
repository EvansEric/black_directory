<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BusinessSubscriptionManager
{
    public function selectFreePlan(Business $business): void
    {
        $subscription = $business->subscription('default');

        if ($subscription && $subscription->valid()) {
            try {
                $subscription->cancel();
            } catch (\Throwable $exception) {
                Log::error('Stripe subscription cancellation error: '.$exception->getMessage(), [
                    'business_id' => $business->id,
                    'tier' => SubscriptionGating::TIER_FREE,
                ]);

                throw new HttpException(502, 'Stripe could not cancel the subscription. Please try again.', $exception);
            }

            // Keep the paid tier until the period the customer already paid for ends.
            $business->update([
                'subscription_status' => 'canceled',
                'subscription_ends_at' => $subscription->ends_at,
            ]);

            return;
        }
        $business->update([
            'subscription_tier' => SubscriptionGating::TIER_FREE,
            'subscription_status' => 'active',
            'billing_cycle' => null,
            'subscription_ends_at' => null,
        ]);
    }

    private function checkoutPriceId(string $tier, string $cycle): string
    {
        $priceId = config("services.stripe.prices.{$tier}.{$cycle}");
        $stripeSecret = config('cashier.secret');

        if (blank($stripeSecret) || str_ends_with((string) $stripeSecret, '...')) {
            abort(503, 'Stripe checkout is not configured. Add STRIPE_SECRET to your environment.');
        }

        if (! is_string($priceId) || blank($priceId) || str_ends_with($priceId, '...')) {
            abort(503, "Stripe price is not configured for the {$tier} {$cycle} plan.");
        }

        return $priceId;
    }

    /**
     * Checkout for a user who has no listing yet; the paid plan is attached to
     * their first listing once the session is confirmed.
     *
     * @return array{url: string, id: string}
     */
    public function createAccountCheckout(User $user, string $tier, string $cycle): array
    {
        $priceId = $this->checkoutPriceId($tier, $cycle);
        $metadata = ['user_id' => (string) $user->id, 'tier' => $tier, 'billing_cycle' => $cycle];

        try {
            $session = Cashier::stripe()->checkout->sessions->create([
                'mode' => 'subscription',
                'line_items' => [['price' => $priceId, 'quantity' => 1]],
                'customer_email' => $user->email,
                'client_reference_id' => (string) $user->id,
                'success_url' => route('subscriptions.account-success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url('/?subscription=canceled'),
                'metadata' => $metadata,
                'subscription_data' => ['metadata' => $metadata],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Stripe account checkout error: '.$exception->getMessage(), ['user_id' => $user->id, 'tier' => $tier]);

            throw new HttpException(502, 'Stripe could not start checkout. Please try again.', $exception);
        }

        return ['url' => $session->url, 'id' => $session->id];
    }

    /**
     * Confirm a paid account-level checkout, then attach it to the user's first
     * listing if they already have one (otherwise it waits for their first listing).
     */
    public function recordAccountCheckout(User $user, string $sessionId): bool
    {
        $session = $this->retrieveAccountSession($user, $sessionId);

        if ($session === null) {
            return false;
        }

        $user->forceFill([
            'pending_stripe_session_id' => $sessionId,
            'pending_plan_tier' => $session['tier'],
            'pending_billing_cycle' => $session['cycle'],
            'pending_plan_ends_at' => null,
        ])->save();

        $business = $user->businesses()->first();

        if ($business) {
            $this->claimPendingPlan($user, $business);
        }

        return true;
    }

    /**
     * Downgrade a paid plan that is still waiting for a listing: it keeps running
     * until the period already paid for ends, then lapses to Free.
     */
    public function cancelPendingPlan(User $user): bool
    {
        $session = filled($user->pending_stripe_session_id)
            ? $this->retrieveAccountSession($user, $user->pending_stripe_session_id)
            : null;

        if ($session === null) {
            return false;
        }

        try {
            $updated = Cashier::stripe()->subscriptions->update($session['subscription']['id'], ['cancel_at_period_end' => true])->toArray();
        } catch (\Throwable $exception) {
            Log::error('Stripe pending plan cancellation error: '.$exception->getMessage(), ['user_id' => $user->id]);

            throw new HttpException(502, 'Stripe could not cancel the subscription. Please try again.', $exception);
        }

        $periodEnd = $updated['current_period_end'] ?? $updated['items']['data'][0]['current_period_end'] ?? null;

        $user->forceFill(['pending_plan_ends_at' => is_int($periodEnd) ? Carbon::createFromTimestamp($periodEnd) : now()])->save();

        return true;
    }

    /**
     * Attach the user's paid-but-unassigned plan to a listing.
     */
    public function claimPendingPlan(User $user, Business $business): bool
    {
        $sessionId = $user->pending_stripe_session_id;

        if (blank($sessionId)) {
            return false;
        }

        $session = $this->retrieveAccountSession($user, $sessionId);

        if ($session === null) {
            return false;
        }

        $subscription = $session['subscription'];
        $business->forceFill(['stripe_id' => $session['customer']])->save();

        $subscription['metadata'] = array_filter([
            'tier' => $session['metadata']['tier'] ?? null,
            'billing_cycle' => $session['metadata']['billing_cycle'] ?? null,
        ]) + ($subscription['metadata'] ?? []);

        $this->recordCashierSubscription($business, $subscription);
        $this->syncFromStripeSubscription($business, $subscription);

        $user->forceFill(['pending_stripe_session_id' => null, 'pending_plan_tier' => null, 'pending_billing_cycle' => null, 'pending_plan_ends_at' => null])->save();

        return true;
    }

    /**
     * @return array{customer: string, subscription: array<string, mixed>, tier: string|null, cycle: string|null}|null
     */
    private function retrieveAccountSession(User $user, string $sessionId): ?array
    {
        try {
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId, ['expand' => ['subscription']])->toArray();
        } catch (\Throwable $exception) {
            Log::error('Stripe account session lookup failed: '.$exception->getMessage(), ['user_id' => $user->id]);

            return null;
        }

        $subscription = $session['subscription'] ?? null;
        $customer = $session['customer'] ?? null;

        if (($session['metadata']['user_id'] ?? null) !== (string) $user->id
            || ($session['mode'] ?? null) !== 'subscription'
            || ! is_array($subscription)
            || ! is_string($customer)) {
            return null;
        }

        return [
            'customer' => $customer,
            'subscription' => $subscription,
            'tier' => $session['metadata']['tier'] ?? null,
            'cycle' => $session['metadata']['billing_cycle'] ?? null,
        ];
    }

    /**
     * @return array{url: string, id: string}
     */
    public function createCheckout(Business $business, string $tier, string $cycle): array
    {
        $priceId = $this->checkoutPriceId($tier, $cycle);

        $metadata = [
            'business_id' => (string) $business->id,
            'tier' => $tier,
            'billing_cycle' => $cycle,
        ];

        try {
            $checkout = $business->newSubscription('default', $priceId)
                ->withMetadata($metadata)
                ->checkout([
                    'success_url' => route('subscriptions.success', ['business' => $business->id]).'?session_id={CHECKOUT_SESSION_ID}',
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

    /**
     * Mirror a Stripe subscription object onto the business's tier columns.
     *
     * @param  array<string, mixed>  $subscription
     */
    public function syncFromStripeSubscription(Business $business, array $subscription): void
    {
        $item = $subscription['items']['data'][0] ?? [];
        [$tier, $cycle] = $this->resolvePlan($item['price']['id'] ?? null)
            ?? [$subscription['metadata']['tier'] ?? null, $subscription['metadata']['billing_cycle'] ?? null];

        $data = ['subscription_status' => ($subscription['cancel_at_period_end'] ?? false) ? 'canceled' : ($subscription['status'] ?? 'active')];

        if ($tier) {
            $data['subscription_tier'] = $tier;
        }

        if ($cycle) {
            $data['billing_cycle'] = $cycle;
        }

        $periodEnd = $subscription['current_period_end'] ?? $item['current_period_end'] ?? null;
        if ($periodEnd) {
            $data['subscription_ends_at'] = date('Y-m-d H:i:s', $periodEnd);
        }

        $business->update($data);

        Log::info("Updated business #{$business->id} subscription to {$data['subscription_status']}, tier: {$business->subscription_tier}");
    }

    /**
     * Confirm a completed Checkout Session with Stripe and sync the plan, so access
     * does not depend on the webhook arriving first.
     */
    public function syncFromCheckoutSession(Business $business, string $sessionId): bool
    {
        try {
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId, [
                'expand' => ['subscription'],
            ])->toArray();
        } catch (\Throwable $exception) {
            Log::error('Stripe checkout session lookup failed: '.$exception->getMessage(), ['business_id' => $business->id]);

            return false;
        }

        $subscription = $session['subscription'] ?? null;

        if (($session['metadata']['business_id'] ?? null) !== (string) $business->id
            || ($session['mode'] ?? null) !== 'subscription'
            || ! is_array($subscription)) {
            return false;
        }

        $subscription['metadata'] = array_filter([
            'tier' => $session['metadata']['tier'] ?? null,
            'billing_cycle' => $session['metadata']['billing_cycle'] ?? null,
        ]) + ($subscription['metadata'] ?? []);

        $this->recordCashierSubscription($business, $subscription);
        $this->syncFromStripeSubscription($business, $subscription);

        return true;
    }

    /**
     * Cashier normally records this from the webhook; do it here too so the
     * checkout return path leaves the business with a cancellable subscription.
     *
     * @param  array<string, mixed>  $subscription
     */
    public function recordCashierSubscription(Business $business, array $subscription): void
    {
        $item = $subscription['items']['data'][0] ?? [];
        $priceId = $item['price']['id'] ?? null;

        $record = Subscription::query()->updateOrCreate(
            ['stripe_id' => $subscription['id']],
            [
                'business_id' => $business->getKey(),
                'type' => 'default',
                'stripe_status' => $subscription['status'] ?? 'active',
                'stripe_price' => $priceId,
                'quantity' => $item['quantity'] ?? 1,
                'ends_at' => null,
            ],
        );

        if (isset($item['id'])) {
            $record->items()->updateOrCreate(
                ['stripe_id' => $item['id']],
                [
                    'stripe_product' => $item['price']['product'] ?? null,
                    'stripe_price' => $priceId,
                    'quantity' => $item['quantity'] ?? 1,
                ],
            );
        }
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function resolvePlan(?string $priceId): ?array
    {
        if (blank($priceId)) {
            return null;
        }

        $matches = [];

        foreach (config('services.stripe.prices', []) as $tier => $cycles) {
            foreach ($cycles as $cycle => $id) {
                if ($id === $priceId) {
                    $matches[] = [$tier, $cycle];
                }
            }
        }

        // A price shared by several plans cannot identify one; callers fall back to checkout metadata.
        if (count($matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    public function cancel(Business $business): Business
    {
        $subscription = $business->subscription('default');

        if ($subscription && $subscription->valid() && filled(config('cashier.secret'))) {
            try {
                $subscription->cancel();
            } catch (\Throwable $exception) {
                Log::error('Stripe cancellation error: '.$exception->getMessage(), [
                    'business_id' => $business->id,
                ]);

                throw new HttpException(502, 'Stripe could not cancel the subscription. Please try again.', $exception);
            }

            $business->update(['subscription_ends_at' => $subscription->ends_at]);
        }

        $business->update(['subscription_status' => 'canceled']);

        return $business->refresh();
    }
}
