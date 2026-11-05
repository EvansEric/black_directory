<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Geocoder
{
    public function __construct(
        private readonly bool $enabled = true,
        private readonly string $endpoint = 'https://nominatim.openstreetmap.org/search',
    ) {}

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public function geocode(string $address): ?array
    {
        if (! $this->enabled || blank($address)) {
            return null;
        }

        try {
            $response = Http::withUserAgent(config('app.name', 'Directory').' geocoder')
                ->timeout(5)
                ->get($this->endpoint, ['q' => $address, 'format' => 'json', 'limit' => 1, 'countrycodes' => 'us']);

            $match = $response->successful() ? ($response->json()[0] ?? null) : null;
        } catch (\Throwable $exception) {
            Log::warning('Geocoding failed: '.$exception->getMessage());

            return null;
        }

        if (! isset($match['lat'], $match['lon'])) {
            return null;
        }

        return ['latitude' => (float) $match['lat'], 'longitude' => (float) $match['lon']];
    }

    /**
     * Resolve a US zip code to its city name.
     */
    public function cityForZip(string $zip): ?string
    {
        return $this->lookupZip($zip)['city'] ?? null;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public function coordinatesForZip(string $zip): ?array
    {
        $found = $this->lookupZip($zip);

        return $found ? ['latitude' => $found['latitude'], 'longitude' => $found['longitude']] : null;
    }

    /**
     * Successful zip lookups are cached.
     *
     * @return array{latitude: float, longitude: float, city: ?string}|null
     */
    private function lookupZip(string $zip): ?array
    {
        if (! $this->enabled || ! preg_match('/^\d{5}$/', $zip)) {
            return null;
        }

        $key = "geocoder.zip.{$zip}";

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        try {
            $response = Http::withUserAgent(config('app.name', 'Directory').' geocoder')
                ->timeout(5)
                ->get($this->endpoint, ['postalcode' => $zip, 'format' => 'json', 'limit' => 1, 'addressdetails' => 1, 'countrycodes' => 'us']);

            $match = $response->successful() ? ($response->json()[0] ?? null) : null;
        } catch (\Throwable $exception) {
            Log::warning('Zip lookup failed: '.$exception->getMessage());

            return null;
        }

        if (! isset($match['lat'], $match['lon'])) {
            return null;
        }

        $address = $match['address'] ?? [];
        $found = [
            'latitude' => (float) $match['lat'],
            'longitude' => (float) $match['lon'],
            'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['hamlet'] ?? null,
        ];

        Cache::forever($key, $found);

        return $found;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public function locate(Business $business): ?array
    {
        $parts = filled($business->city) && filled($business->state)
            ? [$business->address, $business->city, $business->state, $business->zip]
            : [$business->address, $business->location];

        return $this->geocode(collect($parts)->filter()->implode(', '));
    }
}
