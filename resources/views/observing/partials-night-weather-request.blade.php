<section class="surface my-6 space-y-3 p-5 print:hidden" aria-labelledby="night-weather-heading">
    <h2 id="night-weather-heading" tabindex="-1" class="text-xl">{{ __('Weather for this observing interval') }}</h2>
    <p>{{ __('Weather is separate from your calculated observing windows. Request an hourly forecast for the selected UTC interval; dates outside forecast coverage remain unknown. Cloud, humidity, wind and horizontal visibility do not measure astronomical seeing or guarantee a useful view.') }}</p>
    <p>{{ __('Only when you choose the button below, your rounded coordinates will be sent through this server to Open-Meteo and used in this server’s forecast cache. No equipment or journal data is sent. The forecast opens in a new tab so this calculation and your temporary equipment stay here.') }}</p>
    <form method="POST" action="{{ route('observe.night.weather') }}" target="_blank" rel="noopener" data-night-weather-request>
        @csrf
        <input type="hidden" name="lat" value="{{ $plan['observer']['lat'] }}">
        <input type="hidden" name="lon" value="{{ $plan['observer']['lon'] }}">
        <input type="hidden" name="window_start_utc" value="{{ $plan['constraints']['window_start_utc'] }}">
        <input type="hidden" name="window_end_utc" value="{{ $plan['constraints']['window_end_utc'] }}">
        <button type="submit" class="min-h-11 rounded border px-4 py-2">{{ __('Request matching-hour weather') }}</button>
    </form>
</section>
