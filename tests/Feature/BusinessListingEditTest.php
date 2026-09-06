<?php

use App\Models\Business;
use App\Models\User;
use Livewire\Livewire;

function businessFor(User $user, array $attributes = []): Business
{
    return Business::create([
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
        ->set('location', 'Decatur, GA')
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

it('forbids a non-owner from opening the edit form', function () {
    $business = businessFor(User::factory()->create());

    $this->actingAs(User::factory()->create());

    Livewire::test('business-list')
        ->call('editBusiness', $business->id)
        ->assertForbidden();
});
