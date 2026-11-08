<?php

use App\Models\Business;
use App\Models\User;
use App\Services\BusinessSubscriptionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('asks guests to sign in or register when choosing a paid plan', function () {
    Livewire::test('subscription-modal')
        ->call('openSubscriptionModal', null, 'standard')
        ->assertDispatched('close-subscription-modal')
        ->assertDispatched('open-auth-modal')
        ->assertNotDispatched('show-subscription-modal');

    expect(session('pending_plan_selection'))->toBe(['tier' => 'standard', 'cycle' => 'monthly']);
});

it('requires an account before checkout and preserves the billing choice', function () {
    $this->mock(BusinessSubscriptionManager::class)
        ->shouldNotReceive('createAccountCheckout');

    Livewire::test('subscription-modal')
        ->call('selectTier', 'pro')
        ->call('selectCycle', 'annual')
        ->call('checkout')
        ->assertDispatched('close-subscription-modal')
        ->assertDispatched('open-auth-modal')
        ->assertNoRedirect();

    expect(session('pending_plan_selection'))->toBe(['tier' => 'pro', 'cycle' => 'annual']);
});

it('resumes the selected plan after registering without starting checkout', function () {
    session()->put('pending_plan_selection', ['tier' => 'pro', 'cycle' => 'annual']);
    $this->mock(BusinessSubscriptionManager::class)->shouldNotReceive('createAccountCheckout');

    Livewire::test('auth-modal')
        ->set('registerName', 'New User')
        ->set('registerEmail', 'new@example.com')
        ->set('registerPassword', 'password123')
        ->set('registerPassword_confirmation', 'password123')
        ->call('register')
        ->assertRedirect(route('pricing'));

    $this->assertAuthenticated();
    Livewire::test('pages::pricing')
        ->assertSet('resumePlan', ['tier' => 'pro', 'cycle' => 'annual'])
        ->assertSee('open-subscription-modal', false);
    expect(session()->has('pending_plan_selection'))->toBeFalse();
});

it('resumes the selected plan after signing in', function () {
    $user = User::factory()->create(['password' => Hash::make('password123')]);
    session()->put('pending_plan_selection', ['tier' => 'standard', 'cycle' => 'monthly']);

    Livewire::test('auth-modal')
        ->set('loginEmail', $user->email)
        ->set('loginPassword', 'password123')
        ->call('login')
        ->assertRedirect(route('pricing'));

    $this->assertAuthenticatedAs($user);
    Livewire::test('pages::pricing')->assertSet('resumePlan', ['tier' => 'standard', 'cycle' => 'monthly']);
});

it('clears the selected plan when the authentication prompt is dismissed', function () {
    session()->put('pending_plan_selection', ['tier' => 'standard', 'cycle' => 'monthly']);

    Livewire::test('auth-modal')->call('cancel');

    expect(session()->has('pending_plan_selection'))->toBeFalse();
});

it('restores the selected plan and billing cycle for an authenticated user', function () {
    Livewire::actingAs(User::factory()->create())->test('subscription-modal')
        ->call('openSubscriptionModal', null, 'pro', 'annual')
        ->assertSet('selectedTier', 'pro')
        ->assertSet('billingCycle', 'annual')
        ->assertDispatched('show-subscription-modal');
});

it('rejects unauthenticated direct checkout requests', function () {
    $business = Business::create([
        'user_id' => User::factory()->create()->id, 'name' => 'Shop',
        'category' => 'Retail & Fashion', 'location' => 'Atlanta, GA', 'description' => 'x',
    ]);

    $this->mock(BusinessSubscriptionManager::class)->shouldNotReceive('createCheckout');

    $this->postJson(route('subscriptions.checkout'), [
        'business_id' => $business->id,
        'tier' => 'standard',
        'billing_cycle' => 'monthly',
    ])->assertForbidden();
});

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
        'is_verified' => true,
        'user_id' => $user->id, 'name' => 'Shop', 'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA', 'description' => 'x',
    ]);

    expect(app(BusinessSubscriptionManager::class)->claimPendingPlan($user, $business))->toBeFalse();
});

it('requires login for the account checkout return page', function () {
    $this->get(route('subscriptions.account-success'))->assertForbidden();
});
