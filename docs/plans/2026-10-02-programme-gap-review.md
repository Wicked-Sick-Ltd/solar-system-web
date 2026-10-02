# Observing programme: independent acceptance audit

Updated 2 October 2026 against the combined acceptance branch through runtime `c36de8a`,
published web review slices through #96, backend through #45, and the
[accepted programme](2026-10-01-observing-programme.md). This documentation branch
does not itself contain every reviewed topic. The [progress log](PROGRESS.md)
records integration checkpoints; a passing topic suite does not establish that
the final combined revision passed. No release or deployment is claimed.

## Acceptance matrix

| Accepted items | Implemented/reviewed evidence | Remaining acceptance or implementation |
| --- | --- | --- |
| P1 | Local foundation and observing screenshots; native catalogue filters, skip-to-main, search-title updates, settings inventory, explicit WebGL activation and keyboard camera controls. Web #80 fixes the two reproduced browsing defects. | [Current browser evidence](../qa/2026-10-02/workspace-acceptance.md) covers 320px shortlist/camera/journal, landscape menu Escape/focus return, observer error focus, settings radio keys and native planning with page scripts blocked. This does not establish globally disabled JavaScript/noscript or reduced-motion browser acceptance. User-assisted native workspace and journal imports now pass without permission changes; successful reload/export comparisons are recorded in that report. Physical touch and assistive-technology acceptance are separate. |
| P2, P6 | Web #84 retains ten fixed-fixture request workloads, cold/fresh counts, seven-pair latency/memory samples and built-asset budgets, including an actual eight-target response fixture. | Combined integration exposed debug-environment contamination of runtime counts. PR #84 follow-up `72543c0` pins both PHP environment adapters before boot; an independent inherited-debug run passed all ten cases. The combined shortlist revision `f81d8cc` passed 1,297 PHP tests/5,809 assertions and 239 JS checks; see the progress checkpoint. The final A4 integration at `c36de8a` passes 1,327 PHP tests/5,924 assertions and 247 JS tests, Pint, PHPStan and build. Actual map callback-cadence evidence and independently tested paint coalescing are retained in [combined browser evidence](../qa/2026-10-02/combined-browser-evidence.md). The mixed unloaded baseline prevents a causal browser-speed claim. [Three actual render runs](../qa/2026-10-02/galaxy-render-measurements.md) now retain CPU submission/GPU elapsed-query timings, draw counts and disposal resource counters. Counters are not heap/GPU bytes or driver-reclamation proof. Lower-power hardware and field p75/Core Web Vitals remain unmeasured. Mock HTTP kernel timings exclude provider/network/browser work. |
| P3 | Web #67 multiprocess queue lease/retry coverage; later catalogue/weather producer-lease regressions prevent obsolete cache publication. | Preserve combined generation/queue regression checks. Authorized rollout must restart/drain workers as documented; this is an operational prerequisite, not an absent queue implementation. |
| P4 | Backend #38 measured representative object/exoplanet queries with unchanged output hashes; vectorized terrain crossings and one batched planner request avoid per-time HTTP fan-out. | [Retained-catalogue finalization](../qa/2026-10-02/combined-browser-evidence.md) was measured on three disposable copies (6,366 exoplanets/4,775 hosts), with original bytes unchanged. [Full minor-planet finalization](https://github.com/Wicked-Sick-Ltd/solar-system-db/blob/89e90de3b37a3bd4e60a985dade0fdef0255d155/docs/performance/full-catalogue-finalization-20261002.md) now measures three disposable copies of 1,574,019 objects: 143.892/144.096/143.808 seconds, 32.5–36.3 MiB whole-child peak RSS, matching derived IDs and unchanged input. This legacy input has no exoplanet/host/starter tables. Warm synthetic queries and selected planner workloads do not establish production throughput. |
| P5 | Bounded private planner/weather/sync transport and inputs; #79 catalogue-generation fencing and private response isolation. #86 retains response-associated exoplanet metadata with cached rows. | Verify combined route/header/cache checks. Old edge entries need expiry or separately authorized purge; new headers cannot clear earlier responses. Page identity does not pin a later page or every catalogue route. |
| E1–E5 | Stable bounded guest profiles/sites, v1/v2 migration, cameras, circular masks, explicit activation/copy, settings/privacy integration. Browser creation, rounded site/mask persistence, saved optics reload, site copy and native workspace export are recorded. | User-assisted native v2 workspace import/reload/export exactly matches the synthetic fixture; malformed/stale/quota/storage-denied flows have isolated tests. Camera save/edit/reload, unknown then known pixel size, field geometry and journal historical snapshot correction/undo are now [separately browser-verified](../qa/2026-10-02/workspace-acceptance.md). This is desktop evidence, not physical-device acceptance. |
| A1–A6 | UTC/legacy Moon correction; bounded true lunar/planet calculations, selected hours, DST/polar/grazing/terrain cases, Moon constraints, pinned JPL option and selected independent Horizons references. The final A4 gap is implemented in backend #44/web #96: refined independent altitude/darkness coverage, scoped to the evaluated interval. [Actual JPL Chrome cases](../qa/2026-10-02/constraint-browser.md) distinguish altitude-satisfied/daylight from dark/below-altitude and full-window cases. | Accuracy evidence is limited to the retained tested cases and model assumptions. Production kernel/IERS provisioning and broader field validation are not claimed. |
| N1–N3 | Native private form, selected hours, chart/table equivalents, explained windows/statuses and telescope/binocular/camera geometry. | Final combined keyboard/assistive-technology checks remain. No optical compatibility or visual-detection guarantee is intended. |
| N4, C4 | Web #83 integrates saved/temporary eyepiece comparison, explicit ordering, source magnitude bands/flags and angular containment. Browser saved and temporary setups pass; site hours/terrain constrain the night model. | Backend #43 and web #91 now implement the bounded site/time/equipment-mode shortlist, with source field/magnitude choices and refined windows. [Actual JPL browser checks](../qa/2026-10-02/shortlist-browser.md) cover naked-eye/telescope differences, selected hours, honest empty results and target-only handoff. This closes the implementation gap; 320px native calculation, explanation and keyboard-scrollable numeric table also pass. Broader device acceptance remains distinct. There is no universal visibility score. |
| N5 | Web #78/#83 matching-hour optional forecast; browser current and distant-date/out-of-coverage flows pass. New-tab submission preserves the original private POST result. | Forecast coverage is independent of geometric windows; no weather request occurs until requested. These are fixture/local journey checks, not weather-accuracy certification. |
| N6, D3 | Web #81 session JSON/CSV/print; `1b14b81` retains optional calculation source identity and explains older omission. Backend retained replay documents matching source/runtime/IERS/kernel identities, builtin and actual JPL/DST fixtures. Default redacted JSON and native print preview were inspected. | No general session import/replay UI or hosted historical archive is promised. Repetition needs original private inputs and retained compatible resources. The parent recovered a 12,831-byte opt-in download containing location and all four terrain points; the 12,253-byte default omitted them. The user recovered the native session JSON and printed PDF; [the report](../qa/2026-10-02/constraint-browser.md) verifies default redaction/source retention and the subsequent print-spacing fix on a three-target case. CSV was not repeated in this assisted pass. Current automated retention/redaction checks pass. |
| C1–C3 | Licensed bounded bright-star/multiple-star/deep-sky samples, offline ingestion, native browsing, exact namespaced IDs, frame/motion evidence and mixed-target planner/journal links. Actual JPL mixed plan is browser-verified. | The parent verified current native NGC0224 search, source detail, target-only preparation, saved-site copy and actual JPL calculation against backend #42; see [browser evidence](../qa/2026-10-02/combined-browser-evidence.md). Broader narrow-screen/keyboard acceptance remains separate. M45 remains unsupported; historical doubles are not current companion ephemerides or a complete sky survey. |
| J1–J3 | Lists, statuses/reordering, actual-time/outcome/notes observations and historical setup snapshots; JSON/CSV/print and explicit deletion/undo. Web #85 adds correction/rename and lossless corrupt-storage recovery; independent reviews and Chrome edit/error/undo/reload acceptance pass. | User-assisted journal import/reload and default/full JSON exports now pass exact fixture comparisons without permission changes. The transient import preview was completed before agent inspection. Raw recovery is explicitly unredacted, unlike normal privacy-filtered exports. Desktop camera observation correction/undo/reload is now recorded separately. Narrow correction/undo/list rename and keyboard interaction now pass; see the same report. Broader assistive-technology acceptance remains separate. |
| J4–J5 | #74/#76 encrypted owner-isolated copies, explicit previews/transfers, revision tombstones, account-scope guard and ephemeral previews. Synthetic-account Chrome upload/restore/logout/Back checks pass. | [Native two-account checks](../qa/2026-10-02/sync/README.md) now cover account switching, stale upload against a deleted copy, stale local restore rejection and fresh-preview recovery. Partial local write failure/rollback remains automated-only evidence. LocalStorage has no atomic multi-key compare-and-swap; the residual race is documented, not solved by server revisions. |
| D1 | Backend #40 logical data IDs, separate build/source provenance, mutation/schema invalidation and fixed-backup file checksums. | Retained exoplanet and full minor-planet finalization costs are now recorded separately. The derived benchmark IDs belong only to disposable copies; the public legacy artifact and its manifest were unchanged. A future retained-download policy is not established. IDs cannot recover files no longer hosted. |
| D2 | #79 observed catalogue/cache/download identities; #86 strict optional same-read-transaction page association, cached-row retention and JSON/CSV propagation. Backend #42 tests real WAL writes/replacement and unknown metadata. Planner uses its own packaged source hashes, not the separately observed SQLite ID. | Current About metadata passed against the new API. An earlier automated exoplanet attachment attempt was blocked; the user now completed the native JSON download without changing settings. Its 11 local fixture rows, source fields and same-read-transaction identity passed inspection. CSV was not repeated in this assisted pass. Legacy pages remain unassociated; associated unknown is distinct. A separately probed global ID must never certify returned rows. No all-route or multi-page pinning contract exists. |
| D4 | Scoped scientific/privacy contracts, release runbooks and [unpublished release packet](../RELEASE-1.0.0-REVIEW.md), now reconciled with delivered topics. | Review the exact final combined revision and remaining acceptance before publication. No release ledger, version, production endpoint or domain has changed. |

## Next useful work

1. Review the final integration PR #90 and its exact CI head. A4 backend #44
   passes 505 tests and all CI; web #96 passes its independent review and all CI.
   Combined `c36de8a` passes 1,327 PHP/247 JS tests and static/build checks. The
   final audit found no further missing accepted product feature; acceptance
   limits below remain open and are not permission to invent more scope.
2. Retain the delivered bounded shortlist (#91/backend #43), native narrow and
   keyboard, account-conflict, catalogue, and actual renderer evidence. Preserve
   sampling, unknown-measurement and privacy limits. Actual final scientific explanations pass. User-assisted native print and JSON downloads now supply inspected artifacts;
   retain their recorded inputs, privacy modes and revision limits.
3. Retain the successful user-assisted native imports and JSON exports. No
   extension permission or browser security setting changed. Transient previews
   were completed before agent inspection; CSV was not repeated in this pass.
4. Keep physical-device/touch, assistive-technology, lower-power GPU and field p75
   evidence explicitly incomplete until measured. Production provisioning, edge
   retirement and publication are separately authorized operations.

## Scope limits, not invented blockers

### Remaining completion gates versus reported limitations

The accepted P6 explicitly includes lower-power devices. Current M3 Max browser
measurements do not satisfy that requirement. User-assisted native workspace/journal imports, JSON downloads and inspected
print PDFs have now resolved the earlier automation-specific acceptance blocks.
Their evidence is scoped to the retained fixtures and workloads; previews and
CSV were not independently re-observed in that pass. Lower-power hardware
remains a genuine acceptance dependency.

The accepted plan explicitly describes field p75/Core Web Vitals as **eventual
targets**, not existing results, and excludes live load testing and new analytics
from local acceptance. Production provisioning/domain changes/publication remain
outside this development authorization. Exact heap/GPU-byte accounting is a
reported measurement limitation, not a separately promised deliverable. Do not
invent these as additional completion gates or silently convert local laboratory
measurements into field claims. Full-catalogue finalization measurement extends
the performance evidence; it does not alter the accepted feature scope.

The accepted programme does not require a full-Gaia import, motor control, exposure
advice, a universal detection score, an executable session-import UI or indefinite
hosting of every catalogue version. Missing these is not a reason to invent scope.
The related plugin repository remains unidentified. Domain migration, production
changes, external delivery and new permissions remain outside this authorization.
