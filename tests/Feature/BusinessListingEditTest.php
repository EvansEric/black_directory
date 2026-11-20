<?php

use App\Models\Business;
use App\Models\User;
use App\Services\Geocoder;
use Livewire\Livewire;

function businessFor(User $user, array $attributes = []): Business
{
    return Business::create([
        'is_verified' => true,
        'user_id' => $user->id,
        'name' => 'Original Business',
        'category' => 'Dining & Food',
        'location' => 'Atlanta, GA',
        'address' => '123 Main Street',
        'zip' => '30303',
        'phone' => '555-0100',
        'website' => 'https://example.com',
        'image' => 'businesses/original.webp',
        'description' => 'The original description.',
        'rating' => 4.5,
        'reviews_count' => 12,
        'featured' => false,
        'tags' => ['Local'],
        ...$attributes,
    ]);
}

it('allows a business owner to edit their listing', function () {
    $owner = User::factory()->create();
    $business = businessFor($owner);

    $this->actingAs($owner);

    Livewire::test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->assertSet('editingBusinessId', $business->id)
        ->assertSet('name', 'Original Business')
        ->set('name', 'Updated Business')
        ->set('city', 'Decatur')
        ->set('state', 'GA')
        ->set('description', 'The updated description.')
        ->call('save')
        ->assertDispatched('business-updated')
        ->assertDispatched('business-saved');

    expect($business->fresh())
        ->name->toBe('Updated Business')
        ->location->toBe('Decatur, GA')
        ->description->toBe('The updated description.')
        ->image->toBe('businesses/original.webp')
        ->rating->toBe('4.5')
        ->reviews_count->toBe(12)
        ->featured->toBeFalse()
        ->tags->toBe(['Local']);
});

it('allows insurance businesses to be listed and filtered by category', function () {
    $owner = User::factory()->create();

    Livewire::actingAs($owner)
        ->test('add-business-modal')
        ->assertSee('<option value="Insurance">Insurance</option>', false)
        ->set('name', 'Neighborhood Insurance')
        ->set('category', 'Insurance')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'Insurance coverage for local families.')
        ->call('save')
        ->assertDispatched('business-saved');

    $business = Business::query()->where('name', 'Neighborhood Insurance')->firstOrFail();
    expect($business->category)->toBe('Insurance');

    Livewire::test('business-list')
        ->assertSee('Insurance')
        ->call('selectCategory', 'Insurance')
        ->assertSee('Neighborhood Insurance')
        ->assertDontSee('Original Business');
});

it('applies an autocomplete address and saves its coordinates without geocoding', function () {
    $owner = User::factory()->create();
    $geocoder = $this->mock(Geocoder::class);
    $geocoder->shouldNotReceive('geocodeListingAddress');

    Livewire::actingAs($owner)
        ->test('add-business-modal')
        ->assertSee('data-address-autocomplete', false)
        ->set('name', 'Autocomplete Shop')
        ->set('category', 'Retail & Fashion')
        ->set('description', 'A nearby shop.')
        ->call('setAddressFromAutocomplete', '123 Peachtree St NE', 'Atlanta', 'GA', '30303', 33.755, -84.390)
        ->assertSet('address', '123 Peachtree St NE')
        ->assertSet('city', 'Atlanta')
        ->assertSet('state', 'GA')
        ->assertSet('zip', '30303')
        ->call('save')
        ->assertHasNoErrors();

    expect(Business::where('name', 'Autocomplete Shop')->firstOrFail())
        ->address->toBe('123 Peachtree St NE')
        ->latitude->toBe(33.755)
        ->longitude->toBe(-84.39);
});

it('uses the existing address autocomplete flow while editing a listing', function () {
    $owner = User::factory()->create();
    $business = businessFor($owner);

    Livewire::actingAs($owner)
        ->test('add-business-modal')
        ->call('editBusiness', $business->id)
        ->call('setAddressFromAutocomplete', '456 Decatur St', 'Decatur', 'ga', '30030', 33.7748, -84.2963)
        ->assertSet('address', '456 Decatur St')
        ->assertSet('city', 'Decatur')
        ->assertSet('state', 'GA')
        ->call('save')
        ->assertHasNoErrors();

    expect($business->fresh())
        ->address->toBe('456 Decatur St')
        ->location->toBe('Decatur, GA')
        ->zip->toBe('30030');
});

it('exposes address autocomplete endpoints without exposing the Google key', function () {
    config()->set('services.google_maps.key', 'browser-key');

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('places.autocomplete'), false)
        ->assertDontSee('browser-key', false);
});

it('forbids a non-owner from opening the edit form', function () {
    $business = businessFor(User::factory()->create());

    $this->actingAs(User::factory()->create());

    Livewire::test('business-list')
        ->call('editBusiness', $business->id)
        ->assertForbidden();
});

it('paginates the business directory six listings at a time and resets on search', function () {
    $user = User::factory()->create();

    foreach (range(1, 8) as $index) {
        businessFor($user, ['name' => "Directory Business {$index}"]);
    }

    $component = Livewire::test('business-list');

    expect($component->instance()->businesses->count())->toBe(6);

    $component->call('nextPage');
    expect($component->instance()->businesses->count())->toBe(2);

    $component->set('search', 'Directory');
    expect($component->instance()->businesses->count())->toBe(6);
});

it('limits how many businesses an owner can add by tier', function () {
    $user = User::factory()->create();
    businessFor($user);

    Livewire::actingAs($user)
        ->test('add-business-modal')
        ->set('name', 'Second Business')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'Another listing.')
        ->call('save')
        ->assertHasErrors('name');

    expect($user->businesses()->count())->toBe(1);

    businessFor($user, ['subscription_tier' => 'pro', 'subscription_status' => 'active']);

    Livewire::actingAs($user)
        ->test('add-business-modal')
        ->set('name', 'Third Business')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'Another listing.')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->businesses()->count())->toBe(3);
});
