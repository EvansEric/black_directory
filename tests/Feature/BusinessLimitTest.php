<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function limitedOwner(string $tier): User
{
    $user = User::factory()->create();

    if ($tier !== 'free') {
        Business::create([
            'is_verified' => true,
            'user_id' => $user->id, 'name' => 'Existing', 'category' => 'Retail & Fashion', 'location' => 'Atlanta, GA',
            'description' => 'x', 'subscription_tier' => $tier, 'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
        ]);
    }

    return $user;
}

function submitListing(User $user, string $name)
{
    return Livewire::actingAs($user)->test('add-business-modal')
        ->set('name', $name)
        ->set('category', 'Retail & Fashion')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'x')
        ->call('save');
}

it('allows only one business on the free plan', function () {
    $user = limitedOwner('free');

    submitListing($user, 'First')->assertHasNoErrors();
    submitListing($user, 'Second')->assertHasErrors('name');

    expect($user->businesses()->count())->toBe(1);
});

it('allows only one business on the standard plan', function () {
    $user = limitedOwner('standard');

    submitListing($user, 'Second')->assertHasErrors('name');

    expect($user->businesses()->count())->toBe(1);
});

it('allows up to five businesses on the pro plan', function () {
    $user = limitedOwner('pro');

    foreach (range(2, 5) as $i) {
        submitListing($user, "Shop {$i}")->assertHasNoErrors();
    }
    submitListing($user, 'Shop 6')->assertHasErrors('name');

    expect($user->businesses()->count())->toBe(5);
});

it('still lets owners edit their only business at the limit', function () {
    $user = limitedOwner('standard');
    $business = $user->businesses()->first();

    Livewire::actingAs($user)->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->set('city', 'Decatur')
        ->set('state', 'GA')
        ->call('save')
        ->assertHasNoErrors();
});

it('offers free owners Standard features and editing without promising another listing', function () {
    $user = limitedOwner('free');
    submitListing($user, 'First')->assertHasNoErrors();
    $business = $user->businesses()->firstOrFail();

    $component = submitListing($user, 'Another')
        ->assertHasErrors('name')
        ->assertSeeHtml('data-form-locked')
        ->assertSeeHtml('data-form-lock-overlay')
        ->assertSee('Standard adds features to your existing listing, but still allows only 1 listing.')
        ->assertSee('Upgrade to Standard for more features')
        ->assertSee('Edit existing listing')
        ->assertDontSee('Upgrade to Pro')
        ->assertSeeHtml("tier: 'standard', businessId: {$business->id}");

    $component->call('editBusiness', $business->id)
        ->assertSet('editingBusinessId', $business->id)
        ->assertDontSeeHtml('data-form-locked')
        ->set('city', 'Decatur')
        ->call('save')
        ->assertHasNoErrors();

    expect($business->fresh()->city)->toBe('Decatur')
        ->and($user->businesses()->count())->toBe(1);
});

it('offers Pro to Standard owners at the listing limit', function () {
    $user = limitedOwner('standard');

    submitListing($user, 'Another')
        ->assertHasErrors('name')
        ->assertSee('allows only 1 business listing. Upgrade to Pro to list up to 5 businesses.')
        ->assertSee('Upgrade to Pro to unlock');
});

it('keeps the form locked after a free listing is upgraded to Standard', function () {
    $user = limitedOwner('free');
    submitListing($user, 'First')->assertHasNoErrors();
    $business = $user->businesses()->firstOrFail();
    $business->update([
        'subscription_tier' => 'standard',
        'subscription_status' => 'active',
    ]);

    submitListing($user, 'Another')
        ->assertHasErrors('name')
        ->assertSeeHtml('data-form-locked')
        ->assertSee('Upgrade to Pro to unlock');

    expect($user->businesses()->count())->toBe(1);
});

it('selects Standard when requested for a previously paid but expired listing', function () {
    $user = limitedOwner('pro');
    $business = $user->businesses()->firstOrFail();
    $business->update(['subscription_status' => 'canceled', 'subscription_ends_at' => now()->subDay()]);

    Livewire::actingAs($user)->test('subscription-modal')
        ->call('openSubscriptionModal', $business->id, 'standard')
        ->assertSet('selectedTier', 'standard');
});

it('disables the form inputs until the owner upgrades', function () {
    $atLimit = limitedOwner('standard');
    $html = Livewire::actingAs($atLimit)->test('add-business-modal')->html();
    expect($html)->toMatch('/<fieldset\\s+disabled[^>]*data-form-fields/');

    $html = Livewire::actingAs(limitedOwner('free'))->test('add-business-modal')->html();
    expect($html)->not->toMatch('/<fieldset\\s+disabled[^>]*data-form-fields/');

    $pro = limitedOwner('pro');
    $html = Livewire::actingAs($pro)->test('add-business-modal')->html();
    expect($html)->not->toMatch('/<fieldset\\s+disabled[^>]*data-form-fields/');
});

it('keeps inputs enabled when editing the only business', function () {
    $user = limitedOwner('standard');

    $html = Livewire::actingAs($user)->test('add-business-modal')
        ->call('editBusiness', $user->businesses()->first()->id)
        ->html();

    expect($html)->not->toMatch('/<fieldset\\s+disabled[^>]*data-form-fields/');
});
