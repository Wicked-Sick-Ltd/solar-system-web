# First community release: editorial review packet

Prepared 2 October 2026. **Unreleased; no publication or deployment.**
The existing `version.txt` remains `0.0.0`, the bootstrap sentinel. The unpublished
`resources/releases/1.0.0.json` is the single proposed site/community copy. There
is no invented 1.1.0 release or retroactive history. It uses the existing exact
`title`, `summary`, `sections` schema and deliberately says it is a review draft.

The original draft started at `e39df13` (web #77); this review packet is updated
against `1b14b81` and published topics through #86. The draft remains broader
than the code in this documentation branch. It is an editorial proposal for a combined release, not
an assertion that checking out this branch enables all described features.
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
| [#81](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/81), C3/N6 frontend integration | Mixed solar/star/deep-sky plans, source/model metadata, session JSON/CSV/print and journal links | Backend [#39](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/39); independent consumer/export review and selected Chrome checks completed, combined branch acceptance still required. |
| [#83](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/83) | Saved/temporary eyepiece comparison and sourced appearance context | Integrated desktop/mobile and saved-workspace browser checks pass. This compares equipment for already chosen targets; automatic candidate selection is still incomplete. |
| [#84](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/84) | Repeatable route and asset workload budgets | Fixed fixtures and local kernel measurements, not public latency; final combined baseline and actual map/device evidence remain separate. |
| [#85](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/85) | Correct observations, rename lists and recover original unreadable storage | Preserves historical identity/setup. Chrome correction/error/undo/reload passes; recovery files are explicitly unredacted. |
| [#86](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/86) | Exoplanet exports retain optional identity associated with their page rows | Backend [#42](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/42); older responses remain unassociated. Not a multi-page snapshot guarantee; actual combined browser export still pending. |
| Scientific followthrough `1b14b81` | Preserve reported algorithm-source identity and explain older omissions | Backend retained-fixture replay requires compatible source/runtime/kernel/IERS identities; no executable session import or hosted archive is implied. |

Backend [#38](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/38) improves
measured query workloads without changing response hashes. Do not turn its
synthetic warmed-cache benchmark into an end-user speed claim. The draft avoids
publishing kernel/route test timings as general performance promises.

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
- Server encryption is not end-to-end encryption. Guest files remain separate
  from account copies; notes can contain private data even without coordinates.
- Catalogue ID, build ID, source snapshot hash and file checksum have different
  meanings. A separately observed ID does not pin a scientific response.
- Session JSON records inputs and assumptions but is not an executable replay
  service. Redacted exports intentionally lack the private inputs needed to repeat
  a calculation. CSV is a companion window index, not the full provenance record.
- Public Universe branding does not mean the new domain is serving this code.
  No production, DNS, TLS, email or API/MCP client endpoint has changed here.
