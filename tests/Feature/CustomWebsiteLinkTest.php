<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function websiteForm($component)
{
    return $component
        ->set('name', 'Linked Shop')
        ->set('category', 'Retail & Fashion')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'Hello')
        ->set('website', 'https://linked.example.com');
}

it('drops the custom link for free users', function () {
    websiteForm(Livewire::actingAs(User::factory()->create())->test('add-business-modal'))
        ->assertSee('Custom link is a Standard-tier feature')
        ->call('save');

    expect(Business::first()->website)->toBeNull();
});

it('saves the custom link on a new listing for a user with a paid plan', function () {
    $user = User::factory()->create();
    $user->forceFill(['pending_stripe_session_id' => 'cs_test_paid', 'pending_plan_tier' => 'standard'])->save();

    websiteForm(Livewire::actingAs($user)->test('add-business-modal'))
        ->assertSeeHtml('wire:model.blur="website"')
        ->call('save')
        ->assertHasNoErrors();

    expect(Business::first()->website)->toBe('https://linked.example.com');
});

it('rejects non-http links', function () {
    $user = User::factory()->create();
    $user->forceFill(['pending_stripe_session_id' => 'cs_test_paid', 'pending_plan_tier' => 'standard'])->save();

    websiteForm(Livewire::actingAs($user)->test('add-business-modal'))
        ->set('website', 'ftp://files.example.com')
        ->call('save')
        ->assertHasErrors('website');
});

it('shows the visit website link only on paid listings', function () {
    $owner = User::factory()->create();
    $make = fn (string $name, string $tier) => Business::create([
        'user_id' => $owner->id, 'name' => $name, 'category' => 'Retail & Fashion', 'location' => 'Atlanta, GA',
        'description' => 'x', 'website' => 'https://'.strtolower($name).'.example.com',
        'subscription_tier' => $tier, 'subscription_status' => 'active', 'subscription_ends_at' => now()->addMonth(),
    ]);
    $make('Paid', 'standard');
    $make('Unpaid', 'free');

    Livewire::test('business-list')
        ->assertSeeHtml('href="https://paid.example.com"')
        ->assertDontSeeHtml('https://unpaid.example.com');
});

function slugBusiness(array $overrides = []): Business
{
    return Business::create([
        'user_id' => User::factory()->create()->id, 'name' => 'Slug Shop', 'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA', 'description' => 'x', 'website' => 'https://real.example.com',
        'custom_slug' => 'slug-shop', 'subscription_tier' => 'standard', 'subscription_status' => 'active',
        'subscription_ends_at' => now()->addMonth(), ...$overrides,
    ]);
}

it('masks the website behind /b/slug and redirects to the original url', function () {
    slugBusiness();

    $this->get('/b/slug-shop')->assertRedirect('https://real.example.com');
    $this->get('/b/unknown')->assertNotFound();
});

it('stops redirecting when the plan is no longer paid', function () {
    slugBusiness(['subscription_tier' => 'free']);

    $this->get('/b/slug-shop')->assertNotFound();
});

it('shows the masked link instead of the original website', function () {
    slugBusiness();

    Livewire::test('business-list')
        ->assertSeeHtml('href="'.url('/b/slug-shop').'"')
        ->assertDontSeeHtml('https://real.example.com');
});

it('saves a custom slug for paid users and keeps slugs unique', function () {
    slugBusiness();
    $user = User::factory()->create();
    $user->forceFill(['pending_stripe_session_id' => 'cs_test_paid', 'pending_plan_tier' => 'standard'])->save();

    websiteForm(Livewire::actingAs($user)->test('add-business-modal'))
        ->set('customSlug', 'slug-shop')
        ->call('save')
        ->assertHasErrors('customSlug')
        ->set('customSlug', 'Bad Slug!')
        ->call('save')
        ->assertHasErrors('customSlug')
        ->set('customSlug', 'linked-shop')
        ->call('save')
        ->assertHasNoErrors();

    expect(Business::where('name', 'Linked Shop')->first()->custom_slug)->toBe('linked-shop');
});

it('ignores a custom slug for free users', function () {
    websiteForm(Livewire::actingAs(User::factory()->create())->test('add-business-modal'))
        ->set('customSlug', 'free-slug')
        ->call('save');

    expect(Business::first()->custom_slug)->toBeNull();
});
