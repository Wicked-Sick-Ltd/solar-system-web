@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ config('site.name') }} — {{ __('What’s new') }}</title>
        <link>{{ $pageUrl }}</link>
        <description>{{ __('Shipped improvements to Public Universe.') }}</description>
        <lastBuildDate>{{ $updatedRss }}</lastBuildDate>
        <atom:link href="{{ $feedUrl }}" rel="self" type="application/rss+xml"/>
        @foreach ($entries as $entry)
            <item>
                <title>{{ $entry['summary'] }}</title>
                <link>{{ $entry['url'] }}</link>
                <guid isPermaLink="true">{{ $entry['url'] }}</guid>
                <pubDate>{{ \Illuminate\Support\Carbon::parse($entry['merged_at'])->utc()->toRssString() }}</pubDate>
                <category>{{ $entry['type'] }}</category>
                <description>{{ $entry['summary'] }}</description>
            </item>
        @endforeach
    </channel>
</rss>
