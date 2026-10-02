# Observing night planner

`GET /observe/night` renders a guest form. `POST /observe/night` validates a
calendar date, IANA time zone, rounded latitude/longitude, one to eight supported
Moon, planet or catalogue targets, optional exact UTC observing hours, and altitude/darkness/Moon/terrain
constraints, then makes **one JSON POST** request
to `/api/v1/observing/night`. No per-sample API calls, persistent cache, automatic
retry, account upload or session old-input flashing is involved. Responses,
including errors and throttling, use private/no-store and no-referrer headers.
The POST route permits six requests per minute under the standard route throttle.

Requires backend constraints and catalogue planning support in database PR #39.
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
The result integrates [local equipment comparison](observing-equipment-suggestions.md)
and a separate [optional weather request](night-weather.md). Equipment selection
does not change the geometric target list or upload profiles. Weather opens in a
new tab so the private POST result and temporary equipment remain available.

## Validation fixture

`tests/fixtures/observing/night.json` is a local offline engine response generated
from backend `c4f618ab6f0503d871e417680a9a008cd1a29dec`:
2026-10-01, Europe/London, 51.50/-0.12, Moon and Saturn, minimum altitude20°, Sun
below−12°, Moon separation0°. It is a contract fixture, not independent scientific
truth. The backend contains separately retrieved official JPL Horizons reference
fixtures and its calculation validation. No private observing location is used.
This refreshed whole response records the full-night effective interval, unknown
horizon, baseline and exact provider/IERS provenance. Earlier derived fixtures
remain in Git history; no identity was retroactively assigned to their values.

Tests exercise one-call batching, malformed contracts, unavailable backends,
invalid dates/coordinates/constraints, private headers, escaped upstream labels,
form correction and explicitly unresolved crossings. Browser QA used the same
synthetic central-London coordinates with Moon, Jupiter and Saturn and verified
real form submission, model windows and readable tables/charts.


## Catalogue targets and session summaries

The form accepts up to eight total Moon/planet targets and exact starter catalogue
identifiers. Native catalogue links preselect public target IDs only; date and
location remain empty and no calculation starts until submission. IDs are never
case-folded, fuzzy-matched or silently removed. Unknown catalogue membership or
unsupported coordinates produce an unavailable result, without substitute targets.

Calculated catalogue directions retain verified FK5/ICRS metadata, source and
evidence hashes, source-specific attribution/licensing, the stated angular-motion
model and explicit missing physical distances. Provider details retain the actual
Astropy/ERFA versions, IERS effective-column hash and, where configured, the JPL
kernel identity. These are reported identities, not signatures or accuracy claims.
The builtin two-target fixture was wholly refreshed from backend
`c4f618ab6f0503d871e417680a9a008cd1a29dec`; its original request/date remains the same.
`catalogue-night.json` retains Moon, HR2491 and NGC0224 from the actual eight-target
JPL response from that revision, without changing any coordinates or metadata.

Session JSON is a bounded160KB summary of the exact normalized request, effective
constraints, windows, units, UTC/timezone and scientific provenance. It deliberately
omits chart samples, weather, gear and actual observations. CSV is a window index
with second-resolution UTC times, unresolved states and spreadsheet-formula
protection; retain its companion JSON for full assumptions. Both formats are made
locally from the validated result, with no recalculation, upload or local-storage
write. No import or executable replay is implied by a downloaded input record.

Coordinates and terrain are omitted from downloads and print by default. An
explicit checkbox includes them; dates, timezone and derived windows remain even
without that choice and can reveal observing context. Print uses paper colours
without changing the saved theme. Exact repetition needs the original private
inputs, source snapshots, ephemeris/IERS data and compatible software/timezone
rules. The live API is not an immutable archive; no global catalogue identity was
attached atomically to this night calculation. Per-target snapshots remain useful
without making that stronger claim.


Newer responses optionally include `method.calculation`: bounded public source
file names, an algorithm-source hash and Python/NumPy versions. The consumer
validates and preserves it in session JSON; older responses display that it was
not reported. This is distinct from provider/kernel/IERS and per-target source
identities, not an executed-code signature. Backend `docs/OBSERVING-REPLAY.md`
describes retained builtin/JPL fixture checks requiring matching identities and
compatible private inputs. A redacted session summary is not enough to replay a
calculation, and a hash does not retrieve missing software or ephemeris files.
