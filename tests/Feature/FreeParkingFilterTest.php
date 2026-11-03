<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeListing(string $name, string $category, ?bool $parking): Business
{
    return Business::create([
        'user_id' => User::factory()->create()->id,
        'name' => $name,
        'category' => $category,
        'location' => 'Atlanta, GA',
        'description' => 'Test listing.',
        'free_parking' => $parking,
    ]);
}

it('filters food businesses by free parking', function () {
    makeListing('Parking Diner', 'Dining & Food', true);
    makeListing('Street Diner', 'Dining & Food', false);
    makeListing('Unknown Diner', 'Dining & Food', null);

    Livewire::test('business-list')
        ->call('selectCategory', 'Dining & Food')
        ->set('parking', 'yes')
        ->assertSee('Parking Diner')
        ->assertDontSee('Street Diner')
        ->assertDontSee('Unknown Diner')
        ->set('parking', 'no')
        ->assertSee('Street Diner')
        ->assertDontSee('Parking Diner')
        ->set('parking', 'any')
        ->assertSee('Unknown Diner');
});

it('only offers the parking filter for the food category and ignores it elsewhere', function () {
    makeListing('Parking Diner', 'Dining & Food', true);
    makeListing('Barber Shop', 'Beauty & Barber', null);

    Livewire::test('business-list')
        ->assertDontSee('parking-select', false)
        ->set('parking', 'yes')
        ->assertSee('Barber Shop')
        ->call('selectCategory', 'Dining & Food')
        ->assertSee('parking-select', false)
        ->assertSet('parking', 'any');
});

it('shows a parking badge on food listings', function () {
    makeListing('Parking Diner', 'Dining & Food', true);

    Livewire::test('business-list')->assertSee('Free parking');
});

it('lets an owner set free parking for a food business and clears it for other categories', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);

    Livewire::test('add-business-modal')
        ->set('name', 'Soul Kitchen')
        ->set('category', 'Dining & Food')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'Good food.')
        ->set('freeParking', '1')
        ->call('save')
        ->assertHasNoErrors();

    expect(Business::where('name', 'Soul Kitchen')->first()->free_parking)->toBeTrue();

    $this->actingAs(User::factory()->create());

    Livewire::test('add-business-modal')
        ->set('name', 'Fresh Cuts')
        ->set('category', 'Beauty & Barber')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'Cuts.')
        ->set('freeParking', '1')
        ->call('save')
        ->assertHasNoErrors();

    expect(Business::where('name', 'Fresh Cuts')->first()->free_parking)->toBeNull();
});

it('loads and updates free parking when editing a listing', function () {
    $owner = User::factory()->create();
    $business = Business::create([
        'user_id' => $owner->id,
        'name' => 'Edit Diner',
        'category' => 'Dining & Food',
        'city' => 'Atlanta',
        'state' => 'GA',
        'location' => 'Atlanta, GA',
        'description' => 'Test listing.',
        'free_parking' => true,
    ]);

    $component = Livewire::actingAs($owner)
        ->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->assertSet('freeParking', '1');

    $component->set('freeParking', '0')->call('save')->assertHasNoErrors();
    expect($business->fresh()->free_parking)->toBeFalse();

    $component->call('editBusiness', $business->id)
        ->assertSet('freeParking', '0')
        ->set('freeParking', '')
        ->call('save')
        ->assertHasNoErrors();
    expect($business->fresh()->free_parking)->toBeNull();

    $component->call('editBusiness', $business->id)
        ->set('freeParking', '1')
        ->call('save');
    expect($business->fresh()->free_parking)->toBeTrue();
});

it('always renders the free parking dropdown in the business form and toggles it by category', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('add-business-modal')
        ->assertSeeHtml('data-free-parking-field')
        ->assertSeeHtml('wire:model="freeParking"')
        ->assertSeeHtml("\$wire.category === 'Dining & Food'");
});
