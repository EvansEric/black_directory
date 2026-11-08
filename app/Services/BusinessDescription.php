<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Str;

class BusinessDescription
{
    public function formatted(Business $business): string
    {
        return Str::markdown($business->description, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    public function plainText(Business $business): string
    {
        if (! $business->has_rich_description) {
            return $business->description;
        }

        $html = preg_replace('/<\/(?:p|li|ul|ol|h[1-6])>|<br\s*\/?>/i', '$0 ', $this->formatted($business));

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
