<?php

use App\Models\Business;
use App\Models\User;
use App\Services\BusinessSubscriptionManager;
use App\Services\SubscriptionGating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function standardListing(User $owner): Business
{
    return Business::create([
        'user_id' => $owner->id, 'name' => 'Std Shop', 'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA', 'description' => 'x',
        'subscription_tier' => 'standard', 'billing_cycle' => 'monthly',
        'subscription_status' => 'active', 'subscription_ends_at' => now()->addMonth(),
    ]);
}

it('marks the owner’s current plan in the plan modal', function () {
    $owner = User::factory()->create();
    $business = standardListing($owner);

    Livewire::actingAs($owner)->test('subscription-modal')
        ->call('openSubscriptionModal', $business->id, 'standard')
        ->assertSeeHtml('data-current-plan-badge')
        ->assertSee('This is your current plan')
        ->call('selectTier', 'pro')
        ->assertDontSee('This is your current plan')
        ->call('selectTier', 'standard')
        ->call('selectCycle', 'annual')
        ->assertDontSee('This is your current plan');
});

it('does not start checkout for the plan the listing is already on', function () {
    $owner = User::factory()->create();
    $business = standardListing($owner);

    Livewire::actingAs($owner)->test('subscription-modal')
        ->call('openSubscriptionModal', $business->id, 'standard')
        ->call('checkout')
        ->assertNoRedirect();
});

it('marks the current plan on the pricing page', function () {
    $owner = User::factory()->create();
    standardListing($owner);

    Livewire::actingAs($owner)->test('pages::pricing')
        ->assertSee('Manage Standard (current)')
        ->assertSee('Choose Pro');
});

it('shows no current plan badge to guests on the pricing page', function () {
    Livewire::test('pages::pricing')->assertDontSeeHtml('data-current-plan-badge');
});

it('shows a paid plan that is waiting for a first listing', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'pending_stripe_session_id' => 'cs_test_paid',
        'pending_plan_tier' => 'standard',
        'pending_billing_cycle' => 'monthly',
    ])->save();

    Livewire::actingAs($user)->test('navigation')
        ->assertSee('Standard')
        ->assertSeeHtml('data-plan-pending');

    Livewire::actingAs($user)->test('pages::pricing')->assertSee('Manage Standard (current)');
    Livewire::actingAs($user)->test('subscription-modal')
        ->call('selectTier', 'standard')
        ->assertSeeHtml('data-current-plan-badge');
});

it('shows the time left on a paid plan before downgrading to free', function () {
    $owner = User::factory()->create();
    $business = standardListing($owner);

    Livewire::actingAs($owner)->test('subscription-modal')
        ->call('openSubscriptionModal', $business->id)
        ->assertDontSeeHtml('data-downgrade-notice')
        ->call('selectTier', 'free')
        ->assertSeeHtml('data-downgrade-notice')
        ->assertSee('Your Standard plan stays active for')
        ->call('selectTier', 'standard')
        ->assertDontSeeHtml('data-downgrade-notice');
});

it('does not show a downgrade notice for businesses on the free plan', function () {
    $owner = User::factory()->create();
    $business = Business::create([
        'user_id' => $owner->id, 'name' => 'Free Shop', 'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA', 'description' => 'x',
    ]);

    Livewire::actingAs($owner)->test('subscription-modal')
        ->call('openSubscriptionModal', $business->id)
        ->call('selectTier', 'free')
        ->assertDontSeeHtml('data-downgrade-notice');
});

it('redirects with the time left after downgrading to free', function () {
    $owner = User::factory()->create();
    $business = standardListing($owner);

    Livewire::actingAs($owner)->test('subscription-modal')
        ->call('openSubscriptionModal', $business->id)
        ->call('selectTier', 'free')
        ->call('checkout')
        ->assertRedirect('/');

    expect(session('status'))->toContain('Your Standard plan stays active for');
});

it('downgrades a paid plan that is waiting for a listing and shows the time left', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'pending_stripe_session_id' => 'cs_test_paid',
        'pending_plan_tier' => 'standard',
        'pending_billing_cycle' => 'monthly',
    ])->save();

    $this->mock(BusinessSubscriptionManager::class)
        ->shouldReceive('cancelPendingPlan')
        ->once()
        ->andReturnUsing(function (User $u): bool {
            $u->forceFill(['pending_plan_ends_at' => now()->addDays(20)])->save();

            return true;
        });

    Livewire::actingAs($user)->test('subscription-modal')
        ->call('selectTier', 'free')
        ->assertSeeHtml('data-downgrade-notice')
        ->call('checkout')
        ->assertRedirect('/')
        ->assertNotDispatched('open-add-modal');

    expect(session('status'))->toContain('Your Standard plan stays active for 20 days');

    Livewire::actingAs($user->fresh())->test('navigation')
        ->assertSee('Standard')
        ->assertSee('then Free');
});

it('falls back to Free once a downgraded pending plan has lapsed', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'pending_stripe_session_id' => 'cs_test_paid',
        'pending_plan_tier' => 'standard',
        'pending_plan_ends_at' => now()->subDay(),
    ])->save();

    expect(SubscriptionGating::getOwnerTier($user))->toBe('free');
});
