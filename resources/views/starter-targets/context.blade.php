<div class="surface mb-6 space-y-3 p-5 text-sm" style="color: var(--muted);">
    <p>{{ __('A bounded starter sample: 50 bright-star records from HEASARC BSC5P, including 23 records with identified double-star components and historical separations, plus 108 OpenNGC deep-sky records associated with Messier numbers.') }}</p>
    <p>{{ __('These are static catalogue measurements, not current sky positions or a complete census. Double-star records may describe the same pair; a pair is not necessarily a gravitationally bound binary. Separation dates and companion position angles are not available.') }}</p>
    <p>{{ __('Missing measurements are unknown, not zero. Sizes can come from different wavelength bands and do not guarantee an object’s visible extent. No observability or equipment-resolution prediction is made here.') }}</p>
    <p><a href="{{ route('observatory') }}" class="inline-block min-h-11 content-center underline">{{ __('Your equipment workspace') }}</a> · <a href="{{ route('observe') }}" class="inline-block min-h-11 content-center underline">{{ __('Observing tools') }}</a></p>
</div>
