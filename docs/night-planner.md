# Moon and planet night planner

`GET /observe/night` renders a guest form. `POST /observe/night` validates a
calendar date, IANA time zone, rounded latitude/longitude, one to eight supported
bodies, optional exact UTC observing hours, and altitude/darkness/Moon/terrain
constraints, then makes **one JSON POST** request
to `/api/v1/observing/night`. No per-sample API calls, persistent cache, automatic
retry, account upload or session old-input flashing is involved. Responses,
including errors and throttling, use private/no-store and no-referrer headers.
The POST route permits six requests per minute under the standard route throttle.

Requires backend constraints support in database PR #37.
An older API, unsupported date, unavailable Earth-orientation coverage or malformed
response produces an unavailable result, never a legacy lunar substitute.
`SOLAR_PLANNER_TIMEOUT` defaults to 40 seconds and is bounded to 40–60 seconds,
allowing the backend's 35-second isolated JPL worker to finish or report failure.
A Guzzle sink rejects decoded response writes above 1.5 MB before retaining the
excess body. This also limits compressed responses after decoding. Redirects are
disabled so private requests are not forwarded elsewhere. A final envelope check
also protects fake/custom transports. Real loopback tests cover valid, oversized,
gzip and redirect responses without astronomy or external-network dependencies.

The strict consumer verifies schema, requested observer/night/constraints, local
noon boundaries including DST, coverage metadata, target identity/order, finite
angle bounds, ordered non-overlapping windows and complete aligned sample grids.
Effective observing hours must match exactly; returned windows stay inside them.
The returned canonical horizon must match the request, and each sample's terrain
and required-altitude values are independently checked by circular interpolation.
Status and empty-window consistency are checked. An unresolved grazing crossing
with no confirmed interval remains unresolved in the wording.

The page presents local times with seconds and UTC offsets, Moon illumination, darkness,
per-target model windows, a server-rendered altitude chart and the complete
accessible sample table. Provider/frame/refraction and Earth-orientation prediction
status remain visible. Selected hours are shaded on full-night charts and the
altitude threshold is dashed. Sample tables can be focused and scrolled by
keyboard. A browser-saved site can explicitly copy coordinates, timezone,
baseline and horizon into the form, without activating or changing its profile.
Manual fields work without JavaScript. Blank terrain remains unknown; Moon
interference uses its geometric altitude above zero, independent of that mask.
Equipment suggestions, weather, session exports and starter star/deep-sky
planning remain tracked follow-ups.

## Validation fixture

`tests/fixtures/observing/night.json` is a local offline engine response generated
on 2026-10-01 from the implementation subsequently committed as backend `c8d6cd9`:
2026-10-01, Europe/London, 51.50/-0.12, Moon and Saturn, minimum altitude20°, Sun
below−12°, Moon separation0°. It is a contract fixture, not independent scientific
truth. The backend contains separately retrieved official JPL Horizons reference
fixtures and its calculation validation. No private observing location is used.
For the additive constraints contract, this fixture now explicitly records its
full-night effective interval, unknown horizon and existing20°baseline in each
target sample. Original positions and window measurements are unchanged.

Tests exercise one-call batching, malformed contracts, unavailable backends,
invalid dates/coordinates/constraints, private headers, escaped upstream labels,
form correction and explicitly unresolved crossings. Browser QA used the same
synthetic central-London coordinates with Moon, Jupiter and Saturn and verified
real form submission, model windows and readable tables/charts.
