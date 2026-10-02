<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends CashierWebhookController
{
    protected function handleCustomerSubscriptionCreated(array $payload)
    {
        $response = parent::handleCustomerSubscriptionCreated($payload);
        $this->syncBusiness($payload['data']['object']);

        return $response;
    }

    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);
        $this->syncBusiness($payload['data']['object']);

        return $response;
    }

    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        $response = parent::handleCustomerSubscriptionDeleted($payload);

        if ($business = $this->getUserByStripeId($payload['data']['object']['customer'] ?? null)) {
            $business->update([
                'subscription_status' => 'canceled',
                'subscription_tier' => 'free',
            ]);

            Log::info("Canceled subscription for business #{$business->id}");
        }

        return $response;
    }

    protected function handleInvoicePaymentFailed(array $payload)
    {
        if ($business = $this->getUserByStripeId($payload['data']['object']['customer'] ?? null)) {
            $business->update(['subscription_status' => 'past_due']);

            Log::warning("Payment failed for business #{$business->id}. Set status to past_due.");
        }

        return $this->successMethod();
    }

    protected function handleCheckoutSessionCompleted(array $payload)
    {
        $object = $payload['data']['object'];

        if (($object['mode'] ?? null) === 'subscription'
            && ($business = $this->getUserByStripeId($object['customer'] ?? null))) {
            $business->update(['subscription_status' => 'active']);
        }

        return $this->successMethod();
    }

    /**
     * Mirror the Stripe subscription onto the business's tier columns.
     */
    protected function syncBusiness(array $subscription): void
    {
        /** @var Business|null $business */
        $business = $this->getUserByStripeId($subscription['customer'] ?? null);

        if (! $business) {
            return;
        }

        $item = $subscription['items']['data'][0] ?? [];
        [$tier, $cycle] = $this->resolvePlan($item['price']['id'] ?? null)
            ?? [$subscription['metadata']['tier'] ?? null, $subscription['metadata']['billing_cycle'] ?? null];

        $data = ['subscription_status' => $subscription['status'] ?? 'active'];

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
     * @return array{0: string, 1: string}|null
     */
    protected function resolvePlan(?string $priceId): ?array
    {
        if (blank($priceId)) {
            return null;
        }

        foreach (config('services.stripe.prices', []) as $tier => $cycles) {
            foreach ($cycles as $cycle => $id) {
                if ($id === $priceId) {
                    return [$tier, $cycle];
                }
            }
        }

        return null;
    }
}
