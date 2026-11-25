<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    private const PAGE_SIZE = 1000;

    public function index(): Response
    {
        $entries = [['loc' => route('sitemap.pages')]];
        $pages = (int) ceil(Business::query()->where('is_verified', true)->count() / self::PAGE_SIZE);

        for ($page = 1; $page <= $pages; $page++) {
            $entries[] = ['loc' => route('sitemap.businesses', $page)];
        }

        return response()->view('seo.sitemap', [
            'index' => true,
            'entries' => $entries,
        ])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function pages(): Response
    {
        $entries = [];
        /** @var array<string, array{title: string, description: string}> $pages */
        $pages = config('seo.pages');

        foreach (array_keys($pages) as $name) {
            $entries[] = ['loc' => route($name)];
        }

        return response()->view('seo.sitemap', [
            'index' => false,
            'entries' => $entries,
        ])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function businesses(int $page): Response
    {
        abort_if($page < 1, 404);

        $businesses = Business::query()->where('is_verified', true)->orderBy('id')
            ->forPage($page, self::PAGE_SIZE)->get(['id', 'updated_at']);

        abort_if($businesses->isEmpty(), 404);

        $entries = $businesses->map(fn (Business $business): array => [
            'loc' => route('businesses.show', $business),
            'lastmod' => $business->updated_at?->toAtomString(),
        ]);

        return response()->view('seo.sitemap', [
            'index' => false,
            'entries' => $entries,
        ])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\n\nSitemap: ".route('sitemap.index')."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
