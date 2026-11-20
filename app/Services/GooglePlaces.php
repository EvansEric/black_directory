<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GooglePlaces
{
    private const BASE_URL = 'https://places.googleapis.com/v1';

    /**
     * @return array<int, array{place_id: string, description: string, main_text: string, secondary_text: string}>
     */
    public function autocomplete(string $input, ?string $sessionToken = null): array
    {
        $response = $this->request(
            self::BASE_URL.'/places:autocomplete',
            'suggestions.placePrediction.place,suggestions.placePrediction.text,suggestions.placePrediction.structuredFormat',
            [
                'input' => $input,
                'includedRegionCodes' => ['US'],
                'sessionToken' => $sessionToken,
            ],
        );

        return collect($response->json('suggestions', []))
            ->map(function (array $suggestion): ?array {
                $prediction = $suggestion['placePrediction'] ?? [];
                $place = str($prediction['place'] ?? '')->after('places/')->toString();

                if (blank($place)) {
                    return null;
                }

                $format = $prediction['structuredFormat'] ?? [];

                return [
                    'place_id' => $place,
                    'description' => $prediction['text']['text'] ?? '',
                    'main_text' => $format['mainText']['text'] ?? '',
                    'secondary_text' => $format['secondaryText']['text'] ?? '',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{address: string, city: string, state: string, zip: string, latitude: float|null, longitude: float|null}
     */
    public function details(string $placeId, ?string $sessionToken = null): array
    {
        $response = $this->request(
            self::BASE_URL.'/places/'.rawurlencode($placeId),
            'addressComponents,location',
            $sessionToken ? ['sessionToken' => $sessionToken] : [],
            'GET',
        );

        $components = collect($response->json('addressComponents', []));
        $component = fn (string $type): string => (string) ($components
            ->first(fn (array $item): bool => in_array($type, $item['types'] ?? [], true))['longText'] ?? '');
        $shortComponent = fn (string $type): string => (string) ($components
            ->first(fn (array $item): bool => in_array($type, $item['types'] ?? [], true))['shortText'] ?? '');

        $address = collect([$component('street_number'), $component('route')])
            ->filter()
            ->implode(' ');

        return [
            'address' => $address,
            'city' => $component('locality') ?: $component('sublocality') ?: $component('postal_town'),
            'state' => $shortComponent('administrative_area_level_1'),
            'zip' => $component('postal_code'),
            'latitude' => is_numeric($response->json('location.latitude')) ? (float) $response->json('location.latitude') : null,
            'longitude' => is_numeric($response->json('location.longitude')) ? (float) $response->json('location.longitude') : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function request(string $url, string $fieldMask, array $data, string $method = 'POST'): Response
    {
        $key = config('services.google_maps.key');

        if (blank($key)) {
            throw new HttpException(503, 'Google address autocomplete is not configured.');
        }

        $request = Http::withHeaders([
            'X-Goog-Api-Key' => $key,
            'X-Goog-FieldMask' => $fieldMask,
        ])->timeout(8);

        $data = array_filter($data, fn (mixed $value): bool => $value !== null && $value !== '');
        $response = $method === 'GET' ? $request->get($url, $data) : $request->post($url, $data);

        if (! $response->successful()) {
            Log::warning('Google Places request failed.', [
                'status' => $response->status(),
                'google_status' => $response->json('error.status'),
                'message' => $response->json('error.message'),
            ]);

            throw new HttpException(502, 'Address autocomplete is temporarily unavailable.');
        }

        return $response;
    }
}
