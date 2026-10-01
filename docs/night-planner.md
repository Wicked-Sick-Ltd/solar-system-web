# Moon and planet night planner

`GET /observe/night` renders a guest form. `POST /observe/night` validates a
calendar date, IANA time zone, rounded latitude/longitude, one to eight supported
bodies and explicit altitude/darkness/Moon constraints, then makes **one** request
to `/api/v1/observing/night`. No per-sample API calls, persistent cache, automatic
retry, account upload or session old-input flashing is involved. Responses,
including errors and throttling, use private/no-store and no-referrer headers.
The POST route permits six requests per minute under the standard route throttle.

Requires the backend observing engine (stacked after database PR #32).
An older API, unsupported date, unavailable Earth-orientation coverage or malformed
response produces an unavailable result, never a legacy lunar substitute.
`SOLAR_PLANNER_TIMEOUT` defaults to 30 seconds and is bounded to 20–60 seconds.
The 1.5 MB envelope check rejects oversized **buffered** responses. It is not yet
a streaming transport/memory cap; that remains performance work P5.

The strict consumer verifies schema, requested observer/night/constraints, local
noon boundaries including DST, coverage metadata, target identity/order, finite
angle bounds, ordered non-overlapping windows and complete aligned sample grids.
Status and empty-window consistency are checked. An unresolved grazing crossing
with no confirmed interval remains unresolved in the wording.

The page presents local times with UTC offsets, Moon illumination, darkness,
per-target model windows, a server-rendered altitude chart and the complete
accessible sample table. Provider/frame/refraction and Earth-orientation prediction
status remain visible. This first slice does not yet connect equipment, clip to
selected available hours, include weather, export sessions or incorporate the
starter star/deep-sky catalogue; those remain tracked in the programme.

## Validation fixture

`tests/fixtures/observing/night.json` is a local offline engine response generated
on 2026-10-01 from the implementation subsequently committed as backend `c8d6cd9`:
2026-10-01, Europe/London, 51.50/-0.12, Moon and Saturn, minimum altitude20°, Sun
below−12°, Moon separation0°. It is a contract fixture, not independent scientific
truth. The backend contains separately retrieved official JPL Horizons reference
fixtures and its calculation validation. No private observing location is used.

Tests exercise one-call batching, malformed contracts, unavailable backends,
invalid dates/coordinates/constraints, private headers, escaped upstream labels,
form correction and explicitly unresolved crossings. Browser QA used the same
synthetic central-London coordinates with Moon, Jupiter and Saturn and verified
real form submission, model windows and readable tables/charts.
