{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($urls as $item)
    <url>
        <loc>{{ $item['url'] }}</loc>
        @if($item['lastmod'])<lastmod>{{ $item['lastmod']->toAtomString() }}</lastmod>@endif
        <priority>{{ $item['priority'] }}</priority>
    </url>
@endforeach
</urlset>
