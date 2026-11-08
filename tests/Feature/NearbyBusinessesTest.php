<?php

use App\Models\Business;
use App\Models\User;
use App\Services\Geocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// Atlanta is the visitor; Decatur is ~6 mi away, Macon ~80 mi, Chicago ~590 mi.
function nearbyListing(string $name, ?float $lat, ?float $lng): Business
{
    return Business::create([
        'is_verified' => true,
        'user_id' => User::factory()->create()->id, 'name' => $name, 'category' => 'Retail & Fashion',
        'location' => 'Somewhere, GA', 'description' => 'x', 'latitude' => $lat, 'longitude' => $lng,
    ]);
}

beforeEach(function () {
    nearbyListing('Chicago Shop', 41.8781, -87.6298);
    nearbyListing('Macon Shop', 32.8407, -83.6324);
    nearbyListing('Decatur Shop', 33.7748, -84.2963);
    nearbyListing('Unknown Shop', null, null);
});

it('sorts nearest first once the visitor shares their location', function () {
    Livewire::test('business-list')
        ->call('setLocation', 33.7490, -84.3880)
        ->assertSet('sortVal', 'nearest')
        ->set('distance', 'any')
        ->assertSeeInOrder(['Decatur Shop', 'Macon Shop', 'Chicago Shop', 'Unknown Shop'])
        ->assertSeeHtml('data-distance-badge');
});

it('filters by distance in miles', function () {
    Livewire::test('business-list')
        ->call('setLocation', 33.7490, -84.3880)
        ->set('distance', '10')
        ->assertSee('Decatur Shop')
        ->assertDontSee('Macon Shop')
        ->assertDontSee('Chicago Shop')
        ->assertDontSee('Unknown Shop')
        ->set('distance', '100')
        ->assertSee('Macon Shop')
        ->assertDontSee('Chicago Shop')
        ->set('distance', 'any')
        ->assertSee('Chicago Shop');
});

it('defaults to any distance and limits to 5 miles when chosen', function () {
    nearbyListing('Midtown Shop', 33.7800, -84.3830);

    Livewire::test('business-list')
        ->assertSet('distance', 'any')
        ->assertSee('Chicago Shop')
        ->call('setLocation', 33.7490, -84.3880)
        ->assertSee('Chicago Shop')
        ->set('distance', '5')
        ->assertSee('Midtown Shop')
        ->assertDontSee('Decatur Shop')
        ->assertDontSee('Chicago Shop');
});

it('shows no distances without a location and ignores bad coordinates', function () {
    Livewire::test('business-list')
        ->assertDontSeeHtml('data-distance-badge')
        ->call('setLocation', 123.0, 400.0)
        ->assertSet('userLat', null)
        ->assertSet('sortVal', 'featured');
});

it('clears the location and distance filter', function () {
    Livewire::test('business-list')
        ->call('setLocation', 33.7490, -84.3880)
        ->set('distance', '10')
        ->call('clearLocation')
        ->assertSet('userLat', null)
        ->assertSet('distance', 'any')
        ->assertSet('sortVal', 'featured');
});

it('keeps a chosen sort when location is shared and resets distance on filter reset', function () {
    Livewire::test('business-list')
        ->set('sortVal', 'name')
        ->call('setLocation', 33.7490, -84.3880)
        ->assertSet('sortVal', 'name')
        ->set('distance', '25')
        ->call('resetFilters')
        ->assertSet('distance', 'any');
});

it('geocodes an address through nominatim', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::sequence()
        ->push([['lat' => '33.749', 'lon' => '-84.388']])
        ->push([])]);

    $coords = (new Geocoder(true))->geocode('Atlanta, GA');

    expect($coords)->toBe(['latitude' => 33.749, 'longitude' => -84.388]);
    expect((new Geocoder(false))->geocode('Atlanta, GA'))->toBeNull();

    expect((new Geocoder(true))->geocode('Nowhere'))->toBeNull();
});

it('stores coordinates when a listing is saved', function () {
    $this->mock(Geocoder::class)->shouldReceive('geocode')
        ->andReturn(['latitude' => 33.749, 'longitude' => -84.388]);

    Livewire::actingAs(User::factory()->create())->test('add-business-modal')
        ->set('name', 'Pinned Shop')
        ->set('category', 'Retail & Fashion')
        ->set('city', 'Atlanta')
        ->set('state', 'GA')
        ->set('description', 'x')
        ->call('save')
        ->assertHasNoErrors();

    $business = Business::where('name', 'Pinned Shop')->first();
    expect($business->latitude)->toBe(33.749)->and($business->longitude)->toBe(-84.388);
});

it('matches a zip code search to listings in that zip\'s city', function () {
    $this->mock(Geocoder::class)->shouldReceive('cityForZip')->with('30030')->andReturn('Decatur');
    Business::where('name', 'Decatur Shop')->update(['location' => 'Decatur, GA']);

    Livewire::test('business-list')
        ->set('location', '30030')
        ->set('distance', 'any')
        ->assertSee('Decatur Shop')
        ->assertDontSee('Macon Shop');
});

it('measures the distance filter from a typed zip code', function () {
    $geocoder = $this->mock(Geocoder::class);
    $geocoder->shouldReceive('coordinatesForZip')->with('77550')
        ->andReturn(['latitude' => 29.3013, 'longitude' => -94.7977]);
    $geocoder->shouldReceive('cityForZip')->andReturn('Galveston');
    nearbyListing('Houston Shop', 29.7604, -95.3698);

    Livewire::test('business-list')
        ->set('location', '77550')
        ->set('distance', '100')
        ->assertSee('Houston Shop')
        ->assertDontSee('Decatur Shop')
        ->assertDontSee('Chicago Shop')
        ->assertSeeHtml('data-distance-badge');
});
