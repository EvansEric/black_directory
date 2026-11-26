<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoMetadata
{
    /** @return array{title: string, description: string, canonical: ?string, robots: string, image: string, structuredData: array<string, mixed>} */
    public function forRequest(Request $request, ?string $title = null): array
    {
        $page = config('seo.pages.'.$request->route()?->getName());
        $business = $request->route('business');
        $public = is_array($page);
        $description = $page['description'] ?? config('seo.description');
        $title = $page['title'] ?? $title ?? config('seo.name');
        $image = asset(config('seo.image'));
        $canonical = $request->url();
        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => route('home').'#organization',
                    'name' => config('seo.name'),
                    'url' => route('home'),
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => route('home').'#website',
                    'name' => config('seo.name'),
                    'url' => route('home'),
                    'publisher' => ['@id' => route('home').'#organization'],
                ],
            ],
        ];

        if ($business instanceof Business && $business->is_verified && $request->routeIs('businesses.show', 'reviews.index')) {
            $public = true;
            $description = Str::limit(app(BusinessDescription::class)->plainText($business), 155);
            $title = $request->routeIs('reviews.index')
                ? "Reviews for {$business->name} | Noir Directory"
                : "{$business->name} | {$business->category} in {$business->location} | Noir Directory";

            if ($business->canAccessLogo() && filled($business->image)) {
                $image = asset('storage/'.$business->image);
            }

            if ($request->routeIs('businesses.show')) {
                $schema['@graph'][] = $this->businessSchema($business, $description, $image);
            }
        }

        $pageNumber = filter_var($request->query('page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2]]);
        if ($pageNumber !== false) {
            $canonical .= '?page='.$pageNumber;
            $title .= ' - Page '.$pageNumber;
        }

        $filtered = $request->routeIs('home') && $request->hasAny([
            'search', 'location', 'category', 'sortVal', 'parking', 'miles', 'insurance', 'saved',
        ]);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $public ? $canonical : null,
            'robots' => $public && ! $filtered ? 'index, follow, max-image-preview:large' : 'noindex, follow',
            'image' => $image,
            'structuredData' => $public && ! $filtered ? $schema : [],
        ];
    }

    /** @return array<string, mixed> */
    private function businessSchema(Business $business, string $description, string $image): array
    {
        $schema = [
            '@type' => 'LocalBusiness',
            '@id' => route('businesses.show', $business).'#business',
            'url' => route('businesses.show', $business),
            'name' => $business->name,
            'description' => $description,
            'image' => $image,
        ];

        if (filled($business->phone)) {
            $schema['telephone'] = $business->phone;
        }

        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $business->streetAddress(),
            'addressLocality' => $business->city,
            'addressRegion' => $business->state,
            'postalCode' => $business->zip,
        ], fn ($value) => filled($value));

        if (count($address) > 1) {
            $schema['address'] = $address;
        }

        if ($business->latitude !== null && $business->longitude !== null) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $business->latitude,
                'longitude' => $business->longitude,
            ];
        }

        $links = collect($business->availableSocialLinks())->pluck('url')->all();
        if ($business->websiteLink()) {
            $links[] = $business->website;
        }
        if ($links !== []) {
            $schema['sameAs'] = $links;
        }

        return $schema;
    }
}
