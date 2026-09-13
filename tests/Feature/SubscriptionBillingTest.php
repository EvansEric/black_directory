<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('models support camelCase subscription properties and snake_case attributes', function () {
    $user = User::factory()->create([
        'stripe_customer_id' => 'cus_test123',
    ]);

    expect($user->stripeCustomerId)->toBe('cus_test123');

    $business = Business::create([
        'user_id' => $user->id,
        'name' => 'Acme Supplies',
        'category' => 'Professional Services',
        'location' => 'Atlanta, GA',
        'description' => 'Great professional services.',
        'subscription_tier' => 'pro',
        'billing_cycle' => 'annual',
        'stripe_customer_id' => 'cus_test123',
        'stripe_subscription_id' => 'sub_test999',
        'subscription_status' => 'active',
    ]);

    expect($business->subscriptionTier)->toBe('pro')
        ->and($business->billingCycle)->toBe('annual')
        ->and($business->stripeCustomerId)->toBe('cus_test123')
        ->and($business->stripeSubscriptionId)->toBe('sub_test999')
        ->and($business->subscriptionStatus)->toBe('active');
});

test('feature gating correctly permits or gates features based on active tier', function () {
    $user = User::factory()->create();

    $freeBusiness = Business::create([
        'user_id' => $user->id,
        'name' => 'Free Shop',
        'category' => 'Retail & Fashion',
        'location' => 'Decatur, GA',
        'description' => 'Free tier business',
        'subscription_tier' => 'free',
        'subscription_status' => 'active',
    ]);

    $standardBusiness = Business::create([
        'user_id' => $user->id,
        'name' => 'Standard Shop',
        'category' => 'Retail & Fashion',
        'location' => 'Decatur, GA',
        'description' => 'Standard tier business',
        'subscription_tier' => 'standard',
        'subscription_status' => 'active',
    ]);

    $proBusiness = Business::create([
        'user_id' => $user->id,
        'name' => 'Pro Shop',
        'category' => 'Retail & Fashion',
        'location' => 'Decatur, GA',
        'description' => 'Pro tier business',
        'subscription_tier' => 'pro',
        'subscription_status' => 'active',
    ]);

    $spotlightBusiness = Business::create([
        'user_id' => $user->id,
        'name' => 'Spotlight Shop',
        'category' => 'Retail & Fashion',
        'location' => 'Decatur, GA',
        'description' => 'Spotlight tier business',
        'subscription_tier' => 'spotlight',
        'subscription_status' => 'active',
    ]);

    // Free Tier Gating Checks
    expect(canAccessLogo($freeBusiness))->toBeFalse();
    expect(canAccessCustomUrl($freeBusiness))->toBeFalse();
    expect(canAccessLeadForms($freeBusiness))->toBeFalse();
    expect(canAccessSpotlight($freeBusiness))->toBeFalse();

    // Standard Tier Gating Checks
    expect(canAccessLogo($standardBusiness))->toBeTrue();
    expect(canAccessCustomUrl($standardBusiness))->toBeTrue();
    expect(canAccessDescription($standardBusiness))->toBeTrue();
    expect(canAccessLeadForms($standardBusiness))->toBeFalse();
    expect(canAccessSpotlight($standardBusiness))->toBeFalse();

    // Pro Tier Gating Checks
    expect(canAccessLogo($proBusiness))->toBeTrue();
    expect(canAccessCustomUrl($proBusiness))->toBeTrue();
    expect(canAccessLeadForms($proBusiness))->toBeTrue();
    expect(canAccessSpotlight($proBusiness))->toBeFalse();

    // Spotlight Tier Gating Checks
    expect(canAccessLogo($spotlightBusiness))->toBeTrue();
    expect(canAccessCustomUrl($spotlightBusiness))->toBeTrue();
    expect(canAccessLeadForms($spotlightBusiness))->toBeTrue();
    expect(canAccessSpotlight($spotlightBusiness))->toBeTrue();
});

test('middleware gates endpoints based on feature entitlement', function () {
    $user = User::factory()->create();

    $standardBusiness = Business::create([
        'user_id' => $user->id,
        'name' => 'Standard Cafe',
        'category' => 'Dining & Food',
        'location' => 'Atlanta, GA',
        'description' => 'Good food',
        'subscription_tier' => 'standard',
        'subscription_status' => 'active',
    ]);

    $proBusiness = Business::create([
        'user_id' => $user->id,
        'name' => 'Pro Cafe',
        'category' => 'Dining & Food',
        'location' => 'Atlanta, GA',
        'description' => 'Great food',
        'subscription_tier' => 'pro',
        'subscription_status' => 'active',
    ]);

    // Lead submission to standard business should fail with 403
    $this->postJson("/listings/{$standardBusiness->id}/leads", [
        'name' => 'John Client',
        'email' => 'john@example.com',
        'message' => 'Need quote',
    ])->assertForbidden();

    // Lead submission to pro business should succeed with 201
    $this->postJson("/listings/{$proBusiness->id}/leads", [
        'name' => 'John Client',
        'email' => 'john@example.com',
        'message' => 'Need quote',
    ])->assertCreated();

    $this->assertDatabaseHas('leads', [
        'business_id' => $proBusiness->id,
        'email' => 'john@example.com',
    ]);
});

test('subscription checkout and cancellation routes work', function () {
    $user = User::factory()->create();
    $business = Business::create([
        'user_id' => $user->id,
        'name' => 'Test Business',
        'category' => 'Dining & Food',
        'location' => 'Atlanta, GA',
        'description' => 'Test desc',
        'subscription_tier' => 'free',
    ]);

    $this->actingAs($user);

    // Pricing endpoint
    $this->getJson('/subscriptions/pricing')
        ->assertOk()
        ->assertJsonStructure(['tiers']);

    // Checkout request
    $response = $this->postJson('/subscriptions/checkout', [
        'business_id' => $business->id,
        'tier' => 'pro',
        'billing_cycle' => 'monthly',
    ]);

    $response->assertOk();
    expect($business->fresh()->subscription_tier)->toBe('pro')
        ->and($business->fresh()->subscription_status)->toBe('active');

    // Cancel request
    $cancelResponse = $this->postJson('/subscriptions/cancel', [
        'business_id' => $business->id,
    ]);

    $cancelResponse->assertOk();
    expect($business->fresh()->subscription_status)->toBe('canceled');
});

test('stripe webhook handles subscription updates, deletions, and payment failures', function () {
    $user = User::factory()->create();
    $business = Business::create([
        'user_id' => $user->id,
        'name' => 'Webhook Test Business',
        'category' => 'Dining & Food',
        'location' => 'Atlanta, GA',
        'description' => 'Test desc',
        'subscription_tier' => 'standard',
        'stripe_subscription_id' => 'sub_wh_123',
        'stripe_customer_id' => 'cus_wh_123',
        'subscription_status' => 'active',
    ]);

    // 1. customer.subscription.updated
    $this->postJson('/api/webhooks/stripe', [
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_wh_123',
                'customer' => 'cus_wh_123',
                'status' => 'active',
                'metadata' => [
                    'business_id' => $business->id,
                    'tier' => 'spotlight',
                    'billing_cycle' => 'annual',
                ],
            ],
        ],
    ])->assertOk();

    expect($business->fresh()->subscription_tier)->toBe('spotlight')
        ->and($business->fresh()->billing_cycle)->toBe('annual');

    // 2. invoice.payment_failed
    $this->postJson('/api/webhooks/stripe', [
        'type' => 'invoice.payment_failed',
        'data' => [
            'object' => [
                'subscription' => 'sub_wh_123',
                'customer' => 'cus_wh_123',
            ],
        ],
    ])->assertOk();

    expect($business->fresh()->subscription_status)->toBe('past_due');

    // 3. customer.subscription.deleted
    $this->postJson('/api/webhooks/stripe', [
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'id' => 'sub_wh_123',
                'customer' => 'cus_wh_123',
            ],
        ],
    ])->assertOk();

    expect($business->fresh()->subscription_status)->toBe('canceled')
        ->and($business->fresh()->subscription_tier)->toBe('free');
});

test('listings search and directory queries order results by tier priority', function () {
    $user = User::factory()->create();

    $free = Business::create([
        'user_id' => $user->id,
        'name' => 'A Free Business',
        'category' => 'Tech',
        'location' => 'Atlanta, GA',
        'description' => 'Free',
        'subscription_tier' => 'free',
        'subscription_status' => 'active',
        'rating' => 5.0,
    ]);

    $standard = Business::create([
        'user_id' => $user->id,
        'name' => 'B Standard Business',
        'category' => 'Tech',
        'location' => 'Atlanta, GA',
        'description' => 'Standard',
        'subscription_tier' => 'standard',
        'subscription_status' => 'active',
        'rating' => 5.0,
    ]);

    $pro = Business::create([
        'user_id' => $user->id,
        'name' => 'C Pro Business',
        'category' => 'Tech',
        'location' => 'Atlanta, GA',
        'description' => 'Pro',
        'subscription_tier' => 'pro',
        'subscription_status' => 'active',
        'rating' => 5.0,
    ]);

    $spotlight = Business::create([
        'user_id' => $user->id,
        'name' => 'D Spotlight Business',
        'category' => 'Tech',
        'location' => 'Atlanta, GA',
        'description' => 'Spotlight',
        'subscription_tier' => 'spotlight',
        'subscription_status' => 'active',
        'rating' => 5.0,
    ]);

    $ordered = Business::query()->orderedByTier()->get();

    // Hierarchy check: Spotlight > Pro/Featured > Standard > Free
    expect($ordered->pluck('id')->all())->toBe([
        $spotlight->id,
        $pro->id,
        $standard->id,
        $free->id,
    ]);
});
