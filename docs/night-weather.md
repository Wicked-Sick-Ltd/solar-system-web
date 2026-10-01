# Optional hourly weather for a selected night interval

This change adds an explicit forecast lookup, separate from geometric astronomy.
It does not rank observing windows, estimate astronomical seeing, or guarantee
visibility. No forecast is requested by opening the planner or computing a night.

## Integration contract

Send a normal CSRF-protected form POST to named route `observe.night.weather`
(`/observe/night/weather`) only after the visitor chooses to request weather.
Fields are `lat`, `lon`, `window_start_utc`, `window_end_utc`, and the normal
`_token`. JSON clients use the same fields and the CSRF header, with
`Accept: application/json`. Never put coordinates in a query string.

The interval must have exact `YYYY-MM-DDTHH:MM:SSZ` endpoints, be positive and
no longer than 26 hours. That bound includes local nights crossing a two-hour
DST change. The astronomy calculation owns local-time conversion; this service
never reinterprets timestamps in another zone. Coordinates must be finite and
within geographic bounds before rounding to two decimal places.

Requests are limited to 4 KiB before JSON decoding, reject other fields and
query parameters, and are throttled to 12 per minute. They create no account,
location record or session input flash. Rounded coordinates are sent to
Open-Meteo and used in the server forecast cache. They are not anonymous to the
provider. Responses, including validation, CSRF and throttle failures, have
`Cache-Control: private, no-store` and `Referrer-Policy: no-referrer`.
Normal server access logs must not be configured to capture private POST bodies.

The native result view is `observing.night-weather`. The embeddable result
partial `observing.partials.night-weather` takes a `forecast` array with the JSON
shape below. Parent planner integration supplies an explicit action and carries
its already selected interval. This slice does not edit the planner form or JS.

```text
schema_version: 1
status: available | partial | unknown | out_of_range | unavailable
observer: {lat, lon}                         # rounded request coordinates
window_start_utc, window_end_utc             # unchanged accepted interval
source: {name, url, license_url, fetched_at_utc,
         requested_forecast_days: 7,
         returned_start_utc, returned_end_utc_exclusive}
units: {cloud_cover: %, relative_humidity_2m: %, wind_speed_10m: m/s, visibility: m}
hours: [{time_utc, status, cloud_cover, relative_humidity_2m,
         wind_speed_10m, visibility}]
```

Each row is the source sample at the start of an overlapping UTC hour. The
first row can precede the exact interval start within its containing hour;
there is no interpolation or substitution from a different hour. At most 27
rows are returned. Zero remains zero; missing fields or samples are null.
`available` means all four requested values are reported, not good conditions.
`partial` means incomplete fields or mixed row statuses. `unknown` means a
matching covered hour has no reported values. `out_of_range` means a past hour,
an hour beyond the requested seven-day horizon, or outside the returned time
bounds. Interior gaps are unknown, not interpolated. `unavailable` means the
provider failed or its payload failed validation. A wholly failed lookup is
HTTP 503; valid unknown or out-of-range results are HTTP 200; invalid input is
422 (oversized body 413, unsupported content type 415).

The returned bounds describe the snapshot's time extent, not continuous data
coverage or a promise all seven days are available. Retrieval time is retained
on a cache hit and is not labelled as model issue time. Past source rows are
suppressed so forecasts cannot appear to be historical observations.

## Shared provider snapshot

`OpenMeteoClient::hourlyForecast()` returns one validated snapshot used by both
this endpoint and the existing observer's `tonightOutlook()`. The latter keeps
its reference-hour matching and existing output/threshold semantics. Sequential
requests sharing rounded coordinates reuse the snapshot for the configured
cache duration (default 30 minutes). Only scalar arrays are cached, preserving the secure no-object-unserialization
cache policy; every retrieved snapshot is validated before DTO reconstruction.
The cache key includes API base URL, UTC
date, units/schema version and rounded coordinates, so a changed provider or
UTC day cannot reuse an old horizon. A per-key cache lock (HTTP timeout plus five seconds) deduplicates simultaneous
cold lookups; competing callers receive unavailable rather than waiting or
starting another provider request. Failed fetches are cached for 60 seconds,
then a retry is allowed. The shared cache driver must support atomic locks. No per-hour provider calls are made. Redirects are disabled and a 64 KiB decoded
response sink bounds compressed and uncompressed bodies before JSON parsing.

The primary [Open-Meteo forecast documentation](https://open-meteo.com/en/docs)
specifies a seven-day default, UTC hourly timestamps, configurable forecast
days, and explicit m/s wind units. We request seven days and at most 168
hourly samples, retaining the existing aligned-array, finite-value, unit and
UTC validation. The [data licence](https://open-meteo.com/en/licence) requires
attribution under CC BY 4.0; result views credit Open-Meteo and link the licence.
No credentials or paid API have been added.

## Offline checks

```bash
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan test tests/Feature/NightWeatherTest.php tests/Unit/OpenMeteoClientTest.php tests/Unit/WeatherTransportTest.php
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=512M
```

HTTP fakes cover cache reuse, changed provider/day, exact-hour gaps, nullable
values and zero, shorter returned coverage, past/future exclusions, UTC midnight,
a 25-hour DST night, 26-hour limit, malformed raw types/dates, provider failure
and retry, native HTML, CSRF, throttling, size and privacy headers. No real
location or live forecast request is used.

The transport test starts only a local loopback HTTP fixture to prove the plain
and gzip decoded-body bound. The cache test uses an isolated temporary file
store with the production no-object-unserialization setting.
