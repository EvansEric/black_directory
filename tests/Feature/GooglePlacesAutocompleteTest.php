<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('returns address predictions from Places API New', function () {
    config()->set('services.google_maps.key', 'test-key');
    Http::fake([
        'https://places.googleapis.com/v1/places:autocomplete' => Http::response([
            'suggestions' => [[
                'placePrediction' => [
                    'place' => 'places/ChIJ123',
                    'text' => ['text' => '123 Main St, Atlanta, GA'],
                    'structuredFormat' => [
                        'mainText' => ['text' => '123 Main St'],
                        'secondaryText' => ['text' => 'Atlanta, GA'],
                    ],
                ],
            ]],
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('places.autocomplete', [
            'input' => '123 Main',
            'session_token' => '83da2f35-3c45-4c79-a4ca-bc7f9d4cc10a',
        ]))
        ->assertOk()
        ->assertJsonPath('suggestions.0.place_id', 'ChIJ123')
        ->assertJsonPath('suggestions.0.main_text', '123 Main St');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Goog-Api-Key', 'test-key')
        && $request->data()['includedRegionCodes'] === ['US']);
});

it('normalizes Places details for the Livewire business form', function () {
    config()->set('services.google_maps.key', 'test-key');
    Http::fake([
        'https://places.googleapis.com/v1/places/ChIJ123*' => Http::response([
            'addressComponents' => [
                ['longText' => '123', 'types' => ['street_number']],
                ['longText' => 'Main Street', 'shortText' => 'Main St', 'types' => ['route']],
                ['longText' => 'Atlanta', 'types' => ['locality']],
                ['longText' => 'Georgia', 'shortText' => 'GA', 'types' => ['administrative_area_level_1']],
                ['longText' => '30303', 'types' => ['postal_code']],
            ],
            'location' => ['latitude' => 33.749, 'longitude' => -84.388],
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('places.show', ['place' => 'ChIJ123', 'session_token' => '83da2f35-3c45-4c79-a4ca-bc7f9d4cc10a']))
        ->assertOk()
        ->assertExactJson([
            'address' => '123 Main Street',
            'city' => 'Atlanta',
            'state' => 'GA',
            'zip' => '30303',
            'latitude' => 33.749,
            'longitude' => -84.388,
        ]);
});

it('requires authentication before searching addresses', function () {
    $this->getJson(route('places.autocomplete', ['input' => '123 Main']))
        ->assertUnauthorized();
});
