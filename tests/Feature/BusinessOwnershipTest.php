<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function ownedListing(User $owner): Business
{
    return Business::create([
        'user_id' => $owner->id,
        'name' => 'Owner Shop',
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'Test listing.',
    ]);
}

it('lets the owner edit their listing', function () {
    $owner = User::factory()->create();
    $business = ownedListing($owner);

    Livewire::actingAs($owner)->test('business-list')
        ->call('editBusiness', $business->id)
        ->assertDispatched('edit-business');

    Livewire::actingAs($owner)->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->assertSet('editingBusinessId', $business->id);
});

it('blocks other users from editing a listing', function () {
    $business = ownedListing(User::factory()->create());
    $other = User::factory()->create();

    Livewire::actingAs($other)->test('business-list')
        ->call('editBusiness', $business->id)
        ->assertForbidden();

    Livewire::actingAs($other)->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->assertForbidden();
});

it('blocks other users from saving changes to a listing', function () {
    $business = ownedListing(User::factory()->create());

    Livewire::actingAs(User::factory()->create())->test('add-business-modal')
        ->set('editingBusinessId', $business->id)
        ->set('name', 'Hijacked')
        ->call('save')
        ->assertForbidden();

    expect($business->fresh()->name)->toBe('Owner Shop');
});

it('only shows the edit button to the owner', function () {
    $owner = User::factory()->create();
    ownedListing($owner);

    Livewire::actingAs($owner)->test('business-list')->assertSeeHtml('title="Edit listing"');
    Livewire::actingAs(User::factory()->create())->test('business-list')->assertDontSeeHtml('title="Edit listing"');
    Livewire::test('business-list')->assertDontSeeHtml('title="Edit listing"');
});
