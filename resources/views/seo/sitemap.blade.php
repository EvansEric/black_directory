{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
@if ($index)
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($entries as $entry)
    <sitemap>
        <loc>{{ $entry['loc'] }}</loc>
    </sitemap>
    @endforeach
</sitemapindex>
@else
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($entries as $entry)
    <url>
        <loc>{{ $entry['loc'] }}</loc>
        @if (! empty($entry['lastmod']))
        <lastmod>{{ $entry['lastmod'] }}</lastmod>
        @endif
    </url>
    @endforeach
</urlset>
@endif
