<details class="rounded border p-3">
    <summary class="cursor-pointer py-2">{{ __('Catalogue source and coordinate assumptions') }}</summary>
    <div class="mt-3 space-y-3 break-words">
        <p>{{ $source['attribution'] }} · <a class="underline" href="{{ $source['source_url'] }}">{{ __('Source data') }}</a> · <a class="underline" href="{{ $source['license_url'] }}">{{ $source['license'] }}</a></p>
        <p>{{ __('Input direction:') }} {{ $source['input_coordinates']['ra_deg'] }}°, {{ $source['input_coordinates']['dec_deg'] }}° · {{ $source['input_coordinates']['frame'] }} · {{ __('Julian reference epoch:') }} {{ $source['input_coordinates']['reference_epoch_jyear'] }}. {{ __('Individual observation epoch and physical distance are unknown.') }}</p>
        <p>{{ __('Motion model:') }} {{ $source['motion_model'] }}. {{ $source['frame_transform'] }}.</p>
        @if($source['proper_motion_applied'])<p>{{ __('Proper motion (RA × cos declination, declination), arcseconds/year:') }} {{ $source['pm_ra_cosdec_arcsec_per_year'] }}, {{ $source['pm_dec_arcsec_per_year'] }}.</p>@endif
        <p>{{ $source['accuracy_note'] }}</p>
        <p>{{ __('Retrieved:') }} {{ $source['retrieved_at'] }} · <a class="underline" href="{{ $source['astrometry_evidence']['query_url'] }}">{{ __('Coordinate evidence') }}: {{ $source['astrometry_evidence']['authority'] }}</a></p>
        <p class="break-all">{{ __('Source snapshot SHA-256:') }} <code>{{ $source['snapshot_sha256'] }}</code></p>
    </div>
</details>
