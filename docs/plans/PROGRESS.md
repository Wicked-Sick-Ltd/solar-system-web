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

Active owners: equipment lane E4 camera/sensor profiles and horizon masks;
backend lane checksum-pinned local JPL provider; catalogue lane frontend
`/observing-targets` browsing with source/epoch/licence disclosures; parent
integration, browser evidence and next private lists/journals/sync work.

Remaining accepted scope includes complete equipment-aware planner journeys,
selected hours/weather/exports, sourced catalogue planning integration, observing
lists/journals, optional private synchronization, scientific snapshot identity and
reproducible exports, and the remaining performance/browser acceptance. The
current1.5MBnight-response check is buffered-envelope validation, not a streaming
memory limit; P5 still needs that follow-up. Do not mark the programme complete
after this first wave. Continue build/review/PR loops across sessions; preserve
the original backend checkout and all topic worktrees.

## Evening development: usability, observation accuracy and release rehearsal

Craig authorized an autonomous window of up to two hours from 18:42 UTC. Three
isolated worktrees handled navigation, catalogue browsing and galaxy loading;
follow-on observation/settings fixes received independent adversarial review.
Changes are integrated on `codex/public-universe-foundation` as local commits.

- The galaxy page now requests its 3D renderer and JSON only after activation.
  Initial galaxy JavaScript fell from 557.56 kB to **3.59 kB** (1.62 kB gzip).
  The optional 557.53 kB renderer remains; no device/network speed claim is made.
  Pending navigation, failed loads, GPU cleanup and respectful focus continuation
  have regression coverage. Text links and the measured-system directory remain.
- Shared navigation has visible keyboard outlines, larger mobile targets,
  explicit search submission, bounded menus and no-JavaScript fallbacks. Native
  catalogue forms and pagination preserve selections and modified link clicks.
  Strict raw input validation avoids filter broadening and malformed-state errors.
- The orrery rejects impossible dates and discloses missing positions. Close
  approaches distinguish unavailable/empty data, disclose the nearest-200 cap,
  and preserve null, zero and sub-precision positive measurements.
- Settings use native radios, clean up listeners/timers on navigation, reject
  malformed shared locations, and retain explicit confirmation before import.
- The observer panel cancels stale location callbacks, serializes Forget after
  pending lookups, and saves only accepted coordinates. Invalid input and API
  failures recover without losing useful error messages. Sky responses are
  validated before producing visibility claims. Weather uses explicit m/s winds,
  a new cache namespace and the exact forecast hour; copy no longer equates clear
  weather with observability or civil twilight with daylight. Offline REST/MCP
  and PHP contracts now include real ordinary and polar observer responses.
- A disposable loopback HTTPS rehearsal exercises real cached Laravel builds
  and an isolated release ledger, with dedicated no-secret CI. Adversarial review
  strengthened assertions for changed-note retries and before/after rollback
  visibility. This is not a rehearsal of Forge, FPM, backups or live workers.
- The reviewed **1.0.0 draft** now includes these changes. Version remains the
  bootstrap `0.0.0`; no publication, tag, merge, deployment or domain change.

Combined integration validation: **755 PHP tests / 2,830 assertions**, **36
JavaScript tests**, **15 release metadata/hook tests**, **8 deployment tests**,
and **11 handout tests** pass. Full Pint, PHPStan and production asset build pass.
The real offline contract rerun passes **6 tests / 106 assertions** against the
unchanged backend integration checkout. The optional renderer's existing
chunk-size warning and the backend's pre-existing Starlette deprecation remain.

Browser controls timed out and later reported no available Chrome window. The
new layouts, native radio arrow-key interaction, observer focus/error feedback
and WebGL activation therefore
remain pending browser acceptance. Do not treat prior screenshots as evidence of
these new changes. See [evening acceptance](../qa/2026-10-01/evening-development.md)
for measurements, isolation limits and the exact remaining visual checks.

## Completed follow-on: real handout PDF validation

The actual Debian wkhtmltopdf renderer reproduced and fixed an extra blank page
and an orphaned footer. Both default and optional moon handouts now render as
**2 and 3 A4 pages**, respectively. All five pages were visually inspected, and
an independent reviewer checked text, dimensions, exact URI annotations and
honest offline fixture labels. The integrated rerender produced byte-identical
page PNGs to those inspected. **11 offline Python tests** and PDF smoke checks
pass. No live astronomy requests were used for this acceptance.

Counts now preserve exact small totals and actual positive/negative snapshot
differences; the historical comparison explicitly covers the eight listed
planets. Malformed count data or partial/repeated moon pages fail generation.
A pinned official Debian image and network-disabled rendering make both variants
reproducible locally and in CI. Generated PDFs remain ignored local artifacts.
See [PDF acceptance](../qa/2026-10-01/handout-pdf.md) for renderer/data provenance,
reproduction and limitations. New live data or custom branding still requires
fresh rendering and visual inspection before publication.

## Community release workflow

Craig requested deployments and full community/site release notes following
project-gambit. Implemented a single reviewed JSON source, local Markdown/JSON
review commands, `/whats-new` history and permanent version pages, linked from
the footer and sitemap. Drafts remain private until exact live build/database
verification records an immutable publication. Rollbacks hide newer versions;
retries and concurrent publishers preserve the first notes, commit and date.

The deployment script now requires a full reviewed SHA, validates branch ancestry
and a clean checkout before maintenance, checks out that exact revision and
writes build identity before configuration caching. Its final publication hook
requires HTTPS, no redirects, a nonce, no-store and matching live version/commit
and database readiness. Bootstrap `0.0.0` also verifies readiness but publishes
nothing. Post-activation failure explicitly reports that code is already live.

Release Please configuration and no-secret CI metadata checks are prepared. The
release bot is inactive until a separately authorized repository-scoped GitHub
App is configured; a manual release PR remains possible. A reviewed draft for
1.0.0 is at `resources/releases/1.0.0.json`. No stable release/tag, live deployment,
community message or new access grant has been created. See
[release workflow](../releases.md) and [deployment runbook](../../DEPLOYMENT.md).

Validation: **507 PHP tests / 1,932 assertions**, **15 release metadata/hook tests**,
**8 deployment tests** (including an isolated real Git branch-advance test),
**6 JavaScript tests**, Pint, PHPStan and production assets pass. The existing
557 kB galaxy chunk warning remains. Independent review corrected a bootstrap
readiness bypass and a PHP/Python numeric-heading schema mismatch before
integration. Browser review of desktop and 390px layouts used a temporary local
SQLite database and clearly labelled QA release, with no accounts or outbound
HTTP. The permanent page link works; no local fixture was publicly published.

The first deployment destination was requested once (existing production or
separate staging) and remains unanswered. Existing Forge site inventory was
read, but the intended read-only Git command returned unrelated cache-warming
output. Inspection of the installed CLI showed it lists commands and polls the
first item after creating a command, making stale output plausible; this does
not establish the deployed revision. No deployment command was invoked and no
further remote commands were attempted. Forge's browser session requires login.
Before rollout, establish the actual revision, install a reviewed deploy wrapper,
verify backups/drain/recovery and obtain the target decision. A changed repository
script does not automatically replace Forge's saved script. Worker/scheduler
health remains a required operator check beyond web readiness.

## Completed follow-on: accessible measured-system directory

Craig requested autonomous continuation on 1 October. Delivered `/systems`, a
native GET directory for the measured exoplanet hosts returned by the existing
map endpoint, beyond its 20-item accessible fallback. Name/distance filters,
deterministic sorting, 24-item pagination and reset work through ordinary GET
requests. All filters have strict raw input validation and explicit out-of-range
recovery. Map, Learn, Explore and footer links connect the directory to journeys.

Small, zero, missing and one-sided uncertainties remain distinct in the directory
and map. Missing truncation/omission metadata is unknown, never assumed complete
or zero. Shared map payload validation rejects malformed measurements, duplicate
IDs and oversized responses. Scientific coverage details use native expandable
content; truncation warnings remain visible.

Implementation branch `codex/public-universe-systems` was integrated as
`9ceb234`; shared boundary validation is `c499568`, followed by reviewed
integration refinements. Independent review found two precision/coverage gaps;
both were fixed and regression-tested. No production changes were made.

Current web validation: **471 PHP tests / 1,806 assertions**, **6 JavaScript
tests**, full Pint/PHPStan and production asset build pass. Existing galaxy
chunk warning remains. Backend and deployment code are unchanged in this pass;
the earlier checks below apply to that foundation checkpoint.

Browser: native filter submission, name-sort page 2→3, keyboard details,
390×844 layouts, and Proxima directory→map selection verified. Proxima's small
errors now display +1.109E-3 / −1.142E-3 light-years in the map. Full ordinary-GET
flows also have HTTP feature coverage without a JS runtime; browser JavaScript
was left enabled. See [feature notes](../measured-systems.md) and
[QA screenshots](../qa/2026-10-01/README.md).

## Delivery

- Web integration branch: `codex/public-universe-foundation` in the original
  checkout. Coherent commits are local, reviewed and integrated; no production
  or auto-deploying merge has been performed.
- M1: account/session/cache and alert fixes; account-aware fail-closed release
  script; corrected operational docs; Public Universe branding/share images;
  domain-migration runbook; unified discovery and galaxy interaction QA;
  configurable Public Universe handout branding with legacy origin defaults.
- M2: Explore/Observe/Learn/Data journeys with sourced activities; meteor list
  and detail with all returned parameter sets; asteroid filters and explicit
  ID-cursor traversal with a tested overfetch boundary. Malformed URL/Livewire
  state is validated before querying the catalogue.
- M3: guest filtered-page CSV/JSON exports (at most 24 planets), units, raw source
  values, errors/limits/references, missing/null preservation and CSV safety;
  exact offline REST/MCP-function/web contracts. Export scope and lack of
  immutable snapshot identity are disclosed in both UI and metadata.
- Backend commit `34e62c0`, branch `codex/public-universe-data-integrity`, at
  `../.worktrees/public-universe-db`: unsupported legacy asteroid filters raise
  an error; meteor windows preserve fractional circular distance with a tiny
  inclusive-boundary tolerance. Original database checkout and handoff retained.
- Agent branches/worktrees remain available under `../.worktrees/` for audit.

## Foundation validation (before the directory follow-on)

- Web: **418 PHP tests / 1,610 assertions**, full Pint and PHPStan pass.
- JavaScript: **5 tests** pass. Production assets build successfully. Existing
  galaxy chunk-size warning remains (557 kB minified, 139 kB gzip).
- Handout: **6 offline HTML/CLI tests** pass and now run in CI. Actual PDF
  rendering remains a release gate.
- Deployment: **4 mocked-command tests** pass and now run in CI; real deploy
  script was not executed against a service.
- Backend: **175 API/MCP tests** pass; F401 lint and fresh temporary offline
  build/verification pass. Two pre-existing Starlette deprecation warnings remain.
- Cross-interface runner: **3 PHP contract tests / 53 assertions**, real REST
  responses equal MCP function results, with selected-checkout imports and exact
  consumer GET/query assertions. It does not certify MCP transport.
- Real local catalogue: seven TRAPPIST-1 planets round-trip through CSV and JSON
  with identical source data, metadata, attachment and no-store headers.
- [Browser QA and screenshots](../qa/2026-10-01/README.md): desktop/mobile hubs,
  search, map keyboard/selection/drag, asteroid filter/cursor, meteor details,
  exports. Invalid distance recovery retains literal `q=true` (not `q=1`).
- Adversarial review findings were fixed and regression-tested: raw input
  coercion/nulls, malformed upstream rows, pagination skips, ignored old-schema
  filters, fractional boundary rounding, mixed-checkout contract generation and
  insufficiently strict request fixtures. Local documentation links pass.

## Follow-on dependencies and release gates

1. Plugin repository remains unavailable; its path was requested. Inspect its
   configuration, brand and API compatibility once located.
2. Automatic exoplanet aliasing is deliberately deferred: no trustworthy planet
   identity/alias relation is present. See [identity notes](../catalogue-contract.md)
   and backend `docs/EXOPLANETS.md`; use persistent, explicitly sourced mappings
   when evidence is supplied, never inferred planet merges.
3. Physical touch acceptance requires a real device. The default handout PDF
   renderer gap is closed above; changed data/branding still requires fresh
   rendering and visual inspection before publishing a new PDF.
4. Execute staging rehearsal and the evidence checklist in
   [migration runbook](../PUBLIC-UNIVERSE-MIGRATION.md). Verify working API/MCP/
   download origins and the plugin. Production deployment, DNS/TLS cutover,
   provider settings and auto-deploying merges require environment authorization.
5. Later scope: broader curricula, translation, artificial satellites and larger
   stellar catalogues. Size these after foundation acceptance.

## Resume

Read repository AGENTS.md, CONTRIBUTING.md, SECURITY.md and this plan. Inspect Git
state before editing; preserve original database handoff and all worktrees. The
available foundation work is implemented. Select the next ready item above,
record scope/evidence and continue the documented build/review/checkpoint loop.
Do not mistake historical deployment plans or local passing checks for live
rollout authorization.
