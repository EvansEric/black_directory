<?php

use App\Models\Business;
use App\Models\User;
use App\Services\BusinessSubscriptionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('starts a stripe checkout for a user with no listing', function () {
    $user = User::factory()->create();

    $this->mock(BusinessSubscriptionManager::class)
        ->shouldReceive('createAccountCheckout')
        ->once()
        ->withArgs(fn (User $u, string $tier, string $cycle) => $u->is($user) && $tier === 'standard' && $cycle === 'monthly')
        ->andReturn(['url' => 'https://checkout.stripe.test/session', 'id' => 'cs_test_1']);

    Livewire::actingAs($user)->test('subscription-modal')
        ->call('selectTier', 'standard')
        ->call('checkout')
        ->assertRedirect('https://checkout.stripe.test/session')
        ->assertNotDispatched('open-add-modal');
});

it('sends a no-listing user to the add form for the free plan', function () {
    Livewire::actingAs(User::factory()->create())->test('subscription-modal')
        ->call('selectTier', 'free')
        ->call('checkout')
        ->assertDispatched('open-add-modal')
        ->assertNoRedirect();
});

it('does not charge twice while a paid plan is waiting for a listing', function () {
    $user = User::factory()->create();
    $user->forceFill(['pending_stripe_session_id' => 'cs_test_paid'])->save();

    $this->mock(BusinessSubscriptionManager::class)->shouldNotReceive('createAccountCheckout');

    Livewire::actingAs($user)->test('subscription-modal')
        ->call('selectTier', 'standard')
        ->call('checkout')
        ->assertDispatched('open-add-modal');
});

it('claims a pending paid plan when the first listing is created', function () {
    $user = User::factory()->create();

    $this->mock(BusinessSubscriptionManager::class)
        ->shouldReceive('claimPendingPlan')
        ->once()
        ->withArgs(fn (User $u, Business $b) => $u->is($user));

    Livewire::actingAs($user)->test('add-business-modal')
        ->set('name', 'First Shop')
        ->set('category', 'Retail & Fashion')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'Hello')
        ->call('save')
        ->assertHasNoErrors();
});

it('ignores a claim when nothing is pending', function () {
    $user = User::factory()->create();
    $business = Business::create([
        'user_id' => $user->id, 'name' => 'Shop', 'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA', 'description' => 'x',
    ]);

    expect(app(BusinessSubscriptionManager::class)->claimPendingPlan($user, $business))->toBeFalse();
});

it('requires login for the account checkout return page', function () {
    $this->get(route('subscriptions.account-success'))->assertForbidden();
});
