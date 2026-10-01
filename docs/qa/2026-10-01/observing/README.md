# Observing browser checks — 1 October 2026

Captured with the real Chrome browser against local isolated servers, synthetic
central-London coordinates and QA-named equipment. No customer account or actual
personal observing site was used. Integration checkpoint8f380d3 contains workspace
b7e1f79, opticsc533ad7 and night planner1cfcf2d. Backend engine served read-only
fixtures on18004; no catalogue ingestion or production requests.

Confirmed in browser: telescope and eyepiece creation; site coordinate rounding,
explicit activation and reload persistence; optical example1000mm/20mm gives50×,
200mm aperture gives4mm exit pupil,50°AFOV gives approximate1°true field; entered
30arcminutes is50% of the field diameter. Desktop and390×844viewport override
screenshots show the result and usable narrow controls. DOM client/page widths
both375px (scrollbar excluded), with no horizontal overflow. Viewport restored.

Real night-form POST for2026-10-01 Europe/London51.50/−0.12 returned Moon,
Jupiter and Saturn windows with model/IERS prediction disclosures. The original
chart screenshot prompted clearer altitude axis labels before commit; a final
night screenshot is still pending.

Journal checks: created a synthetic list and Saturn observation with selected
telescope snapshot; reload retained both. Downloaded JSON through Chrome's
native Save dialog and inspected the resulting file: one list and one
observation, the selected equipment, and no site coordinates. Desktop and
390×844 mobile screenshots show the saved observation; client/page widths both
375px. The viewport override was restored. Notes identify the record as synthetic.

Remaining: native file import (the extension requires file-URL access), workspace
export, editing and deletion keyboard flows, broader foundation journeys. The earlier download event timeout was
caused by an outstanding native Save dialog; completing it verified journal
download. Unit tests do not substitute for these remaining browser checks.
Physical touch/device performance was not tested.

Private-backup browser checks use a separate disposable SQLite database and
synthetic `observing-qa@example.invalid` account on loopback port18016. The initial
account revision0 and empty browser preview required explicit actions. Creating
a synthetic guest list did not upload it. Consent plus upload produced revision1
with one list; explicit restore of that same preview succeeded. Sign-out followed
by browser Back redirected to sign-in, without reopening the private preview.
Desktop and390×844screenshots show controls/status; mobile page/client widths
both375px, and the viewport was restored. Multi-tab conflicts, account-switch
guards, failed writes and decoded response caps have automated coverage; they
were not all reproduced manually in Chrome.


Terrain/hour planner: real loopback API calculation for 2026-10-01, London,
20:00Z–04:00Z and a four-point synthetic horizon returned a clipped Moon window
00:11:22–05:00:00+01:00, Saturn21:23:21–05:00:00+01:00 and no matching Jupiter
window. Desktop and375×812screenshots show target/required-altitude lines and
selected-hour shading. Enlarged mobile chart labels were visually checked.
The expanded Moon table has a focusable286px container around614px of columns;
ArrowRight moved its scrollLeft from0 to40, while page width stayed within the
375px viewport. Viewport restored. Screenshots: [desktop](night-terrain-desktop.jpg)
and [mobile](night-terrain-mobile.jpg). Saved-site copy has automated lifecycle,
stale/deleted-site and no-write coverage; its complete browser journey is pending.
The local backend used the builtin model; actual DE440s scientific regressions
are covered separately in backend PR35/39. A legacy upstream accuracy sentence
in this capture says no terrain; PR39 corrects it to no surveyed-terrain guarantee.


Mixed-catalogue/session checks continued after midnight (2October local time).
The local server on18018 used an explicit isolated environment and backend18006
with the actual pinned DE440s kernel. Native target hints left location/date empty;
submitting synthetic London plus Moon, HR2491 and NGC0224 produced correct solar
and starter journal namespaces, source/model disclosures and session controls.
Downloaded default JSON was12,253bytes with all three target identities and the
actual kernel hash, but no latitude, longitude or terrain; inputs_complete=false.
The opt-in control produced an explicit location-inclusive preparation status;
its second downloaded artifact was not independently recovered. Automated tests
cover both output forms and checkbox/print reset. Phone viewport375×812 showed
usable wrapping controls and no page overflow (360px content); override restored.
See [desktop](catalogue-session-desktop.jpg) and [mobile](catalogue-session-mobile.jpg).

Native Chrome print preview showed three pages with a light paper theme and
location/terrain omitted by default. The dialog was cancelled without sending
a print job. Accessible preview text was checked; a standalone PDF was not
produced. Exact source and model metadata remain in the JSON export.
