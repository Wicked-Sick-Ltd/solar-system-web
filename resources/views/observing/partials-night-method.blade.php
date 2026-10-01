<details class="rounded border p-3">
    <summary class="cursor-pointer py-2">{{ __('Software and ephemeris identity') }}</summary>
    <div class="mt-3 space-y-3 break-words">
        <p>Astropy {{ $method['astropy_version'] }} · ERFA {{ $method['erfa_version'] }} @if(isset($method['jplephem_version'])) · jplephem {{ $method['jplephem_version'] }} @endif</p>
        <p>{{ __('Earth-orientation release:') }} {{ $method['iers']['data_version'] }} · {{ $method['iers']['start_utc'] }} → {{ $method['iers']['end_utc'] }}.</p>
        <p class="break-all">{{ __('Earth-orientation effective-column SHA-256:') }} <code>{{ $method['iers']['snapshot']['sha256'] }}</code></p>
        @if(isset($method['kernel']))
            <p><a class="underline" href="{{ $method['kernel']['source_url'] }}">{{ $method['kernel']['name'] }}</a> · {{ $method['kernel']['size_bytes'] }} {{ __('bytes') }}</p>
            <p class="break-all">{{ __('Kernel SHA-256:') }} <code>{{ $method['kernel']['sha256'] }}</code></p>
            <p>{{ __('Kernel coverage (TDB, not UTC):') }} {{ $method['kernel']['start_tdb'] }} → {{ $method['kernel']['end_tdb'] }}.</p>
        @endif
        <p>{{ __('Hashes identify the reported inputs; they do not certify positional accuracy. Retain matching source snapshots and software to repeat a calculation.') }}</p>
    </div>
</details>
