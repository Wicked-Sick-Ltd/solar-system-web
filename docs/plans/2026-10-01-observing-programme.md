# Public Universe observing and performance programme

Accepted 1 October 2026. Status: active implementation. The persistent goal covers
the entire programme below, not just the next feature. This supersedes the earlier
foundation plan's deferred observing scope; it does not authorize production.

## Product outcome

A visitor can choose a location, night, available hours and equipment, receive an
explained list of suitable targets with time windows and field-of-view previews,
save or print the session, and record what they actually observed. Naked-eye,
binocular and telescope users can participate without an account. An account
optionally synchronizes private workspaces after an explicit user action.

The platform remains useful for independent discovery and scientific reuse.
Geometric visibility, equipment suitability and weather are reported separately.
Missing data never becomes a favourable score or a promise of visibility.

## Authorization and workflow

Craig requested existing work in PRs and autonomous completion of the proposed
seven workstreams. Development, tests, dependency setup, isolated worktrees,
independent review, coherent commits, branch pushes and PRs are authorized.
No production/main merge, live rollout, domain cutover, community announcement,
purchase, new credential/access grant or telescope motor control is authorized.

Existing review checkpoints:

- [Website foundation PR #65](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/65)
  is draft pending the latest browser acceptance. Initial CI, assets, PDF,
  Lighthouse and publication rehearsal passed. CodeQL requested an explicit
  minimum TLS version in the isolated rehearsal; fix `b39f6a8` is pushed.
- [Database compatibility PR #31](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/31)
  contains `34e62c0`; local 175 tests/offline verify/lint passed, CI is monitored.
- Preserve the original backend handoff checkout and other developers' branches.
  The related plugin remains unidentified; do not guess its location or ownership.

Use focused topic PRs stacked on the relevant foundation branch while it awaits
review. State the dependency in each PR; do not merge merely to unblock another
topic. Keep the programme integration branch for combined checks. Resolve review
findings before advancing a slice, without asking for routine milestone choices.

## Deliverables and acceptance

### P — measured performance and release baseline

- P1: complete outstanding browser acceptance on current navigation, catalogue,
  settings, observer and 3D activation paths. Record actual screenshots and
  physical-touch limitations separately from automated assertions.
- P2: measure cold/warm request counts, payload sizes, route latency, memory and
  map frame time locally using fixed fixtures. Record machine/workload and
  distributions; never infer public p75 from one laboratory run.
- P3: prevent duplicate stale-cache refresh jobs, with bounded leases, independent
  cache keys, retry after failure and worker/queue integration tests.
- P4: profile representative backend search/list queries and remove demonstrated
  bottlenecks. Batch planner calculations; avoid object-by-time HTTP fan-out.
- P5: version-aware cache invalidation and explicit freshness, preserving private
  response isolation; bound memory and expensive request inputs.
- P6: repeatable performance budgets and representative-page CI. Improve map
  render/memory behaviour based on measurements, including lower-power devices.

Targets for eventual measured field performance: LCP <=2.5s, INP <=200ms and
CLS <=0.1 at p75, segmented by device. This is a target, not an existing result.
Reference: [Core Web Vitals thresholds](https://web.dev/articles/defining-core-web-vitals-thresholds).
No live load testing or new analytics deployment is part of local acceptance.

### E — equipment and observing sites

- E1: bounded, versioned guest workspace with stable IDs; named telescope,
  binocular, eyepiece and Barlow/reducer profiles; explicit units and validation.
- E2: named sites with rounded coordinates, IANA time zone and minimum altitude;
  explicit active-site selection compatible with existing observer settings.
- E3: safe JSON export and preview-before-import, reload persistence, storage
  denial/quota handling, reset/delete and no silent loss or upload of private data.
- E4: optional camera/sensor profiles and site horizon masks, with azimuth wrap
  and clear missing-horizon behaviour. Do not invent a branded equipment database.
- E5: integrate settings inventory, privacy copy and accessible navigation.

Keep the existing settings-link codec compatible. Do not embed site names,
journals or a whole workspace in automatic share URLs. Login alone is never
consent to upload local workspace content.

### A — validated astronomy and night windows

- A1: fix UTC-offset interpretation and reject the legacy Earth-as-Moon result.
  Other moon parent proxies must remain explicitly identified as proxies.
- A2: implement a separate bounded planning service with true lunar positions,
  consistent Sun/planet calculations, explicit frame/refraction/observer choices,
  offline Earth-orientation data policy and provider/method metadata.
- A3: derive a night from date + IANA zone (local noon to next noon), then clip
  to the visitor's chosen hours. Test 23/25-hour nights, date line, leap dates and
  polar day/night; use UTC instants internally and labelled local output.
- A4: altitude/darkness/solar-separation windows, refined boundaries and explicit
  always-above/below/no-window/unavailable states. Account for grazing events;
  root-solving tolerance is not a claim of astronomical accuracy.
- A5: Moon illumination, separation and altitude from the same ephemeris; optional
  Moon-separation constraints apply appropriately while the Moon is above the
  horizon, and never exclude the Moon from observing itself.
- A6: compare with independently recorded JPL reference values across locations,
  dates and lunar phases; record query, frame, timestamp and tolerances. Provide
  a configurable local checksum-pinned JPL-kernel provider. An offline built-in
  provider is explicitly labelled and never silently substituted under a JPL label.

References: [Astropy solar-system coordinates](https://docs.astropy.org/en/stable/coordinates/solarsystem.html),
[Earth-orientation policy](https://docs.astropy.org/en/stable/utils/iers.html),
[JPL Horizons](https://ssd.jpl.nasa.gov/horizons/manual.html),
[Astroplan constraints](https://astroplan.readthedocs.io/en/stable/tutorials/constraints.html).
Do not infer apparent brightness from absolute/reference magnitude. Do not offer
solar observing recommendations or automated instrument pointing in this scope.

### N — equipment-aware night planner and optical preview

- N1: consume the versioned backend contract with strict validation and graceful
  unavailability; a chosen date, site and available hours produce a small useful
  target list. Start with validated Moon/planet calculations, then expand to C.
- N2: accessible altitude/time charts, direction, useful windows and explanations
  of included/excluded targets. Tables and text carry the same essential data.
- N3: magnification, exit pupil and approximate visual field of view from actual
  gear values; support field-stop estimates and camera sensor FOV where supplied.
  Label the field-of-view preview as geometric, not a promise of visual appearance.
- N4: telescope/eyepiece suggestions with reasons and missing-input states;
  integrate horizon masks, user preferences and temporary unsaved setups.
- N5: weather shown independently at matching forecast hours and only within its
  coverage; future-date plans still work geometrically without a forecast.
- N6: save/print an accessible observing session; offer repeatable planner inputs
  and provenance in exports, with explicit choices about including locations.

Optics checks include 1,000mm/20mm =50x and independently calculated unit/boundary
cases. Reference: [Celestron magnification](https://www.celestron.com/blogs/knowledgebase/what-is-magnification-power-as-it-pertains-to-telescopes).
No unexplained overall viewing score or guarantee of detection.

### C — sourced observing catalogue

- C1: source/licensing/coverage review for a bounded bright-star starter catalogue,
  double stars and deep-sky targets. Record identifiers, coordinates, epoch/frame,
  proper motion when provided, measurement bands, angular sizes and references.
- C2: reproducible ingestion, schema/query support and offline fixtures for all
  three target families. Define the delivered sample before implementation and
  disclose completeness; no unreviewed full-Gaia import is implied.
- C3: REST/MCP functions and web browsing/search/planner integration with stable
  namespaced target references. Aliases require evidence; never fuzzy-merge objects.
- C4: use available apparent brightness, angular size and source constraints for
  explained suitability. Unknown brightness remains unknown; extended-object
  difficulty must not be reduced to a universal point-source magnitude limit.

### J — lists, journals and optional account synchronization

- J1: multiple observing lists, add-from-catalogue/planner, reordering/status and
  unavailable-target retention without changing historical identity.
- J2: journals with user-entered actual observation times, outcome and notes;
  equipment/site snapshots preserve context after later profile changes.
- J3: bounded imports/exports and deletion, JSON plus useful CSV/print output,
  spreadsheet-formula protection and explicit location inclusion choices.
- J4: optional authenticated workspace synchronization with owner isolation,
  revision/conflict checks, explicit upload/download and no silent overwrite.
- J5: account-associated local data separation, logout/account-switch handling,
  export/delete controls and accurate privacy documentation.

### D — scientific provenance and reproducibility

- D1: immutable catalogue build identifiers and source retrieval/version metadata
  available through both interfaces, without exposing server paths or secrets.
- D2: propagate catalogue identity through frontend caches, data pages, downloads
  and observing plans; disclose stale/mixed-source/unknown identity states.
- D3: exports retain input parameters, units, UTC/timezone information, ephemeris
  identity and calculation assumptions. Verify repeatability against retained
  fixtures/snapshots rather than claiming that a moving live API is immutable.
- D4: updated user/scientist documentation, exact REST/MCP-function/frontend
  contracts and reviewed community release notes for each useful milestone.

## First implementation wave and ownership

1. Parent: PRs, programme coordination, shared routes/navigation/build entry
   points, API consumer contracts, integration and browser evidence.
2. Equipment worktree: E1–E3 guest workspace and page, schema and independent
   storage/import tests. Route `/observatory`, named `observatory`.
3. Backend worktree: A1 correctness commits, then A2–A5 service/API with A6
   reference fixtures; send interface shape before frontend integration.
4. Performance worktree: P3 verified refresh deduplication, plus P2 cold/warm
   request-count baseline. Independently review another lane when ready.

Then complete N1–N3 as the first full user journey, followed by C, J, D and the
remaining P/E/A/N items. This ordering does not remove later accepted scope.

## Completion and continuation

Keep explicit statuses and evidence in PROGRESS.md. Each delivered slice needs
meaningful regression tests, independent review and a focused PR. Use fixtures
instead of customer accounts, real notifications or private observing locations.
New external sources need primary-source attribution and redistribution review.

The programme is complete only when all deliverables above have implementation
and acceptance evidence, including required browser checks. Record unresolved
external dependencies as incomplete work. A first successful planner, passing
tests, elapsed session or a partial PR is not completion of the whole goal.
Continue useful independent work while browser/provider prerequisites are blocked;
never silently drop the remaining work or mark a partial milestone as the goal.
