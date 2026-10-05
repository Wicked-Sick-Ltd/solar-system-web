<section class="surface space-y-3 p-5 text-sm">
    <h2 class="text-lg font-semibold">{{ __('Source and reuse') }}: {{ $source->data['source'] }}</h2>
    <p>{{ $source->data['attribution'] }}</p>
    <p><a href="{{ $source->data['source_url'] }}" class="underline">{{ __('Original source / pinned release') }}</a> · <a href="{{ $source->data['license_url'] }}" class="underline">{{ $source->data['license'] }}</a></p>
    @if ($source->data['source'] === 'openngc')
        <p>{{ __('OpenNGC data and adaptations retain CC BY-SA 4.0: credit the authors, indicate changes and share adaptations under the same licence. The website’s MIT software licence does not replace these data terms.') }}</p>
    @else
        <p>{{ __('Credit Hoffleit and Warren and NASA/GSFC HEASARC when reusing these records. Retain the source flags and historical measurement limitations.') }}</p>
    @endif
    <dl class="space-y-2">
        <div><dt class="font-medium">{{ __('Selection') }}</dt><dd>{{ $source->data['selection'] }}</dd></div>
        <div><dt class="font-medium">{{ __('Retrieved') }}</dt><dd class="break-all">{{ $source->data['retrieved_at'] }}</dd></div>
        <div><dt class="font-medium">{{ __('Reviewed subset SHA256') }}</dt><dd class="break-all font-mono text-xs">{{ $source->data['snapshot_sha256'] }}</dd></div>
    </dl>
    <details><summary class="cursor-pointer py-2">{{ __('Original file hashes') }}</summary>
        <dl class="mt-2 space-y-2">@foreach ($source->data['upstream_sha256'] as $file => $hash)<div><dt>{{ $file }}</dt><dd class="break-all font-mono text-xs">{{ $hash }}</dd></div>@endforeach</dl>
    </details>
    @isset($source->data['astrometry_evidence'])
        @php($evidence = $source->data['astrometry_evidence'])
        <details><summary class="cursor-pointer py-2">{{ __('Coordinate-frame evidence') }}</summary>
            <p class="mt-2"><a href="{{ $evidence['query_url'] }}" class="underline">{{ $evidence['authority'] }} · {{ $evidence['dataset'] }}</a></p>
            <p>{{ __('Exact source identifiers matched') }}: {{ $evidence['matched_records'] }}. {{ __('Evidence retrieved') }}: {{ $evidence['retrieved_at'] }}.</p>
            @if ($evidence['unsupported_identifiers'] !== [])<p>{{ __('Without matching frame evidence') }}: {{ implode(', ', $evidence['unsupported_identifiers']) }}.</p>@endif
            <dl class="mt-2"><dt>{{ __('Pinned response SHA256') }}</dt><dd class="break-all font-mono text-xs">{{ $evidence['response_sha256'] }}</dd></dl>
        </details>
    @endisset
</section>
