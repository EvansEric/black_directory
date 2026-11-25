<?php

use App\Models\Business;
use App\Models\User;

function seoBusiness(array $attributes = []): Business
{
    return Business::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Neighborhood Coffee',
        'category' => 'Dining & Food',
        'city' => 'Atlanta',
        'state' => 'GA',
        'address' => '12 Main St',
        'zip' => '30303',
        'phone' => '555-0100',
        'latitude' => 33.749,
        'longitude' => -84.388,
        'description' => 'Locally roasted coffee and fresh pastries.',
        'is_verified' => true,
        ...$attributes,
    ]);
}

function seoGraph(string $html): array
{
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR)['@graph'];
}

it('renders unique searchable metadata and social previews for public pages', function () {
    foreach (config('seo.pages') as $name => $metadata) {
        $response = $this->get(route($name));
        $response->assertOk()
            ->assertSee('<title>'.e($metadata['title']).'</title>', false)
            ->assertSee('<meta name="description" content="'.e($metadata['description']).'">', false)
            ->assertSee('<link rel="canonical" href="'.route($name).'">', false)
            ->assertSee('content="index, follow, max-image-preview:large"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card"', false);

        expect(array_column(seoGraph($response->getContent()), '@type'))
            ->toContain('WebSite', 'Organization');
    }
});

it('canonicalizes tracking URLs and keeps real pagination independently indexable', function () {
    $this->get(route('home').'?utm_source=newsletter')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
        ->assertDontSee('utm_source=newsletter');

    $this->get(route('home').'?page=2&utm_source=newsletter')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('home').'?page=2">', false)
        ->assertSee(' - Page 2</title>', false)
        ->assertSee('content="index, follow, max-image-preview:large"', false);
});

it('keeps filtered and saved directory variants out of search indexes', function (string $query) {
    $this->get(route('home').'?'.$query)
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertDontSee('application/ld+json');
})->with(['search=coffee', 'category=Insurance', 'saved=1', 'sortVal=name', 'miles=25']);

it('keeps account and password reset pages out of search indexes', function () {
    $this->get(route('password.reset', ['token' => 'private-token', 'email' => 'owner@example.test']))
        ->assertOk()
        ->assertSee('content="noindex, follow"', false)
        ->assertDontSee('rel="canonical"', false)
        ->assertDontSee('property="og:url"', false);

    $this->actingAs(User::factory()->create())->get(route('profile'))
        ->assertOk()
        ->assertSee('content="noindex, follow"', false);
});

it('publishes linked business profiles with accurate structured data and no fabricated ratings', function () {
    $business = seoBusiness(['rating' => 5, 'reviews_count' => 99]);

    $this->get(route('home'))->assertSee('href="'.route('businesses.show', $business).'"', false);

    $response = $this->get(route('businesses.show', $business));
    $response->assertOk()
        ->assertSee($business->description)
        ->assertSee('Not yet rated')
        ->assertSee('href="'.route('reviews.index', $business).'"', false)
        ->assertSee('<link rel="canonical" href="'.route('businesses.show', $business).'">', false);

    $schema = collect(seoGraph($response->getContent()))->firstWhere('@type', 'LocalBusiness');

    expect($schema['name'])->toBe($business->name)
        ->and($schema['address']['streetAddress'])->toBe('12 Main St')
        ->and($schema['address']['addressLocality'])->toBe('Atlanta')
        ->and($schema['geo']['latitude'])->toBe(33.749)
        ->and($schema)->not->toHaveKey('aggregateRating');

    $this->get(route('reviews.index', $business))
        ->assertOk()
        ->assertSee('<title>Reviews for '.$business->name.' | Noir Directory</title>', false);
});

it('does not publish unverified profiles even to their owners', function () {
    $business = seoBusiness(['is_verified' => false]);

    $this->get(route('businesses.show', $business))->assertNotFound();
    $this->actingAs($business->user)->get(route('businesses.show', $business))->assertNotFound();
    $this->get(route('reviews.index', $business))->assertNotFound();
});

it('escapes business content in metadata and JSON while preserving paid feature restrictions', function () {
    $business = seoBusiness([
        'name' => 'Coffee </script><script>alert("x")</script>',
        'description' => 'Fresh </script><script>alert("x")</script>',
        'website' => 'https://coffee.example',
        'instagram_url' => 'https://instagram.com/coffee',
        'image' => 'businesses/coffee.jpg',
    ]);

    $response = $this->get(route('businesses.show', $business));
    $response->assertOk()->assertDontSee('<script>alert("x")</script>', false);
    $schema = collect(seoGraph($response->getContent()))->firstWhere('@type', 'LocalBusiness');
    expect($schema['name'])->toBe($business->name)
        ->and($schema)->not->toHaveKey('sameAs')
        ->and($schema['image'])->not->toContain('businesses/coffee.jpg');

    $business->update(['subscription_tier' => 'standard']);
    $response = $this->get(route('businesses.show', $business))->assertOk();
    $schema = collect(seoGraph($response->getContent()))->firstWhere('@type', 'LocalBusiness');
    expect($schema['sameAs'])->toContain($business->website, $business->instagram_url)
        ->and($schema['image'])->toContain('businesses/coffee.jpg');
});

it('serves valid discoverable sitemaps containing only public verified listings', function () {
    $verified = seoBusiness();
    $pending = seoBusiness(['is_verified' => false]);

    $this->get('/robots.txt')->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap: '.route('sitemap.index'));

    $response = $this->get(route('sitemap.index'))->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $index = simplexml_load_string($response->getContent());
    expect($index->getName())->toBe('sitemapindex')
        ->and((string) $index->sitemap[0]->loc)->toBe(route('sitemap.pages'))
        ->and((string) $index->sitemap[1]->loc)->toBe(route('sitemap.businesses', 1));

    $response = $this->get(route('sitemap.pages'))->assertOk();
    $pages = simplexml_load_string($response->getContent());
    expect($pages->url)->toHaveCount(count(config('seo.pages')));
    $response->assertDontSee('/profile')->assertDontSee('/admin/');

    $response = $this->get(route('sitemap.businesses', 1))->assertOk();
    $urls = simplexml_load_string($response->getContent());
    expect($urls->url)->toHaveCount(1)
        ->and((string) $urls->url[0]->loc)->toBe(route('businesses.show', $verified));
    $response->assertDontSee(route('businesses.show', $pending));

    $this->get(route('sitemap.businesses', 2))->assertNotFound();
    $this->get('/sitemaps/businesses/0.xml')->assertNotFound();
});

it('splits business sitemaps into batches without dropping listings', function () {
    $owner = User::factory()->create();
    $timestamp = now();
    Business::query()->insert(array_map(fn (int $index): array => [
        'user_id' => $owner->id,
        'name' => "Business $index",
        'category' => 'Retail & Fashion',
        'location' => 'Atlanta, GA',
        'description' => 'Local shop.',
        'is_verified' => true,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ], range(1, 1001)));

    $index = simplexml_load_string($this->get(route('sitemap.index'))->assertOk()->getContent());
    expect($index->sitemap)->toHaveCount(3);

    $first = simplexml_load_string($this->get(route('sitemap.businesses', 1))->assertOk()->getContent());
    $second = simplexml_load_string($this->get(route('sitemap.businesses', 2))->assertOk()->getContent());

    expect($first->url)->toHaveCount(1000)
        ->and($second->url)->toHaveCount(1)
        ->and((string) $second->url[0]->loc)->toBe(route('businesses.show', Business::query()->latest('id')->firstOrFail()));
});
