<div class="space-y-2 rounded border p-4" data-constraint-coverage>
    <h3 class="font-semibold">{{ __('Individual constraints in the selected interval') }}</h3>
    <p><time datetime="{{ $coverage['start_utc'] }}">{{ $local($coverage['start_utc']) }}</time> – <time datetime="{{ $coverage['end_utc'] }}">{{ $local($coverage['end_utc']) }}</time></p>
    <dl class="space-y-2">
        <div><dt class="font-semibold">{{ __('Required altitude') }}</dt><dd>{{ __(match ($coverage['altitude']) {
            'always_satisfied' => 'Satisfied throughout this interval.',
            'never_satisfied' => 'No resolved part of this interval satisfies this constraint.',
            'partial' => 'Satisfied during part of this interval.',
            'unresolved' => 'Coverage remains unresolved in this interval; always or never cannot be established.',
        }) }}</dd></div>
        <div><dt class="font-semibold">{{ __('Chosen darkness threshold') }}</dt><dd>{{ __(match ($coverage['darkness']) {
            'always_satisfied' => 'Satisfied throughout this interval.',
            'never_satisfied' => 'No resolved part of this interval satisfies this constraint.',
            'partial' => 'Satisfied during part of this interval.',
            'unresolved' => 'Coverage remains unresolved in this interval; always or never cannot be established.',
        }) }}</dd></div>
    </dl>
    <p class="text-sm">{{ __('Altitude uses the larger of your minimum altitude and any supplied horizon. These independent diagnostics use refined boundaries; grazing, ambiguous directions or intervals below numerical tolerance can remain unresolved. They apply only to the times shown and do not establish permanent rise/set behaviour. Sun and Moon separation and other constraints still determine the combined windows; satisfying altitude and darkness does not guarantee visibility.') }}</p>
</div>
