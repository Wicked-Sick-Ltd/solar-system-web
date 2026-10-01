# Public Universe progress and continuation

Updated: 2026-10-01. Foundation, accessible systems, release workflow and evening usability pass implemented.
See [development plan](2026-10-01-public-universe.md).

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
