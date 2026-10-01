<section class="surface mb-6 space-y-3 p-5">
    <h2 class="text-lg font-semibold">{{ __('Coordinate interpretation') }}</h2>
    @if ($target->astrometry === null)
        <p>{{ __('Verified coordinate-frame metadata is unavailable in this catalogue version. The source coordinates remain browsable; a frame or reference epoch has not been inferred.') }}</p>
    @elseif ($target->astrometry->data['status'] === 'unsupported')
        <p>{{ __('Coordinate frame unsupported for planning') }}</p>
        <p>{{ $target->astrometry->data['unsupported_reason'] }}</p>
    @else
        @php($astrometry = $target->astrometry)
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt>{{ __('Verified coordinate frame') }}</dt><dd>{{ $astrometry->data['frame'] }}</dd></div>
            <div><dt>{{ __('Frame equinox') }}</dt><dd>{{ $astrometry->data['equinox'] ?? __('Not applicable to ICRS') }}</dd></div>
            <div><dt>{{ __('Catalogue reference epoch') }}</dt><dd>{{ __('Julian year 2000.0') }}</dd></div>
            <div><dt>{{ __('Individual observation epoch') }}</dt><dd>{{ __('Not reported') }}</dd></div>
            @if ($target->source === 'bsc5p')
                <div><dt>{{ __('RA proper motion, including cosine of declination') }}</dt><dd>{{ $astrometry->properMotion('pm_ra_cosdec_arcsec_per_year') }}</dd></div>
                <div><dt>{{ __('Declination proper motion') }}</dt><dd>{{ $astrometry->properMotion('pm_dec_arcsec_per_year') }}</dd></div>
            @endif
        </dl>
        <p class="text-sm">{{ __('The reference epoch describes when the catalogue position is expressed. It does not establish when individual observations were taken. Equinox describes the orientation of the coordinate axes.') }}</p>
        @if ($astrometry->data['motion_model'] === 'linear_angular_proper_motion')
            <p class="text-sm">{{ __('Angular proper motion is available for a bounded calculation, but has not been applied here. No distance, radial velocity, perspective acceleration or companion orbit is inferred.') }}</p>
        @else
            <p class="text-sm">{{ __('Static catalogue direction: no proper motion has been applied. These coordinates are not a current astrometric prediction.') }}</p>
        @endif
    @endif
    <p class="text-sm">{{ __('This page shows unchanged catalogue coordinates. It does not calculate current sky positions or observability.') }}</p>
</section>
