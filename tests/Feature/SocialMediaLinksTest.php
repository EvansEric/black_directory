<?php

use App\Models\Business;
use App\Models\User;
use App\Services\SubscriptionGating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('requires verification before allowing Standard social media links', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'pending_stripe_session_id' => 'cs_test_paid',
        'pending_plan_tier' => 'standard',
    ])->save();

    $component = Livewire::actingAs($user)->test('add-business-modal')
        ->set('name', 'Social Shop')
        ->set('category', 'Retail & Fashion')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'A local shop.')
        ->set('facebookUrl', 'https://facebook.com/socialshop')
        ->set('instagramUrl', 'https://instagram.com/socialshop')
        ->set('xUrl', 'https://x.com/socialshop')
        ->set('tiktokUrl', 'https://tiktok.com/@socialshop')
        ->call('save')
        ->assertHasNoErrors();

    $business = Business::query()->where('name', 'Social Shop')->firstOrFail();
    expect($business->facebook_url)->toBeNull()
        ->and($business->instagram_url)->toBeNull()
        ->and($business->x_url)->toBeNull()
        ->and($business->tiktok_url)->toBeNull();

    $business->update([
        'is_verified' => true,
        'subscription_tier' => 'standard',
        'subscription_status' => 'active',
        'instagram_url' => 'https://instagram.com/socialshop',
    ]);
    expect(SubscriptionGating::verifyFeature($business, 'social_links'))->toBeTrue();
    $component->call('editBusiness', $business->id)
        ->assertSet('instagramUrl', 'https://instagram.com/socialshop')
        ->set('instagramUrl', 'https://instagram.com/updatedshop')
        ->call('save')
        ->assertHasNoErrors();

    expect($business->fresh()->instagram_url)->toBe('https://instagram.com/updatedshop');
});

it('does not save social links for free listings', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('add-business-modal')
        ->assertSee('Social media links are a Standard-tier feature')
        ->set('name', 'Free Shop')
        ->set('category', 'Retail & Fashion')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'A free local shop.')
        ->set('facebookUrl', 'https://facebook.com/freeshop')
        ->call('save')
        ->assertHasNoErrors();

    $business = Business::query()->where('name', 'Free Shop')->firstOrFail();
    expect($business->facebook_url)->toBeNull()
        ->and(SubscriptionGating::verifyFeature($business, 'social_links'))->toBeFalse();
});

it('shows social links on profiles only when the listing has Standard access', function () {
    $owner = User::factory()->create();
    $attributes = [
        'is_verified' => true,
        'user_id' => $owner->id,
        'name' => 'Social Profile',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'A local shop.',
        'facebook_url' => 'https://facebook.com/socialprofile',
        'instagram_url' => 'https://instagram.com/socialprofile',
    ];
    $standard = Business::create([
        ...$attributes,
        'subscription_tier' => 'standard',
        'subscription_status' => 'active',
    ]);
    $free = Business::create([
        ...$attributes,
        'name' => 'Free Profile',
        'subscription_tier' => 'free',
    ]);

    Livewire::test('business-details-modal')
        ->call('open', $standard->id)
        ->assertSeeHtml('data-social-links')
        ->assertSeeHtml('href="https://facebook.com/socialprofile"')
        ->call('close')
        ->call('open', $free->id)
        ->assertDontSeeHtml('data-social-links')
        ->assertDontSeeHtml('https://facebook.com/socialprofile');
});

it('rejects social links that are not valid HTTP URLs', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'pending_stripe_session_id' => 'cs_test_paid',
        'pending_plan_tier' => 'standard',
    ])->save();

    Livewire::actingAs($user)->test('add-business-modal')
        ->set('name', 'Invalid Social Shop')
        ->set('category', 'Retail & Fashion')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'A local shop.')
        ->set('facebookUrl', 'javascript:alert(1)')
        ->call('save')
        ->assertHasErrors('facebookUrl');
});
