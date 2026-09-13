<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook as StripeWebhook;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhooks.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret') ?? env('STRIPE_WEBHOOK_SECRET');

        $eventData = null;

        if ($webhookSecret && $sigHeader) {
            try {
                $event = StripeWebhook::constructEvent($payload, $sigHeader, $webhookSecret);
                $eventData = json_decode(json_encode($event), true);
            } catch (\Throwable $e) {
                Log::error('Stripe Webhook Signature Verification Failed: '.$e->getMessage());

                return response()->json(['error' => 'Invalid signature'], 400);
            }
        } else {
            $eventData = json_decode($payload, true) ?? $request->all();
        }

        if (! isset($eventData['type'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $type = $eventData['type'];
        $object = $eventData['data']['object'] ?? [];

        Log::info("Processing Stripe Webhook Event: {$type}", ['object_id' => $object['id'] ?? null]);

        switch ($type) {
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($object);
                break;

            case 'invoice.payment_failed':
                $this->handlePaymentFailed($object);
                break;

            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($object);
                break;

            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($object);
                break;

            default:
                Log::info("Unhandled Stripe event type: {$type}");
                break;
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle customer.subscription.created / customer.subscription.updated
     */
    protected function handleSubscriptionUpdated(array $object): void
    {
        $subscriptionId = $object['id'] ?? null;
        $customerId = $object['customer'] ?? null;
        $status = $object['status'] ?? 'active';
        $metadata = $object['metadata'] ?? [];
        $businessId = $metadata['business_id'] ?? null;
        $tier = $metadata['tier'] ?? null;
        $billingCycle = $metadata['billing_cycle'] ?? null;

        $query = Business::query();

        if ($businessId) {
            $query->where('id', $businessId);
        } elseif ($subscriptionId) {
            $query->where('stripe_subscription_id', $subscriptionId);
        } elseif ($customerId) {
            $query->where('stripe_customer_id', $customerId);
        } else {
            return;
        }

        $business = $query->first();

        if ($business) {
            $updateData = [
                'subscription_status' => $status,
                'stripe_subscription_id' => $subscriptionId ?? $business->stripe_subscription_id,
                'stripe_customer_id' => $customerId ?? $business->stripe_customer_id,
            ];

            if ($tier) {
                $updateData['subscription_tier'] = $tier;
            }

            if ($billingCycle) {
                $updateData['billing_cycle'] = $billingCycle;
            }

            if (isset($object['current_period_end'])) {
                $updateData['subscription_ends_at'] = date('Y-m-d H:i:s', $object['current_period_end']);
            }

            $business->update($updateData);

            Log::info("Updated business #{$business->id} subscription status to {$status}, tier: {$business->subscription_tier}");
        }
    }

    /**
     * Handle customer.subscription.deleted
     */
    protected function handleSubscriptionDeleted(array $object): void
    {
        $subscriptionId = $object['id'] ?? null;
        $customerId = $object['customer'] ?? null;
        $metadata = $object['metadata'] ?? [];
        $businessId = $metadata['business_id'] ?? null;

        $query = Business::query();

        if ($businessId) {
            $query->where('id', $businessId);
        } elseif ($subscriptionId) {
            $query->where('stripe_subscription_id', $subscriptionId);
        } elseif ($customerId) {
            $query->where('stripe_customer_id', $customerId);
        } else {
            return;
        }

        $business = $query->first();

        if ($business) {
            $business->update([
                'subscription_status' => 'canceled',
                'subscription_tier' => 'free',
            ]);

            Log::info("Canceled subscription for business #{$business->id}");
        }
    }

    /**
     * Handle invoice.payment_failed
     */
    protected function handlePaymentFailed(array $object): void
    {
        $subscriptionId = $object['subscription'] ?? null;
        $customerId = $object['customer'] ?? null;

        $business = Business::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->orWhere('stripe_customer_id', $customerId)
            ->first();

        if ($business) {
            $business->update([
                'subscription_status' => 'past_due',
            ]);

            Log::warning("Payment failed for business #{$business->id}. Set status to past_due.");
        }
    }

    /**
     * Handle invoice.payment_succeeded
     */
    protected function handlePaymentSucceeded(array $object): void
    {
        $subscriptionId = $object['subscription'] ?? null;
        $customerId = $object['customer'] ?? null;

        $business = Business::query()
            ->where('stripe_subscription_id', $subscriptionId)
            ->orWhere('stripe_customer_id', $customerId)
            ->first();

        if ($business) {
            $business->update([
                'subscription_status' => 'active',
            ]);

            Log::info("Payment succeeded for business #{$business->id}. Set status to active.");
        }
    }

    /**
     * Handle checkout.session.completed
     */
    protected function handleCheckoutCompleted(array $object): void
    {
        $metadata = $object['metadata'] ?? [];
        $businessId = $metadata['business_id'] ?? null;
        $tier = $metadata['tier'] ?? null;
        $billingCycle = $metadata['billing_cycle'] ?? null;
        $subscriptionId = $object['subscription'] ?? null;
        $customerId = $object['customer'] ?? null;

        if ($businessId) {
            $business = Business::find($businessId);

            if ($business) {
                $business->update([
                    'subscription_status' => 'active',
                    'subscription_tier' => $tier ?? $business->subscription_tier,
                    'billing_cycle' => $billingCycle ?? $business->billing_cycle,
                    'stripe_subscription_id' => $subscriptionId ?? $business->stripe_subscription_id,
                    'stripe_customer_id' => $customerId ?? $business->stripe_customer_id,
                ]);

                if ($customerId && $business->user) {
                    $business->user->update(['stripe_customer_id' => $customerId]);
                }

                Log::info("Completed checkout for business #{$business->id}. Tier: {$business->subscription_tier}");
            }
        }
    }
}
