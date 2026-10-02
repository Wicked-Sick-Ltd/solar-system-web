<details class="rounded border p-3">
    <summary class="cursor-pointer py-2">{{ __('Software and ephemeris identity') }}</summary>
    <div class="mt-3 space-y-3 break-words">
        <p>Astropy {{ $method['astropy_version'] }} · ERFA {{ $method['erfa_version'] }} @if(isset($method['jplephem_version'])) · jplephem {{ $method['jplephem_version'] }} @endif</p>
        @if(isset($method['calculation']))
            <p>Python {{ $method['calculation']['python_version'] }} · NumPy {{ $method['calculation']['numpy_version'] }}</p>
            <p class="break-all">{{ __('Calculation source SHA-256:') }} <code>{{ $method['calculation']['source_sha256'] }}</code></p>
            <p>{{ __('Catalogue targets use the identified packaged snapshots. The separately reported database catalogue identity does not identify these calculation inputs.') }} @if($hasSessionExport ?? true) {{ __('The JSON export retains the source-hash algorithm and file list.') }} @endif</p>
        @else
            <p>{{ __('Calculation source identity was not reported by this backend. Library and data identities below do not identify the complete calculation software.') }}</p>
        @endif
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
