<?php

use App\Models\Business;
use App\Models\User;
use App\Services\SubscriptionGating;

it('admin users bypass tier restrictions for canAccessLogo', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $freeBusiness = Business::create([
        'user_id' => $nonAdmin->id,
        'name' => 'Free Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'A free business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);
    $adminBusiness = Business::create([
        'user_id' => $admin->id,
        'name' => 'Admin Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'An admin business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);

    expect($freeBusiness->canAccessLogo())->toBeFalse();
    expect($adminBusiness->canAccessLogo())->toBeTrue();
});

it('admin users bypass tier restrictions for canAccessLeadForms', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $freeBusiness = Business::create([
        'user_id' => $nonAdmin->id,
        'name' => 'Free Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'A free business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);
    $adminBusiness = Business::create([
        'user_id' => $admin->id,
        'name' => 'Admin Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'An admin business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);

    expect($freeBusiness->canAccessLeadForms())->toBeFalse();
    expect($adminBusiness->canAccessLeadForms())->toBeTrue();
});

it('admin users bypass tier restrictions for canAccessSpotlight', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $freeBusiness = Business::create([
        'user_id' => $nonAdmin->id,
        'name' => 'Free Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'A free business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);
    $adminBusiness = Business::create([
        'user_id' => $admin->id,
        'name' => 'Admin Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'An admin business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);

    expect($freeBusiness->canAccessSpotlight())->toBeFalse();
    expect($adminBusiness->canAccessSpotlight())->toBeTrue();
});

it('admin users bypass tier restrictions for canAccessCustomUrl', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $freeBusiness = Business::create([
        'user_id' => $nonAdmin->id,
        'name' => 'Free Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'A free business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);
    $adminBusiness = Business::create([
        'user_id' => $admin->id,
        'name' => 'Admin Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'An admin business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);

    expect($freeBusiness->canAccessCustomUrl())->toBeFalse();
    expect($adminBusiness->canAccessCustomUrl())->toBeTrue();
});

it('admin users bypass tier restrictions for canAccessPrioritySearch', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $freeBusiness = Business::create([
        'user_id' => $nonAdmin->id,
        'name' => 'Free Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'A free business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);
    $adminBusiness = Business::create([
        'user_id' => $admin->id,
        'name' => 'Admin Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'An admin business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);

    expect($freeBusiness->canAccessPrioritySearch())->toBeFalse();
    expect($adminBusiness->canAccessPrioritySearch())->toBeTrue();
});

it('admin users bypass tier restrictions for canAccessDescription', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $freeBusiness = Business::create([
        'user_id' => $nonAdmin->id,
        'name' => 'Free Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'A free business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);
    $adminBusiness = Business::create([
        'user_id' => $admin->id,
        'name' => 'Admin Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'An admin business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);

    expect($freeBusiness->canAccessDescription())->toBeFalse();
    expect($adminBusiness->canAccessDescription())->toBeTrue();
});

it('admin users get unlimited business listings', function () {
    $nonAdmin = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    expect(SubscriptionGating::businessLimit($nonAdmin))->toBe(1); // Free tier limit
    expect(SubscriptionGating::businessLimit($admin))->toBeNull(); // Unlimited for admin
});

it('middleware allows admin users to bypass feature verification', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $nonAdmin = User::factory()->create(['is_admin' => false]);

    $freeBusiness = Business::create([
        'user_id' => $nonAdmin->id,
        'name' => 'Free Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'A free business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);
    $adminBusiness = Business::create([
        'user_id' => $admin->id,
        'name' => 'Admin Business',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'An admin business',
        'subscription_tier' => SubscriptionGating::TIER_FREE,
    ]);

    // Verify that admin's business passes feature verification
    expect(SubscriptionGating::verifyFeature($adminBusiness, 'lead_forms', $admin))->toBeTrue();
    expect(SubscriptionGating::verifyFeature($freeBusiness, 'lead_forms', $nonAdmin))->toBeFalse();
});

