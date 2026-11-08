<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function savedListing(string $name): Business
{
    return Business::create([
        'is_verified' => true,
        'user_id' => User::factory()->create()->id,
        'name' => $name,
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'Test listing.',
    ]);
}

it('keeps saved businesses separate for each user', function () {
    [$a, $b, $c] = [savedListing('Alpha Shop'), savedListing('Beta Shop'), savedListing('Gamma Shop')];
    $one = User::factory()->create();
    $two = User::factory()->create();

    Livewire::actingAs($one)->test('business-list')->call('toggleSaved', $a->id);
    Livewire::actingAs($two)->test('business-list')->call('toggleSaved', $b->id)->call('toggleSaved', $c->id);

    Livewire::actingAs($one)->test('business-list')
        ->call('toggleSavedFilter')
        ->assertSee('Alpha Shop')
        ->assertDontSee('Beta Shop')
        ->assertDontSee('Gamma Shop');

    Livewire::actingAs($two)->test('business-list')
        ->call('toggleSavedFilter')
        ->assertDontSee('Alpha Shop')
        ->assertSee('Beta Shop')
        ->assertSee('Gamma Shop');

    expect($one->savedBusinesses()->count())->toBe(1)
        ->and($two->savedBusinesses()->count())->toBe(2);
});

it('unsaves a business when toggled twice', function () {
    $business = savedListing('Alpha Shop');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('business-list')
        ->call('toggleSaved', $business->id)
        ->call('toggleSaved', $business->id);

    expect($user->savedBusinesses()->count())->toBe(0);
});

it('shows an empty state when the user has nothing saved', function () {
    savedListing('Alpha Shop');

    Livewire::actingAs(User::factory()->create())->test('business-list')
        ->call('toggleSavedFilter')
        ->assertSee('No saved businesses yet')
        ->assertDontSee('Alpha Shop')
        ->call('showAllBusinesses')
        ->assertSee('Alpha Shop');
});

it('asks guests to log in instead of saving', function () {
    $business = savedListing('Alpha Shop');

    Livewire::test('business-list')
        ->call('toggleSaved', $business->id)
        ->assertDispatched('open-auth-modal')
        ->call('toggleSavedFilter')
        ->assertSet('savedOnly', false);

    $this->assertDatabaseCount('business_favorites', 0);
});

it('shows each user their own saved count in the navigation', function () {
    $business = savedListing('Alpha Shop');
    $one = User::factory()->create();
    $two = User::factory()->create();
    $one->savedBusinesses()->attach($business);

    Livewire::actingAs($one)->test('navigation')->assertSee('Saved (1)');
    Livewire::actingAs($two)->test('navigation')->assertSee('Saved (0)');
});

it('keeps saved businesses after logging out and back in', function () {
    $business = savedListing('Alpha Shop');
    $user = User::factory()->create(['email' => 'saver@example.com', 'password' => 'password']);

    Livewire::actingAs($user)->test('business-list')->call('toggleSaved', $business->id);
    Livewire::actingAs($user)->test('navigation')->call('logout');
    $this->assertGuest();

    Livewire::test('auth-modal')
        ->set('loginEmail', 'saver@example.com')
        ->set('loginPassword', 'password')
        ->call('login');
    $this->assertAuthenticatedAs($user);

    Livewire::test('navigation')->assertSee('Saved (1)');
    Livewire::test('business-list')->call('toggleSavedFilter')->assertSee('Alpha Shop');
});

it('restores filters from the url so they survive a refresh', function () {
    savedListing('Alpha Shop');

    Livewire::withQueryParams(['search' => 'Alpha', 'category' => 'Retail & Fashion', 'sortVal' => 'newest'])
        ->test('business-list')
        ->assertSet('search', 'Alpha')
        ->assertSet('category', 'Retail & Fashion')
        ->assertSet('sortVal', 'newest')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('category', 'All');
});

it('restores the saved filter from the url', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->withQueryParams(['saved' => '1'])->test('business-list')->assertSet('savedOnly', true);
    Livewire::actingAs($user)->withQueryParams(['saved' => '1'])->test('navigation')->assertSet('savedActive', true);
});
