@php
    $labels = ['available' => __('Reported'), 'partial' => __('Partly reported'), 'unknown' => __('Unknown'), 'out_of_range' => __('Outside forecast coverage'), 'unavailable' => __('Forecast unavailable')];
@endphp
<section class="surface space-y-4 p-5" aria-labelledby="night-weather-heading">
    <h2 id="night-weather-heading" class="text-2xl">{{ __('Hourly forecast') }} · {{ $labels[$forecast['status']] }}</h2>
    <p>{{ __('Selected UTC interval:') }} {{ $forecast['window_start_utc'] }} → {{ $forecast['window_end_utc'] }} · {{ __('Rounded location:') }} {{ $forecast['observer']['lat'] }}°, {{ $forecast['observer']['lon'] }}°.</p>
    <p>{{ __('Forecast values are hourly samples, matched to each overlapping UTC hour without interpolation. A dash means unknown, never zero. Past hours and hours outside the returned forecast have no forecast here.') }}</p>
    <p>{{ __('These forecasts do not change the geometric observing windows. Cloud cover, humidity, wind and horizontal visibility do not measure astronomical seeing or guarantee a useful view.') }}</p>
    @if($forecast['source']['fetched_at_utc'])
        <p>{{ __('Retrieved:') }} <time datetime="{{ $forecast['source']['fetched_at_utc'] }}">{{ $forecast['source']['fetched_at_utc'] }}</time>. {{ __('Retrieval time is not the model issue time.') }}</p>
        <p>{{ __('Returned UTC coverage:') }} {{ $forecast['source']['returned_start_utc'] }} → {{ $forecast['source']['returned_end_utc_exclusive'] }} {{ __('(end exclusive; individual hours or values may be missing).') }}</p>
    @else
        <p>{{ __('No forecast snapshot is available for this interval. No historical or distant-future weather has been substituted.') }}</p>
    @endif
    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('Hourly forecast table; scroll horizontally for all weather columns') }}">
        <table class="w-full text-left text-sm tabular-nums">
            <caption class="py-3 text-left">{{ __('UTC hours and source units. The first sample may precede the exact interval start within its containing hour.') }}</caption>
            <thead><tr>@foreach(['UTC hour', 'Coverage', 'Cloud %', 'Humidity %', 'Wind at 10 m (m/s)', 'Horizontal visibility (m)'] as $heading)<th scope="col" class="p-2">{{ __($heading) }}</th>@endforeach</tr></thead>
            <tbody>@foreach($forecast['hours'] as $hour)<tr>
                <th scope="row" class="p-2 font-normal"><time datetime="{{ $hour['time_utc'] }}">{{ $hour['time_utc'] }}</time></th>
                <td class="p-2">{{ $labels[$hour['status']] }}</td>
                @foreach(['cloud_cover', 'relative_humidity_2m', 'wind_speed_10m', 'visibility'] as $field)<td class="p-2">{{ $hour[$field] === null ? '—' : $hour[$field] }}</td>@endforeach
            </tr>@endforeach</tbody>
        </table>
    </div>
    <p class="text-sm">{{ __('Weather data:') }} <a href="https://open-meteo.com/" class="underline">Open-Meteo</a> · <a href="https://creativecommons.org/licenses/by/4.0/" class="underline">CC BY 4.0</a>. {{ __('A seven-day forecast is requested; actual coverage may be shorter. Check a current local forecast before travelling.') }}</p>
</section>
