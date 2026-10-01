<section class="surface mt-6 space-y-3 p-5 print:hidden" aria-labelledby="night-session-heading">
    <h2 id="night-session-heading" class="text-xl">{{ __('Keep this observing session') }}</h2>
    <p>{{ __('Download this calculation’s windows, exact inputs and scientific provenance without recalculating or uploading anything. The JSON summary omits chart samples, weather, equipment and actual observations. CSV is a window overview; keep its companion JSON for the assumptions and sources.') }}</p>
    <div data-night-session-controls hidden class="space-y-3">
        <label class="flex min-h-11 items-center gap-3"><input type="checkbox" data-night-session-locations> {{ __('Include rounded coordinates and the terrain profile in downloads and print') }}</label>
        <p>{{ __('These are omitted by default. Dates, timezone and derived windows remain and may still reveal observing context. Exact repetition needs the original location, terrain, source snapshots and software versions.') }}</p>
        <div class="flex flex-wrap gap-3">
            <button type="button" data-night-session-json class="min-h-11 rounded border px-4">{{ __('Download session JSON') }}</button>
            <button type="button" data-night-session-csv class="min-h-11 rounded border px-4">{{ __('Download windows CSV') }}</button>
            <button type="button" data-night-session-print class="min-h-11 rounded border px-4">{{ __('Print this session') }}</button>
        </div>
    </div>
    <noscript>{{ __('Use your browser’s Print command to keep this page. Coordinates are hidden in print by default. JavaScript enables the structured downloads and location choice.') }}</noscript>
    <p data-night-session-status role="status"></p><p data-night-session-error role="alert"></p>
    <script type="application/json" data-night-session-data>{!! json_encode($sessionSummary, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
</section>
