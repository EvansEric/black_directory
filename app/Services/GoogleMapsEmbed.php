<?php

namespace App\Services;

use App\Models\Business;

class GoogleMapsEmbed
{
    public function __construct(private readonly ?string $apiKey = null) {}

    /**
     * Pinned-place embed URL. Uses the Maps Embed API when a key is configured.
     */
    public function embedUrl(Business $business): string
    {
        $query = $this->query($business);

        if (filled($this->apiKey)) {
            return 'https://www.google.com/maps/embed/v1/place?'.http_build_query([
                'key' => $this->apiKey,
                'q' => $query,
            ]);
        }

        return 'https://www.google.com/maps?'.http_build_query(['q' => $query, 'output' => 'embed']);
    }

    public function directionsUrl(Business $business): string
    {
        return 'https://www.google.com/maps/search/?'.http_build_query([
            'api' => 1,
            'query' => $this->query($business),
        ]);
    }

    private function query(Business $business): string
    {
        $parts = filled($business->address)
            ? [$business->address, $business->location, $business->zip]
            : [$business->name, $business->location, $business->zip];

        return implode(', ', array_filter(array_map('trim', array_map('strval', $parts))));
    }
}
