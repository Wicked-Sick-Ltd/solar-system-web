@php
    use App\Support\Format;
@endphp

<section class="surface p-6" aria-labelledby="quality-heading">
    <h2 id="quality-heading" class="mb-1 font-serif text-xl font-medium">{{ __('Orbit quality') }}</h2>
    @if ($orbital->conditionReading())
        <p class="mb-3 text-sm" style="color: var(--muted);">{{ __('Orbit :reading (uncertainty code :code).', ['reading' => $orbital->conditionReading(), 'code' => $orbital->conditionCode]) }}</p>
    @endif
    <dl>
        <x-prop-row :label="__('Observations used')" :value="$orbital->nObsUsed !== null ? Format::count($orbital->nObsUsed) : null" />
        <x-prop-row :label="__('Data arc')" :value="$orbital->dataArcDays !== null ? Format::periodDays($orbital->dataArcDays) : null" />
        <x-prop-row :label="__('First observed')" :value="Format::date($orbital->firstObs)" />
        <x-prop-row :label="__('Last observed')" :value="Format::date($orbital->lastObs)" />
        <x-prop-row :label="__('Residual RMS')" :value="Format::unit($orbital->rmsArcsec, '″', 2)" />
        <x-prop-row :label="__('Solution')" :value="Format::date($orbital->solutionDate)" :hint="$orbital->producer" />
    </dl>
</section>
