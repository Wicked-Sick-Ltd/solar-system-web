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
export, editing and deletion keyboard flows, final night charts/tables at narrow
widths and broader foundation journeys. The earlier download event timeout was
caused by an outstanding native Save dialog; completing it verified journal
download. Unit tests do not substitute for these remaining browser checks.
Physical touch/device performance was not tested.
