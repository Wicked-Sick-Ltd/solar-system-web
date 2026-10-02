# Public Universe progress and continuation

Updated: 2026-10-02. Reviewed development checkpoints through web #86; programme remains active.
See [development plan](2026-10-01-public-universe.md).

This is a chronological record. Pending statements in earlier waves describe
those revisions, not the latest status. Use the [current acceptance audit](2026-10-02-programme-gap-review.md)
for reconciled implementation, browser and external limitations. Topic PRs are
unmerged review checkpoints; their test counts are not a combined release result.

## Active programme: performance and equipment-aware observing

Craig accepted the complete seven-workstream proposal after the evening pass,
and requested existing work in PRs before autonomous continuation. The persistent
programme goal is active; the earlier two-hour goal is complete and is not the
scope limit for this new request. See the [accepted programme and acceptance
checklist](2026-10-01-observing-programme.md).

Existing work is published for review: [web #65](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/65)
and [database #31](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/31).
Foundation application/assets/Lighthouse/PDF/rehearsal checks pass; CodeQL TLS
finding was fixed. These are PRs, not merged releases. Production boundaries
remain unchanged. The programme plan is [web #66](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/66).

Reviewed implementation checkpoints:

- P3 and request-count portion of P2: [web #67](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/67),
  unique queued/running stale refresh, real multiprocess queue regression;
  independent21tests/81assertions. CI green.
- E1–E3/E5: [web #68](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/68),
  guest equipment/site workspace, strict import/export and explicit activation.
  Independent review fixed export roundtrips and timezone canonicalization.
  Browser telescope/eyepiece/site creation, rounding, activation and reload pass.
  Export browser-event capture timed out; import/export browser acceptance remains.
- N3 first visual-optics slice: [web #70](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/70),
  magnification/exit pupil, field-stop/AFOV estimates, angular-size comparison.
  Independent26JS tests pass; full feature759PHP/109JS tests and static/build checks
  pass. Actual browser50×/4mm/1° example and phone-width layout checked.
- A1: [database #32](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/32),
  UTC normalization/calendar bounds and honest legacy Moon rejection; independent15tests.
- A2–A5, reference-fixture part of A6: [database #33](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/33),
  offline Astropy true lunar/planet positions, local-noon DST nights, refined
  windows, Moon constraints, method/IERS metadata;251tests/offlineverify/wheel/lint
  pass. Independent review fixed MCP coercion, grazing crossings and bounded
  background execution. JPL-kernel provider remains active work.
- N1/N2 first journey: [web #69](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/69),
  strict one-batch consumer, private POST form, windows/Moon/chart/table/model
  disclosures. Full810PHP tests/3037assertions; independently55planner tests/207
  assertions. Browser Moon/Jupiter/Saturn calculation passes. Selected hours,
  equipment/site integration, weather and session export remain.
- C1/C2 and backend portion of C3: [database #34](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/34),
  pinned50bright-star records,23overlapping documented multiple-star records,
 108deep-sky records. Full194tests/source reproduction/offlinebuild/wheel pass;
  parent independently18tests pass before one additional partial-schema test.
  Historical double separations have unknown dates/PA; no current companion
  position is inferred. Data licensing stays distinct from MIT software.

Integration branch `codex/observing-integration` contains reviewed web slices for
combined checks; focused PRs remain separate and unmerged. Scoped integration
66tests/285assertions and Node22build pass. CI now accepts nested codex PR bases.
Screenshots are recorded under `docs/qa/2026-10-01/observing/`; browser emulation
is not physical-touch acceptance. Latest foundation catalogue/settings/galaxy
browser acceptance still needs completing.

Second-wave checkpoints:

- [Web #71](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/71) adds
  sourced starter-catalogue browsing. Full849PHP tests passed; browser acceptance
  remains pending.
- [Web #72](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/72) adds
  camera/sensor geometry and version-two workspace horizon masks, with backward
  import support. Full760PHP/142JS tests and independent review passed. Masks
  are stored; applying them to night calculations is the next backend slice.
- [Database #35](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/35)
  adds a checksum-pinned local DE440s provider in an isolated bounded process,
  kernel/IERS provenance and selected Horizons regressions. All CI is green.
- [Web #73](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/73) adds
  guest observing lists/journals and private JSON/CSV/print output. Full821PHP
  tests/3112assertions and140JS tests pass. Real Chrome creation, persistence,
  downloaded JSON and desktop/mobile layouts pass. Browser import requires
  extension file-URL permission; automated preview/apply/undo tests pass.

`codex/observing-integration` stays at its published checkpoint so dependent PRs
retain reviewable diffs. The next combined branch is
`codex/observing-integration-next`, with853PHP tests/3235assertions and173JS tests
passing. These checkpoints are not releases.

Third-wave checkpoints:

- [Web #74](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/74) provides
  encrypted private account backups with revision conflicts, strict schemas,
  bounded Unicode JSON and deletion tombstones; full893PHP/188JS checks passed.
- [Web #76](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/76) adds
  explicit local/account previews and consent for upload, restore and removal.
  Full896PHP/200JS checks and independent review passed; real Chrome upload,
  restore, logout/Back and mobile layout passed with synthetic account data.
- [Database #36](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/36) and
  [web #75](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/75) retain
  primary-source coordinate frames, epochs and proper motion. Fifty FK5 bright
  stars and107 ICRS deep-sky records are supported; M45 remains unsupported.
- [Database #37](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/37)
  applies selected UTC hours and circular terrain masks. Vectorized crossings
  reduce repeated transformations; actual MCP integer inputs have regressions.
- [Database #38](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/38)
  improves indexed object/exoplanet queries with unchanged response hashes.
  Synthetic52,431-object keyset first-page median fell31.610→0.629ms;228tests
  pass. This is a local warmed-cache benchmark, not production latency.
- [Database #39](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/39)
  calculates mixed solar/star/deep-sky nights with explicit frame/motion limits,
  source hashes, attribution and unknown stellar distance;387tests pass,
  including actual DE440s, and independent scientific review passed.
- [Database #40](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/40)
  provides logical catalogue identities, build provenance and consistent export
  checksums. Old or changed catalogues report unknown;245tests and independent
  review pass. Full-catalogue finalization cost remains unmeasured.

Fourth-wave checkpoints (2 October):

- [Web #77](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/77)
  adds saved-site copy, selected hours, terrain charts and accessible tables.
  Real desktop/mobile calculations and keyboard table scrolling pass. Four real
  HTTP transport tests cover bounded plain/gzip responses and redirect rejection.
- [Web #78](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/78)
  adds explicit, bounded, matching-hour weather lookup with unknown/coverage
  states and cache write fencing. Independent review passed; page integration
  and browser acceptance remain.
- [Web #79](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/79)
  consumes catalogue identity for generation-aware caches and About/download
  provenance. Independent review passed; combined integration remains.
- [Web #80](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/80)
  fixes live-search page titles and obsolete galaxy loading instructions found
  during real desktop browser acceptance. Full956PHP tests and23focusedJS
  tests passed; Chrome confirmed both fixes.
- The current `codex/observing-session-exports` slice adds mixed catalogue target
  planning, strict source/provider metadata and private JSON/CSV/print sessions.
  Real DE440s Chrome calculations, default JSON download and phone layout pass;
  independent review passed. Final1076PHP tests/4014assertions and208JS tests passed, along with
  Pint, PHPStan and the production build. Global catalogue identity is not claimed to describe
  the packaged per-source planning records atomically.

Reviewed equipment comparisons (saved and temporary setups) are ready for the
next combined night-planner slice. Performance route/asset budgets and draft
community release notes are in progress. Remaining acceptance includes combined
weather/equipment/identity journeys, workspace export/import browser coverage,
map frame-time measurements and physical-device/field performance. File import
currently requires the browser extension's file-URL permission; no new access
has been granted. These checkpoints are unmerged development PRs. Do not mark
the programme complete after this wave.

Fifth-wave integration checkpoints:

- [Web #81](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/81):
  catalogue targets and private session exports; all CI checks pass.
- [Web #82](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/82):
  reviewed community/site release draft and full acceptance matrix. Still
  unpublished; version remains0.0.0.
- [Web #83](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/83):
  explained saved/temporary equipment comparison and explicit weather in a new
  tab;1128PHP/224JS passed. Chrome confirms sourced size context,50×/4mm/1°,
  live matching-hour forecast, unknown distant-date weather, responsive optics
  and retained original plan. Later combined browser checks confirm saved
  equipment reload, explicit saved-site copy and downloaded workspace JSON.
- [Web #84](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/84):
  fixed-fixture request/asset budgets, with cold/fresh counts and raw7-pair
  latency/memory samples. Full1168PHP/226JS passed. These are local request-kernel
  measurements with fake backend responses, not public latency or device results.

Published `codex/observing-provenance-integration`f54398d combines guidance,
catalogue generation-aware caches, browsing fixes and release drafts;1158PHP
tests/4518assertions passed. The next scientific followthrough retains an
optional calculation source-code identity, explicitly reports older omissions,
and corrects stale workspace/export wording discovered during browser QA.
Journal corrections/recovery and backend retained replay are independently
reviewed; per-response exoplanet identity and actual map-cadence measurements
are active. File import and physical-device/field performance remain incomplete.


Sixth-wave reviewed checkpoints (2 October):

- [Web #85](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/85),
  `3111845`: journal observation correction and list renaming preserve UUIDs,
  target identity and historical setup. Lossless original-data recovery is
  explicitly unredacted. Independent 39-case store/UI review and real Chrome
  validation/error/undo/reload checks pass; full topic 955 PHP/210 JS passed.
  The journal-editing implementation gap in the earlier audit is closed.
- [Web #86](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/86),
  `24ec3eb`: optional response-associated exoplanet identity travels with cached
  rows and exports. Known, unknown and legacy unassociated states stay distinct;
  a separate catalogue probe cannot certify a page. Topic 88 targeted tests/547
  assertions passed. Backend [#42](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/42)
  `17583c9` pins metadata, table capability, rows and count to one read transaction;
  independent 13-case WAL/replacement/error suite passed. Actual combined browser
  export acceptance is still pending.
- Scientific followthrough `1b14b81` retains optional algorithm-source identity,
  explains missing older metadata and fixes negative magnitude display and stale
  site/export wording. Independent 201 scoped PHP/638 assertions and 23 optics
  JS checks passed. Backend retained replay compares only compatible recorded
  source/runtime/IERS/kernel identities; it is not replay of a moving live API.

At this audit checkpoint the parent is combining #84/#85/#86. Nine performance
harness runtime-count failures were traced to inherited debug configuration.
PR #84 follow-up `72543c0` pins both PHP environment adapters before application
boot; an independent inherited-debug run passed ten cases/584 assertions without
weakening the runtime assertion. The full combined baseline still needs its final
rerun. Actual map profiling and
current-catalogue browsing are in progress. Append their results with exact
revision/workload rather than retroactively changing earlier evidence.

The [acceptance matrix](2026-10-02-programme-gap-review.md) identifies the remaining
product implementation gap: explained site/time/equipment-guided target selection,
including naked-eye discovery. Existing eyepiece comparison does not itself choose
a target shortlist. Native file import remains blocked by extension permission;
physical touch/lower-power performance, field p75 and several final keyboard and
assistive-technology journeys remain unverified. These limitations do not reopen
already delivered journal correction, weather or page-associated identity work.

Follow-up reported during audit review: the parent combined branch now passes
1,215 PHP tests/5,333 assertions, 234 JS tests, Pint, PHPStan and build after the
pre-bootstrap debug fix. Current About metadata works against backend #42. Chrome
blocked the native exoplanet download with `ERR_BLOCKED_BY_CLIENT`; browser export
acceptance remains incomplete and that restriction was not bypassed. The parent
also recovered the opt-in session artifact (12,831 bytes, location included and
all four terrain points), alongside the 12,253-byte default with coordinates
omitted. Detailed screenshots/revision evidence belong in the parent QA update.


## Combined audit and map checkpoint — 2 October

Web `a3cb133` combines the acceptance audit (#88), independently reviewed map
coalescing (#89) and retained parent browser evidence. Integration draft #90
uses immutable `codex/observing-acceptance-base` (`8a06a4a`) for review. The exact
combined revision passed 1,215 PHP tests / 5,333 assertions with inherited debug
enabled, all 238 JS tests, Pint, PHPStan, production build and diff checks.
Actual catalogue search/detail/site-copy/JPL planning passed. Browser map samples
do not establish a causal speed improvement; the diagnostic limitations and
source/build hashes are retained in the [browser report](../qa/2026-10-02/combined-browser-evidence.md).

Backend bounded candidate discovery is now draft #43 (`a4ef9bb`), with 498 tests
including actual JPL and independent 30-case review. Its frontend consumer and
remaining feasible browser acceptance are in progress; the programme is active.


## Shortlist integration and actual browser checkpoint — 2 October

Frontend #91 (`ed54b0c`) is integrated as `f81d8cc`, with backend #43
(`a4ef9bb`). The combined code passes 1,297 PHP tests / 5,809 assertions,
239 JS tests, Pint, PHPStan, build and diff checks. Native desktop Chrome
acceptance passed actual JPL naked-eye and telescope candidate selection,
selected hours, explicit empty magnitude filtering and target-only handoff;
[details and screenshots](../qa/2026-10-02/shortlist-browser.md).

Workspace/camera/script-blocked evidence from #92 and release packet #93 are
now integrated. Narrow-screen, sync account-transition and remaining feasible
browser checks continue. Existing file-import/download browser restrictions,
physical-device and field-performance limitations remain explicit. No deployment,
version activation, domain change or community publication occurred.
