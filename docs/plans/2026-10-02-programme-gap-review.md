# Observing programme: independent acceptance audit

Updated 2 October 2026 against web `1b14b81`, published review slices through
[#86](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/86), and the
[accepted programme](2026-10-01-observing-programme.md). This documentation branch
does not itself contain every reviewed topic. The [progress log](PROGRESS.md)
records integration checkpoints; a passing topic suite does not establish that
the final combined revision passed. No release or deployment is claimed.

## Acceptance matrix

| Accepted items | Implemented/reviewed evidence | Remaining acceptance or implementation |
| --- | --- | --- |
| P1 | Local foundation and observing screenshots; native catalogue filters, skip-to-main, search-title updates, settings inventory, explicit WebGL activation and keyboard camera controls. Web #80 fixes the two reproduced browsing defects. | Latest integrated 320px/landscape, menu Escape/focus return, observer error focus, settings radio keys, reduced-motion and JavaScript-disabled browser journeys still need explicit evidence. Native file import remains blocked by extension file-URL permission. Physical touch and assistive-technology acceptance are separate. |
| P2, P6 | Web #84 retains ten fixed-fixture request workloads, cold/fresh counts, seven-pair latency/memory samples and built-asset budgets, including an actual eight-target response fixture. | Combined integration exposed debug-environment contamination of runtime counts. PR #84 follow-up `72543c0` pins both PHP environment adapters before boot; an independent inherited-debug run passed all ten cases. Final full combined validation remains separate. Actual map cadence/memory profiling is in progress. Lower-power hardware and field p75/Core Web Vitals remain unmeasured. Mock HTTP kernel timings exclude provider/network/browser work. |
| P3 | Web #67 multiprocess queue lease/retry coverage; later catalogue/weather producer-lease regressions prevent obsolete cache publication. | Preserve combined generation/queue regression checks. Authorized rollout must restart/drain workers as documented; this is an operational prerequisite, not an absent queue implementation. |
| P4 | Backend #38 measured representative object/exoplanet queries with unchanged output hashes; vectorized terrain crossings and one batched planner request avoid per-time HTTP fan-out. | Full-catalogue identity finalization remains unmeasured. Warm synthetic queries and selected planner workloads do not establish production throughput. |
| P5 | Bounded private planner/weather/sync transport and inputs; #79 catalogue-generation fencing and private response isolation. #86 retains response-associated exoplanet metadata with cached rows. | Verify combined route/header/cache checks. Old edge entries need expiry or separately authorized purge; new headers cannot clear earlier responses. Page identity does not pin a later page or every catalogue route. |
| E1–E5 | Stable bounded guest profiles/sites, v1/v2 migration, cameras, circular masks, explicit activation/copy, settings/privacy integration. Browser creation, rounded site/mask persistence, saved optics reload, site copy and native workspace export are recorded. | Native file-picker import roundtrip remains unverified; malformed/stale/quota/storage-denied flows have isolated tests. Stored camera geometry is reviewed, but camera-specific combined browser interaction should not be inferred from visual-eyepiece screenshots. |
| A1–A6 | UTC/legacy Moon correction; bounded true lunar/planet calculations, selected hours, DST/polar/grazing/terrain cases, Moon constraints, pinned JPL option and selected independent Horizons references. | Accuracy evidence is limited to the retained tested cases and model assumptions. Production kernel/IERS provisioning and broader field validation are not claimed. |
| N1–N3 | Native private form, selected hours, chart/table equivalents, explained windows/statuses and telescope/binocular/camera geometry. | Final combined keyboard/assistive-technology checks remain. No optical compatibility or visual-detection guarantee is intended. |
| N4, C4 | Web #83 integrates saved/temporary eyepiece comparison, explicit ordering, source magnitude bands/flags and angular containment. Browser saved and temporary setups pass; site hours/terrain constrain the night model. | **Implementation gap:** the visitor currently chooses targets, then compares equipment. A bounded, explained site/time/gear-guided candidate shortlist, including naked-eye discovery, is not implemented. Do not mark the broader product outcome complete or substitute a universal visibility score. |
| N5 | Web #78/#83 matching-hour optional forecast; browser current and distant-date/out-of-coverage flows pass. New-tab submission preserves the original private POST result. | Forecast coverage is independent of geometric windows; no weather request occurs until requested. These are fixture/local journey checks, not weather-accuracy certification. |
| N6, D3 | Web #81 session JSON/CSV/print; `1b14b81` retains optional calculation source identity and explains older omission. Backend retained replay documents matching source/runtime/IERS/kernel identities, builtin and actual JPL/DST fixtures. Default redacted JSON and native print preview were inspected. | No general session import/replay UI or hosted historical archive is promised. Repetition needs original private inputs and retained compatible resources. Location-inclusive artifact download and final combined print/privacy acceptance remain distinct from automated coverage. |
| C1–C3 | Licensed bounded bright-star/multiple-star/deep-sky samples, offline ingestion, native browsing, exact namespaced IDs, frame/motion evidence and mixed-target planner/journal links. Actual JPL mixed plan is browser-verified. | Current starter browser with the latest catalogue still awaits parent acceptance at this audit checkpoint. M45 remains unsupported; historical doubles are not current companion ephemerides or a complete sky survey. |
| J1–J3 | Lists, statuses/reordering, actual-time/outcome/notes observations and historical setup snapshots; JSON/CSV/print and explicit deletion/undo. Web #85 adds correction/rename and lossless corrupt-storage recovery; independent reviews and Chrome edit/error/undo/reload acceptance pass. | Native import permission remains unresolved. Raw recovery is explicitly unredacted, unlike normal privacy-filtered exports. Browser deletion/undo, narrow correction layout and broader keyboard flows should be recorded where still missing; do not reopen the delivered editing implementation. |
| J4–J5 | #74/#76 encrypted owner-isolated copies, explicit previews/transfers, revision tombstones, account-scope guard and ephemeral previews. Synthetic-account Chrome upload/restore/logout/Back checks pass. | Browser account-switch, concurrent-tab conflict and partial local restore failure have automated coverage but incomplete manual acceptance. LocalStorage has no atomic multi-key compare-and-swap; the residual race is documented, not solved by server revisions. |
| D1 | Backend #40 logical data IDs, separate build/source provenance, mutation/schema invalidation and fixed-backup file checksums. | Full-catalogue finalization cost and any future retained-download policy are not established. IDs cannot recover files no longer hosted. |
| D2 | #79 observed catalogue/cache/download identities; #86 strict optional same-read-transaction page association, cached-row retention and JSON/CSV propagation. Backend #42 tests real WAL writes/replacement and unknown metadata. Planner uses its own packaged source hashes, not the separately observed SQLite ID. | Actual combined API/browser export acceptance is pending. Legacy pages remain unassociated; associated unknown is distinct. A separately probed global ID must never certify returned rows. No all-route or multi-page pinning contract exists. |
| D4 | Scoped scientific/privacy contracts, release runbooks and [unpublished release packet](../RELEASE-1.0.0-REVIEW.md), now reconciled with delivered topics. | Review the exact final combined revision and remaining acceptance before publication. No release ledger, version, production endpoint or domain has changed. |

## Next useful work

1. Finish current PR #84/#85/#86 integration checks and retain the measured map
   workload and current-catalogue browser evidence. Report any new combined failure
   instead of aggregating topic counts into an invented passing total.
2. Design the remaining bounded candidate shortlist using available source data,
   actual model windows and explicitly selected equipment/preferences. Explain
   each inclusion/exclusion and unknown measurement; support naked-eye visitors.
   Keep weather and geometric/equipment facts separate. This is remaining accepted
   product work, not a requirement to ingest a new unreviewed catalogue.
3. Complete feasible local browser acceptance above. Native file import requires
   the outstanding extension permission; no alternate tool should bypass that
   refusal. Retain automated import evidence while reporting the manual gap.
4. Keep physical-device/touch, assistive-technology, lower-power GPU and field p75
   evidence explicitly incomplete until measured. Production provisioning, edge
   retirement and publication are separately authorized operations.

## Scope limits, not invented blockers

The accepted programme does not require a full-Gaia import, motor control, exposure
advice, a universal detection score, an executable session-import UI or indefinite
hosting of every catalogue version. Missing these is not a reason to invent scope.
The related plugin repository remains unidentified. Domain migration, production
changes, external delivery and new permissions remain outside this authorization.
