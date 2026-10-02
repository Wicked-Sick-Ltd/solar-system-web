# Refined constraint explanations in Chrome

Native Chrome checks on 2 October 2026 against combined web `c36de8a`, local
server `18026`, and stable backend `2dfc478` on `18010`. Calculations used the
checksum-pinned JPL DE440s provider and synthetic London coordinates 51.5, −0.12,
Europe/London, 2026-10-24. The local night correctly spans 25 hours over the
clock change. Minimum altitude was 20°, darkness threshold −12°, Moon separation
0°, no horizon mask, and no weather request.

- M31 (`openngc:NGC0224`), UTC 15:30–16:00: altitude satisfied throughout the
  selected interval; no resolved part satisfied darkness. No combined window.
- M31, UTC 20:00–21:00: both requirements satisfied throughout, with the full
  one-hour combined window. The displayed interval was 21:00–22:00 +01:00.
- Sirius (`bsc5p:hr2491`), UTC 20:00–21:00: no resolved part satisfied altitude;
  darkness satisfied throughout. No combined window. The target edit was
  explicitly verified in the native form before submission.

The explanations scoped themselves to the selected interval, distinguished the
independent constraints from combined windows and did not claim permanent
rise/set behaviour. Inputs remained in a private POST; the result URL contained
no coordinates. Screenshots were inspected and retained:

![M31 during the selected daytime interval](constraint-daytime-m31.jpg)

![Sirius below the requested altitude during darkness](constraint-below-sirius.jpg)

## Verification and remaining limits

At `c36de8a`, the combined branch passed 1,327 PHP tests / 5,924 assertions with
inherited `APP_DEBUG=true`, 247 JavaScript tests, Pint, PHPStan and the production
build. The build retains its existing large optional map-chunk warning; asset
budgets remain independently enforced. Independent reviewers checked the new
consumer and export behaviour, including inconsistent partial-darkness input.

A final native Print this session attempt timed out in browser control; available
native app inspection did not expose that browser instance's print dialog. No
print job was submitted. Escape returned control to the ordinary page. The
current revision's printed appearance/privacy is **not verified** by this attempt;
the earlier three-page preview remains evidence only for its recorded revision.

A separate reviewer repeated one scoped attempt against the same current local
runtime: M31, UTC 20:00–21:00, with an explicit two-point terrain profile. The
calculation and both throughout-interval diagnostics appeared, but Print again
timed out and native Chrome inspection exposed an unrelated window. The reviewer
stopped without acting on that window. This reproduces the preview-observation
limitation; it supplies no new print artifact or permission to change browser
settings.

A fresh Download session JSON event wait also timed out. This does not prove
download success or failure, and no artifact from that attempt is certified.
The current JSON retention/redaction tests pass; earlier inspected native
downloads remain scoped to their recorded revisions. These timeouts are separate
from the previously reported file-import permission and exoplanet attachment
restrictions; no restriction was changed or bypassed.

## User-assisted print recovery

The user brought the existing `18026/observe/night` tab forward and reported an
open print preview, then saved `Plan a night · Public Universe.pdf` to Downloads.
The 59,163-byte, three-page A4 Chrome/Skia PDF was extracted with Poppler and all
three rendered pages were visually inspected. Its content matches the retained
Sirius (`bsc5p:hr2491`) case: 24 October, UTC 20:00–21:00, altitude unsatisfied,
darkness satisfied, terrain unknown. The live page still has the location/terrain
opt-in unchecked. This recovers the earlier preview's artifact; it does not
claim a fresh calculation or a supplied-terrain print check.

The target curve, minimum-altitude line, selected-interval shading, constraint
explanations and scientific limits are readable. Printed text omits the test
coordinates 51.5 / −0.12; date and Europe/London timezone remain, as disclosed.
The weather note spills onto an otherwise empty third page, leaving pagination
polish outstanding. Collapsed source/sample/identity disclosures remain collapsed
in this artifact. No hardware print job was sent.

Artifact SHA-256: `8e51513e1cb85f28357440d0f987b7d4794693aa8b3f44466566b3ceff3d646a`.
Saving the PDF restored normal tab inspection. A following automation click for
session JSON timed out again; its browser download has not yet been certified.

## User-assisted session JSON recovery

The user clicked Download session JSON on the same retained Sirius result and
saved `public-universe-night-2026-10-24.json` to Downloads. The artifact is 8,842
bytes, SHA-256 `0fc47bb8ff16d56ab5f9e2a80ac778c4450c0bc1318bded00f29191c750bc9a8`. JSON parsing and explicit
content assertions passed: `location_included=false`, `inputs_complete=false`,
no observer coordinates or terrain fields in inputs/constraints, the 25-hour
night and selected UTC interval, no combined windows, and refined independent
coverage (`altitude=never_satisfied`, `darkness=always_satisfied`). JPL kernel
checksum, IERS snapshot, calculation-source identity and target source provenance
are retained. Stellar RA/Dec are catalogue data, not observer-location leakage.

This verifies the native default session JSON download for the retained
`2026-10-02T07:40:57Z` calculation. It does not establish the opt-in terrain case
or CSV output. Browser automation's click timeout was not a download defect in
this manually completed journey.

## Revised print layout: user-assisted visual check

After integration `457ef2b` and a production asset rebuild, the user calculated
a new night and saved over the previous PDF. The resulting 108,860-byte Chrome
PDF was created at 15:50:39 BST, rendered with Poppler and visually inspected on
all four A4 pages. Its SHA-256 is
`8cf1079e65e47fc69fdccba5c7543a73ffa05723749d1737ec7b9b28420375ea`.

This is a different workload: 2 October, full 24-hour local night, Moon/Jupiter/
Saturn, supplied terrain, and Chrome page headers/footers enabled. Each independent
constraint explanation stays together; the three charts and legends are readable
and unclipped; the calculation/limits block includes its final weather note on
one page. There is no weather-note-only page. Coordinates and terrain point
values remain omitted from printed text. The footer contains only the local
planner route, with no private query values.

The result verifies the revised layout for this three-target workload. Because
the inputs and print headers changed, it does not establish a like-for-like
page-count reduction for the earlier Sirius case. Target cards can span pages;
closed sample/source disclosures retain their existing closed state.
