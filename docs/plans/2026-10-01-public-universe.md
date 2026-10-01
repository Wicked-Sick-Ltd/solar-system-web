# Public Universe development plan

Status: foundation implemented; final validation recorded in PROGRESS.md.
Owner: Craig. Started: 2026-10-01.

## Objective and boundaries

Make astronomical data accessible from first discovery to scientific reuse,
using the existing Laravel/Livewire website, Python catalogue/API/MCP and related
plugin. Prepare the move to publicuniverse.net while preserving object links and
existing consumers. Astronomy only. Public browsing and learning remain free and
available without an account.

Craig authorized planning and autonomous implementation, persistent goals,
subagents, worktrees and adversarial review on 1 October. Development changes and
local Git checkpoints are authorized. Production changes, DNS/TLS cutover,
auto-deploying merges, new access grants, purchases and outbound messages remain
outside this development scope. Do not execute historical rollout plans.

## Initial evidence (before this development run)

- Web baseline: `13c40ef`; database baseline: `12af96b` plus local documentation
  handoff `55df0ae`. Preserve the database handoff branch.
- Full catalogue rollout and meteor ingestion plans are archived as landed.
- Exoplanet search/detail/hosts and galaxy explorer are implemented. The
  24 September QA covers desktop navigation and mobile layout, not physical
  touch interaction or all keyboard controls.
- Accounts, alerts, weather and close approaches exist. Deployment/security
  documentation and deploy.sh still incorrectly assume no account database.
- Global search only searches solar-system objects; asteroid category filters
  and meteor-shower web pages remain missing.
- Exoplanet IDs change on upstream rename. The map is a measured exoplanet-host
  sample with a schematic disk, not a complete stellar or spiral-arm survey.
- Plugin checkout is missing; requested its path. Continue independent work.
- GitHub returned no open issues/PRs for web or database at initial review.

## Delivery sequence and acceptance

### M1 — dependable, migration-ready foundation

1. Correct release procedure for persistent account data, migrations, backups,
   queues/scheduler and rollback. Fix geolocation policy. Review account/alert
   flows and address concrete defects with isolated regression coverage.
2. Introduce Public Universe branding and broader editorial copy, maintaining
   configurable production URLs and existing routes. Prepare a detailed domain
   migration runbook including website, API, MCP, download compatibility, TLS,
   canonical/OG/sitemap links, mail, cookies and browser preferences.
3. Unite solar-system and exoplanet discovery using existing API capabilities,
   typed data and independent degradation. Preserve useful results when only
   one catalogue fails. Search remains URL-addressable and accessible.
4. Complete available local galaxy keyboard, selection and lifecycle checks;
   record browser evidence and distinguish real touch checks from simulations.

Exit: relevant tests, Pint, PHPStan, JavaScript checks and asset build pass;
review findings resolved; current setup and migration docs are accurate; browser
QA evidence captured where available. No live deployment implied.

### M2 — expose the data already available

1. Meteor-shower list/detail pages using existing backend endpoints, all
   parameter sets and parent-body links; clearly label approximate activity
   windows instead of promising viewing conditions or rates.
2. Asteroid URL-bound filters mapped to existing API fields and reliable deep
   pagination, including reset, empty-state and invalid-input handling.
3. Connect Explore, Observe, Learn and Data entry points with existing pages.
   Start a small learning experience using sourced explanations, comparisons,
   existing handouts and progressive detail; no fabricated astronomical values.

Exit: guest journeys are linkable, keyboard usable and tested for unavailable
data; responsive browser review completed for new surfaces.

### M3 — scientific reuse and durability

1. Add scoped filtered CSV/JSON exports with units, provenance/retrieval metadata,
   explicit limits and injection-safe CSV handling; preserve uncertainties and
   missing values. Avoid claiming a reproducible snapshot without its identity.
2. Add cross-interface contract fixtures/checks for new catalogue surfaces;
   retain compatibility with older API schemas and explicit absence states.
3. Implement conservative upstream rename/alias reconciliation only where
   trustworthy identifiers support identity; preserve historic URLs and handle
   withdrawn records explicitly. Never fuzzy-merge distinct scientific objects.
4. Review plugin compatibility once its repository is located.

Exit: exports round-trip representative values and documented metadata;
contracts have offline fixtures and regression tests. Alias implementation is
conditional on trustworthy source identity, as required above.

Implementation decision (1 October): selected archive fields contain no reliable
planet-level alias relation. Automatic reconciliation is deferred; the inspected
limitation and requirements for a persistent cited registry are recorded in
[contract/identity notes](../catalogue-contract.md). The plugin remains
unavailable. These are follow-on dependencies, not claims of implemented support.

### Follow-on: community releases and deployments

Requested on 1 October after the accessible-system slice. Follow project-gambit:
reviewed community notes and generated technical history, stable versions,
exact-revision deployment verification and durable on-site publication. Use one
JSON source for both `/whats-new` and locally rendered community copy. No
outbound dispatcher is needed until a destination is selected.

Implementation and validation are recorded in [progress](PROGRESS.md); operational
steps are in [releases](../releases.md). First rollout must resolve the destination
and verify current build, backups and scheduler/worker handling. Repository-scoped
release-bot access and the publicuniverse.net migration are separate decisions.

### Later, separately sized work

Artificial satellites, larger stellar catalogues, fuller curricula and
translation are subsequent extensions. Reassess after M1–M3. Do not launch
additional catalogue ingestion as part of a branding change.

## Work loop

1. Read this plan and PROGRESS.md, inspect Git state and applicable instructions.
2. Select the highest-priority ready slice; record ownership and dependencies.
3. Use independent worktrees for parallel agents; assign disjoint files where
   possible. Main agent owns integration and shared navigation/routes/fixtures.
4. Implement a coherent change and relevant regression tests with offline data.
5. Run targeted checks; review the actual diff and integrate committed work.
6. Have a separate agent challenge correctness, privacy, compatibility and
   acceptance evidence. Fix material findings before checkpointing.
7. Run the combined required checks once per coherent integration. Capture
   screenshots for user-visible work; report unavailable physical-device checks.
8. Commit useful milestones and update PROGRESS.md with exact validation,
   limitations and the next ready task. Continue without milestone approvals.

On interruption, leave all work and branches intact. Never claim a goal complete
because a session is ending. Continue independent work if a plugin or external
environment remains unavailable. Production readiness is distinct from rollout.

## Validation

Web: locked Composer/npm installs; `vendor/bin/pint --test`,
`vendor/bin/phpstan analyse --no-progress`, `php artisan test`,
`node --test tests/js/*.test.js`, `npm run build`, `git diff --check`.
Tests use Http::fake and in-memory account data; never send real notifications.
Deployment scripts are checked with shell syntax and mocked executables rather
than run against Forge. Local browser services bind to loopback and use isolated
fixtures/data, with no live scheduler or outbound email.

Database changes: follow its AGENTS.md install and offline verify/API/MCP suite;
never rebuild over the retained local catalogue or commit generated databases.

## Reference material

- [Web QA](../qa/2026-09-24/README.md)
- [Current web setup](../../README.md)
- [Historical brief](../../BRIEF.md)
- Database docs: `docs/SESSION_HANDOFF.md`, `docs/EXOPLANETS.md`, and archived
  full-catalogue/meteor plans in the sibling repository.
- [Google domain-move guidance](https://developers.google.com/search/docs/crawling-indexing/site-move-with-url-changes)

