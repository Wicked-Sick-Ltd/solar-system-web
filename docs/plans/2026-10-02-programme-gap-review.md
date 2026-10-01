# Observing programme: independent gap review

Assessment at 2 October 2026, against the [accepted programme](2026-10-01-observing-programme.md),
[progress log](PROGRESS.md), topic source and current review checkpoints. This
review neither replaces the programme nor marks it complete. PR implementation,
integrated validation, physical acceptance and live availability are distinct.

## Acceptance matrix

| Accepted items | Evidence now available | Remaining work or qualification |
| --- | --- | --- |
| P1 | Foundation and observing screenshots; desktop Explore skip-to-main, live search, system filters, settings inventory, explicit WebGL loading, host picker and arrow/zoom checked on local fixtures. Two found UI issues fixed in web #80. | Reconcile the older pending lists in PROGRESS with newer evidence. Latest integrated mobile navigation/errors/reduced-motion and no-JavaScript journeys still need named evidence. Emulation does not close physical-touch acceptance. |
| P2, P6 | Request-count baseline, lazy map entry and lifecycle tests. A separate performance lane is adding built-asset and fixture request-kernel budgets for ten routes, including a real eight-target planner response. | Execute and retain the final combined workload. Kernel latency excludes provider/network/browser work; measure actual map frame time, memory, interaction and lower-power hardware separately. No field p75/Core Web Vitals result exists. |
| P3 | Web #67 queue deduplication, multiprocess lease/retry coverage; later identity/weather producer-lease regressions. | Combined cache-generation/queue checks after #79 integration and operational worker restart/drain acceptance at an authorized rollout. |
| P4 | Backend #38 query profiling with unchanged output hashes; vectorized horizon crossings and one batched planner request. | Larger real catalogue build/finalization cost remains unmeasured. Existing warmed synthetic timings do not establish production or every-target planner cost. |
| P5 | Bounded planner/weather/private-sync streams and inputs; #79 catalogue observation/cache generations; private response isolation. | Response-level scientific snapshot association remains absent. Confirm combined route headers and document old CDN entry expiry/purge; no claim that a changed header retroactively clears edge caches. |
| E1–E5 | Guest CRUD, bounded v1/v2 imports, explicit site activation, camera geometry, circular masks, settings/privacy links and storage-failure tests. #77 copies selected site inputs explicitly. | Latest combined camera/mask browser acceptance and real import roundtrip evidence need completing where still pending. Older workspace-schema prose saying night planning cannot use masks is stale after #77 and should be corrected on integration. |
| A1–A6 | UTC/legacy Moon corrections; bounded Astropy/JPL planner; DST/polar/grazing/terrain tests, true lunar positions, Moon constraints, retained selected Horizons fixtures and exact provider/IERS metadata. | Keep reference coverage/tolerances scoped to tested cases; no all-date observational-accuracy certification. Production kernel/IERS provisioning is a separate prerequisite, not an implementation result. |
| N1–N3 | Native private form, selected hours, charts/tables, explained statuses and optics/camera comparisons. | Record final combined screen-reader/keyboard/mobile acceptance; optical compatibility and visibility are deliberately not promised. |
| N4, C4 | Reviewed saved/temporary eyepiece comparison, explicit order, missing fields, source magnitude band/flags and angular-extent containment. Horizon constraints are in the night calculation. | Integrate and browser-check the comparison. This is explained geometric comparison, not a general automatic target recommender; naked-eye/binocular discovery and site/gear-guided target selection should be assessed against the product outcome. Do not invent unsupported success scores. |
| N5 | Web #78 explicit matching-hour forecast, null/partial/out-of-range states and privacy/bounds tests. | Integrate into the combined night result and record an actual bounded local-fixture browser flow. Future-night geometry must work without weather. |
| N6, D3 | Reviewed session summary JSON, CSV window index and print with explicit coordinate/terrain choice; private inputs/units/UTC/timezone/provider/source details retained when selected. | Combined acceptance and documented repeatability check against retained input/result fixtures. Additive algorithm-source identity and offline replay checks are in progress in a separate backend lane, not yet accepted here. No summary import/replay UI or immutable live archive is implemented; exported summaries intentionally omit samples, equipment, weather and actual observations. |
| C1–C3 | Licensed bounded star/multiple-star/deep-sky samples, repeatable ingestion, native browsing, exact namespaced IDs and verified frame/motion metadata; backend #39 mixed planning and frontend integration under review. | Integrate mixed-target links/form/results. M45 remains unsupported; historical double records are not current companion ephemerides. Do not describe the sample as a full sky catalogue. |
| J1–J3 | Multiple lists, target status/reordering, actual-time/outcome/notes observations, historical setup snapshots, JSON/CSV/print, stale-tab rejection and one-level undo for removal/import. | Concrete UI gap: existing observations cannot be corrected, and list names cannot be renamed; current code only creates/removes observations. Safe explicit edit/cancel/save with preserved identity/snapshot should be the next focused product slice. Journal raw-corrupt-data recovery and real browser import acceptance also need confirmation. |
| J4–J5 | #74/#76 owner-isolated encrypted copies, explicit actions, CAS revisions/tombstones, account-scope guard, ephemeral previews and logout/Back browser checks. | Validate combined browser account-switch and partial restore recovery where missing. LocalStorage has no atomic multi-key compare-and-swap; the documented residual race is not solved by server revisions. No automatic login upload. |
| D1 | Backend #40 logical data hash, independent build provenance, public source metadata, mutation/schema invalidation and consistent backup/export checksums. | Measure full catalogue finalization; decide retained snapshot distribution/retention before promising historical retrieval. |
| D2 | #79 typed known/unknown identity, cache generation transitions and API/download comparison. Night results retain source-specific snapshot/provider hashes. | Exoplanet export still says the API supplies no immutable identifier; revise to distinguish known catalogue identity from the absent atomic response association. Do not attach a separately observed ID as a certified response snapshot. A backend response-level identity/pinned-read contract is the meaningful remaining implementation dependency. Planner calculations use packaged starter rows rather than the SQLite catalogue; their own source hashes must not inherit a separately observed global database ID. |
| D4 | Existing scoped contracts/runbooks plus [the unreleased release packet](../RELEASE-1.0.0-REVIEW.md). | Reconcile rolling-upgrade docs with the final integrated code, replace forthcoming checkpoint rows, review final copy and verify actual target revision before any separately authorized publication. |

## Recommended next implementation order

1. Complete the already active integration and acceptance loops for C3/N6, N4,
   weather and catalogue identity. Re-run meaningful mixed-input/privacy/cache
   checks; do not create duplicate implementations to bypass topic dependencies.
2. Add journal observation editing and list renaming. Preserve record UUID and
   target identity, default to the recorded setup snapshot, offer explicit
   cancellation, reject stale storage and keep pending edits visible on quota
   failure. Replacing a historical snapshot must be a separate explicit choice,
   not an automatic copy of today's equipment. Regressions should include failed
   persistence, another-tab changes, import/reload interaction and exact UTC text.
3. Design response-level catalogue provenance before broadening reproducibility
   claims. Pin identity and queried rows to the same read transaction/snapshot,
   expose known/unknown association, retain version compatibility and invalidation,
   then consume it in exports. Before/after probes alone do not prove atomicity.
4. Finish measured performance/browser acceptance on the integrated result. Keep
   representative lab distributions and physical-device limitations separate from
   field targets. Pick map optimizations only after the workload exposes a cost.

The plugin repository/path remains unresolved in the foundation record. Domain
cutover, production migration/backups, external delivery and credentials remain
outside this development authorization. None is silently treated as completed.
