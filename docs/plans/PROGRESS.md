# Public Universe progress and continuation

Updated: 2026-10-01. Foundation and accessible-system follow-on complete.
See [development plan](2026-10-01-public-universe.md).

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
3. Physical touch acceptance requires a real device. Handout PDF layout requires
   the actual renderer before publishing a new PDF; HTML-only tests cannot
   certify A4 pagination or clipping.
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
