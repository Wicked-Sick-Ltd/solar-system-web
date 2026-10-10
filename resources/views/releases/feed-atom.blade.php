@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>{{ config('site.name') }} — {{ __('What’s new') }}</title>
    <subtitle>{{ __('Shipped improvements to Public Universe.') }}</subtitle>
    <id>{{ $pageUrl }}</id>
    <link rel="alternate" type="text/html" href="{{ $pageUrl }}"/>
    <link rel="self" type="application/atom+xml" href="{{ $feedUrl }}"/>
    <updated>{{ $updatedAtom }}</updated>
    @foreach ($entries as $entry)
        <entry>
            <title>{{ $entry['summary'] }}</title>
            <id>{{ $entry['url'] }}</id>
            <link rel="alternate" type="text/html" href="{{ $entry['url'] }}"/>
            <updated>{{ \Illuminate\Support\Carbon::parse($entry['merged_at'])->utc()->toAtomString() }}</updated>
            <published>{{ \Illuminate\Support\Carbon::parse($entry['merged_at'])->utc()->toAtomString() }}</published>
            <category term="{{ $entry['type'] }}"/>
            <summary>{{ $entry['summary'] }}</summary>
        </entry>
    @endforeach
</feed>
