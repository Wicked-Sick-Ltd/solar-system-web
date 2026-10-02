# First community release: editorial review packet

Prepared 2 October 2026. **Unreleased; no publication or deployment.**
The existing `version.txt` remains `0.0.0`, the bootstrap sentinel. The unpublished
`resources/releases/1.0.0.json` is the single proposed site/community copy. There
is no invented 1.1.0 release or retroactive history. It uses the existing exact
`title`, `summary`, `sections` schema and deliberately says it is a review draft.

The original draft started at `e39df13` (web #77). This packet uses documentation
base `09f416f` (web #90) and additionally records reviewed shortlist frontend
`ed54b0c` (web #91) with backend `a4ef9bb` (#43). The parent integration at
`f81d8cc` includes that frontend and passed combined local validation; CI and
remaining browser/device acceptance are still in progress at this checkpoint.
The draft is broader than the code in this documentation branch. It proposes a combined release; checking out this branch
does not enable every described feature.
Keep a proposed feature out of final publication if its reviewed implementation
and required acceptance are not in the selected release revision.

## Proposed scope and dependencies

All listed pull requests are development checkpoints, not shipped releases.
Earlier foundation/workspace/optics/catalogue/journal changes (#65–73) underpin
this batch; the [programme gap review](plans/2026-10-02-programme-gap-review.md)
separates implementation from acceptance.

| Web checkpoint | Proposed community-facing change | Dependency or remaining check |
| --- | --- | --- |
| [#74](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/74) | Optional private account copy, encrypted server storage, revisions and deletion | Requires new private-workspace migration and recoverable application encryption key; no production migration has run. |
| [#75](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/75) | Explain source coordinate frames, epochs and motion | Backend [#36](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/36); unsupported records stay browsable without a calculation promise. |
| [#76](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/76) | Explicit preview/consent for account upload, restore and removal | #74, guest workspace v2 and journal v1; login alone transfers nothing. Local two-key replacement has documented non-atomic race limits. |
| [#77](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/77) | Selected observing hours and explicit saved-site terrain | Backend [#37](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/37), compatible planner and saved horizon schema; browser-selected site is copied only on request. |
| [#78](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/78) | Separate optional hourly forecast | Explicit private request and provider coverage; no overall viewing score. Combined matching-hour and outside-coverage browser checks are recorded in the observing QA log. |
| [#79](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/79) | Catalogue-aware caching and API/download identity comparison | Backend [#40](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/40); legacy identity unknown is supported. Existing long-lived CDN entries need expiry or a separately approved purge during rollout. |
| [#80](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/80) | Keep live search titles and map-loading guidance current | Focused desktop Chrome acceptance passed; automated detached-title and map retry/disposal regressions. |
| [#81](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/81), C3/N6 frontend integration | Mixed solar/star/deep-sky plans, source/model metadata, session JSON/CSV/print and journal links | Backend [#39](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/39); independent consumer/export review and selected Chrome checks completed. Combined local validation is recorded below; browser and release acceptance remain separately qualified. |
| [#83](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/83) | Saved/temporary eyepiece comparison and sourced appearance context | Integrated desktop/mobile and saved-workspace browser checks pass. This compares equipment for already chosen targets; the separate #91 discovery flow provides the bounded candidate shortlist. |
| [#84](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/84) | Repeatable route and asset workload budgets | Fixed fixtures and local request-kernel measurements, not public latency. The inherited-debug correction is included in #90. Later shortlist checks are recorded separately below, without turning kernel timings into public speed claims. |
| [#85](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/85) | Correct observations, rename lists and recover original unreadable storage | Preserves historical identity/setup. Chrome correction/error/undo/reload passes; recovery files are explicitly unredacted. |
| [#86](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/86) | Exoplanet exports retain optional identity associated with their page rows | Backend [#42](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/42); older responses remain unassociated. Not a multi-page snapshot guarantee. Native Chrome attachment download was blocked; browser artifact acceptance remains incomplete, without bypassing that restriction. |
| Scientific followthrough `1b14b81` | Preserve reported algorithm-source identity and explain older omissions | Backend retained-fixture replay requires compatible source/runtime/kernel/IERS identities; no executable session import or hosted archive is implied. |
| [#88](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/88), `087e70e` | Reconcile accepted behaviour, remaining QA and private account-backup wording | Documentation audit only; distinguishes reviewed implementation, combined validation and operational rollout. It does not mark the programme complete. |
| [#89](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/89), `44b66f4` | Combine redundant galaxy-map redraw requests without an idle render loop | Deterministic renderer-call tests pass. Browser cadence samples are diagnostic, with inconsistent baseline conditions; no causal speed, memory or lower-power-device claim follows. |
| [#90](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/90), checkpoint `09f416f` | Combine the audit, map changes, scientific provenance and retained acceptance evidence | The earlier combined code `a3cb133` passed 1,215 PHP tests / 5,333 assertions and 238 JS tests. Later documentation and workspace wording do not certify the subsequently integrated #91 flow. |
| Workspace wording `749a648` (in #90) | Explain that workspace data stays local by default and a separate explicit backup may upload it | Corrects an unconditional local-only statement; saving or signing in still uploads nothing. Server encryption remains distinct from end-to-end encryption. |
| [#91](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/91), `ed54b0c` | Request an explained target shortlist from a site, night, hours and equipment preferences without knowing target IDs | Requires backend [#43](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/43), `a4ef9bb`, and the existing configured offline provider. Topic tests, independent reviews and a real local JPL transport/validator check pass. Combined local checks pass at `f81d8cc`; CI and remaining browser/device acceptance are distinct. |

Backend [#38](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/38) improves
measured query workloads without changing response hashes. Do not turn its
synthetic warmed-cache benchmark into an end-user speed claim. The draft avoids
publishing kernel/route test timings as general performance promises.

Backend #43 screens 157 supported source-frame starter rows plus the Moon and
seven planets at bounded sampled instants, then refines at most eight selected
targets using the existing night model. Equipment choices affect explained
editorial ordering; an optional V-band cutoff is explicit, not an inferred visual
limit. Unknown source measurements remain unknown. Short/grazing opportunities
can be missed between samples; an empty shortlist does not certify an empty sky.
Only candidates with nonempty refined windows enter the shortlist, with any
remaining grazing uncertainty retained. No weather, detection score or optical
compatibility guarantee is inferred. Source/kernel/IERS identities remain visible.

The independent backend checkpoint passed 498 tests including the actual pinned
JPL kernel; #91 passed 1,297 PHP tests / 5,808 assertions and 235 JS tests on its
own branch. A local actual-JPL request through the frontend transport and strict
consumer returned three validated candidates in 7.73 seconds. These are topic
checks, not the total for a combined release or a public latency guarantee. Do
not add topic test counts together. Parent combined revision `f81d8cc` separately
passed 1,297 PHP tests / 5,809 assertions, 239 JS tests, Pint, PHPStan, production
build and diff checks. Those local results do not replace CI or remaining browser
and device acceptance. Consult the exact selected PR head before release.

The [combined browser evidence](qa/2026-10-02/combined-browser-evidence.md)
retains current-catalogue detail, explicit saved-site copy, actual JPL planning,
map diagnostics and a read-only-copy finalization baseline. That baseline uses
2,431 solar-system objects, 6,366 exoplanets and 4,775 hosts; it does not establish
full minor-planet catalogue cost. Native file import/download restrictions,
physical touch, assistive-technology, lower-power GPU and field performance
remain separately qualified acceptance items. The
[acceptance matrix](plans/2026-10-02-programme-gap-review.md) and chronological
[progress record](plans/PROGRESS.md) retain those boundaries; shortlist browser
evidence and CI results are still being added at this checkpoint.

## Final local integration checkpoint

Combined runtime `c36de8a` adds [web #96](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/96)
(`e36224d`) with [backend #44](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/44)
(`2dfc478`): optional refined altitude/darkness diagnostics over exactly the
selected interval, including unknown numerical boundaries. Older API responses
remain compatible. JSON preserves supplied diagnostics; CSV remains an overview.
The combined runtime also contains [#94](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/94)'s
disposable measurement harness and an independently reviewed correction to
invalid-location guidance when what3words is disabled.

This exact combined runtime passes 1,327 PHP tests / 5,924 assertions with
inherited debug enabled, 247 JavaScript tests, Pint, PHPStan, production build
and diff checks. Backend #44 passes 505 tests including actual JPL and all CI.
The selected integration PR head must still be checked for its own CI result.

Retained [narrow/keyboard evidence](qa/2026-10-02/workspace-acceptance.md),
[account-switch/conflict recovery](qa/2026-10-02/sync/README.md),
[actual render measurements](qa/2026-10-02/galaxy-render-measurements.md) and
[refined constraint browser checks](qa/2026-10-02/constraint-browser.md)
replace the corresponding pending statements at the earlier checkpoint above.
The last report explicitly distinguishes passing live JPL explanations from
unverified final print/download attempts. Native file-import and exoplanet
attachment restrictions, physical touch, assistive technology, lower-power
hardware and required browser acceptance remain incomplete. Exact heap/GPU-byte
accounting is a measurement limitation; field p75 is an eventual target, not an
additional local completion gate. No missing accepted product feature was found
after the A4 follow-up, but the remaining required acceptance prevents declaring
the complete programme finished.

The later [full-catalogue finalization report](https://github.com/Wicked-Sick-Ltd/solar-system-db/blob/89e90de3b37a3bd4e60a985dade0fdef0255d155/docs/performance/full-catalogue-finalization-20261002.md)
extends the smaller baseline with 1,574,019 objects across 14 logical tables:
143.892/144.096/143.808 seconds across three disposable copies, with matching
logical IDs, 32.5–36.3 MiB whole-child peak RSS and unchanged source bytes.
This legacy artifact has no exoplanet/host/starter tables. These are local M3 Max
finalization measurements; no public latency, cold-cache or production claim
follows. The derived IDs were not written to the original download or manifest.

## Review the exact community copy

From this checkout, with locked PHP dependencies installed:

```sh
python3 scripts/check-release.py
php artisan universe:releases:render 1.0.0 --format=markdown
php artisan universe:releases:render 1.0.0 --format=json
```

These render commands do not publish, tag, deploy or send messages. The renderer
adds its own draft marker. Keep rendered output outside tracked release metadata;
edit the JSON source rather than maintaining a second divergent announcement.
The eventual community destination has not been selected or authorized here.

Before any publication, an editor must select the actual integrated scope,
recheck topic dependencies and revise the draft-only section.
Recheck source attribution, scientific limitations and privacy wording against
that exact code and backend. Confirm every claimed browser flow and record
remaining device limitations. Then follow [the existing release workflow](releases.md)
and the separately authorized operational checklist. No approval for that final
publication is implied by this packet.

## Wording boundaries

- A geometric window, clear forecast or in-field angular extent is not evidence
  an observer will detect detail. Historical double separation is not diameter.
  Shortlist equipment modes explain preferences, not visual detection; sampled
  screening can miss opportunities in the bounded catalogue as well as omit
  objects outside that sample.
- Server encryption is not end-to-end encryption. Guest files remain separate
  from account copies; notes can contain private data even without coordinates.
- Catalogue ID, build ID, source snapshot hash and file checksum have different
  meanings. A separately observed ID does not pin a scientific response.
- Session JSON records inputs and assumptions but is not an executable replay
  service. Redacted exports intentionally lack the private inputs needed to repeat
  a calculation. CSV is a companion window index, not the full provenance record.
- Public Universe branding does not mean the new domain is serving this code.
  No production, DNS, TLS, email or API/MCP client endpoint has changed here.
