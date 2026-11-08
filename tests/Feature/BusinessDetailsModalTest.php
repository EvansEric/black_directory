<?php

use App\Models\Business;
use App\Models\User;
use App\Services\GoogleMapsEmbed;
use Livewire\Livewire;

function makeBusiness(array $overrides = []): Business
{
    return Business::create([
        'is_verified' => true,
        'user_id' => User::factory()->create()->id,
        'name' => 'Noir Coffee',
        'category' => 'Dining & Food',
        'location' => 'Atlanta, GA',
        'address' => '12 Main St',
        'zip' => '30303',
        'phone' => '555-0100',
        'description' => 'Fresh roasted coffee.',
        ...$overrides,
    ]);
}

it('shows business info and a map pin when a listing is opened', function () {
    config(['services.google_maps.key' => 'test-key']);

    $business = makeBusiness();

    Livewire::test('business-details-modal')
        ->call('open', $business->id)
        ->assertSee('Noir Coffee')
        ->assertSee('555-0100')
        ->assertSee('google.com/maps/embed/v1/place')
        ->assertSee('key=test-key', false)
        ->assertSee('Open in Google Maps');
});

it('falls back to a keyless embed when no API key is configured', function () {
    $business = makeBusiness();

    expect((new GoogleMapsEmbed(null))->embedUrl($business))
        ->toContain('output=embed')
        ->toContain('12+Main+St');
});

it('only shows the saved businesses control to logged in users', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Saved (', false);

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Saved (', false);
});

it('shows the current plan in the user menu', function () {
    $this->get(route('home'))->assertDontSee('data-current-plan', false);

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertSee('Current plan')
        ->assertSee('Free');
});

it('opens the details modal from the home page listing', function () {
    makeBusiness();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('open-business-details', false);
});
