<!-- PORTFOLIO_STATE:START company=Wicked Sick hash=e8c78c23 last_sync=2026-11-19T10:05Z -->
## Portfolio context (auto-generated — do not edit between the markers)

Repo company: **Wicked Sick**. Full inventory at `wicked-repo-inventory/PORTFOLIO_STATE.md` (canonical in Notion → Engineering Inventory).

**Secrets:** API credentials live in 1Password (Wicked Sick account), never in committed files. Map env vars to `op://vault/item/field` references in a committed `.env.op` (paths only, no secret values) and run via `op run --env-file=.env.op -- <cmd>` so secrets exist only for that process. Don't hardcode keys or commit real values.

**Reusable drop-ins** (use these before building from scratch):
- `acsendr-billing` — Provider-agnostic subscription billing with idempotent webhooks, a two-sided revenue-share ledger, payouts, and a reconciliation invariant that proves every penny is allocated.
- `acsendr-conformance-suite` — A language-neutral behavioural contract with a thin runner, so a rewrite in another stack can be held to the same assertions instead of hand-translating them and letting them drift.
- `acsendr-connector-economics` — A registry of third-party data connectors recording what each one actually costs to ship — verification regime, lead time, money, and blockers.
- `acsendr-elicitation` — Elicit a person's decision-making identity — behavioural-event questionnaire, public-domain Big Five, and reconciliation against their own written record.
- `acsendr-intake-contract` — One normalised document shape every ingest source produces, plus a deterministic quality gate that runs before anything is paid for.
- `acsendr-mailbox-import` — Mailbox and transcript ingestion with sent-vs-received voice attribution — mbox, .eml, IMAP and VTT/SRT, no OAuth app and no vendor review.

**Other Wicked Sick repos:**
- `HubSpot Order Management` — Bi-directional sync between HubSpot and order systems — scoped, not yet built.
- `pfSense P2P Link` — Point-to-point ISP link with routed /26 block (188.213.95.128/26 over 100.66.66.0/30 transit).
- `Self-hosted UniFi Controller` — UniFi Network Controller running on Proxmox instead of cloud.
- `wicked-amiga/amiga-projects` — —
- `wicked-amiga/battle-isle-remake` — —
- `wicked-amiga/deuteros-app` — Deuteros mobile port (Godot 4) — strategy & licensing stage
- `wicked-amiga/deuteros-parallel` — Parallel Godot port of Deuteros (1991), companion to Deuteros Resurrected
- `wicked-amiga/supremacy-remake` — Source-derived Supremacy reconstruction, extraction tools, and original-CPU validation evidence
- _... +122 more — see /Github/PORTFOLIO_STATE.md_
<!-- PORTFOLIO_STATE:END -->

# Repository Guidelines

## Project Structure

Public astronomy reference for the solar system — a Laravel + Livewire front end over the solar-system-db REST API. For astronomy, not astrology.

- `app/` — application classes and services.
- `resources/` — frontend source and templates.
- `routes/` — route definitions.
- `database/` — migrations, factories, and seeders.
- `tests/` — automated tests and fixtures.
- `config/` — configuration definitions.
- `docs/` — design and operational documentation.

## Development and Validation

Use PHP ^8.4, Node.js (use the version pinned by the project/CI). Run commands from the repository root unless the component documentation says otherwise. Configure local dependencies and test services before application tests.

- `composer install` — install PHP dependencies from the lockfile.
- `composer dev` — start the local development processes.
- `composer test` — run the configured test checks.
- `vendor/bin/pint --test` — check PHP formatting.
- `npm ci` — install JavaScript dependencies.
- `npm run dev` — run the configured development entry point.
- `npm run build` — run the configured build.

Cloud agents install from `.cursor/install.sh` (see the README section "Cloud agent environment"). Tests use the SQLite database in `phpunit.xml`.

## Coding and Testing

Follow `.editorconfig` for indentation, encoding, and line endings. Use Laravel Pint for PHP formatting. Tests use Pest. Keep changes focused and follow existing test filenames. Add regression coverage for behavior changes, using isolated fixtures instead of live customer data. For documentation-only edits, verify commands, local links, and `git diff --check`.

## Working Agreement

Read `CONTRIBUTING.md` and `SECURITY.md` before contributing. Honor directory-specific agent instructions. Preserve existing local changes and use a separate branch or worktree when other work is in progress. Keep credentials, private datasets, and generated artifacts out of commits. Deployment, publishing, and live service changes require authorization for that environment.

Use concise commit subjects consistent with recent history (for example, `docs: clarify setup`). Pull requests should explain the change, link relevant issues, and record validation results and any skipped checks. Include screenshots when user-visible behavior changes.

## Licensing

MIT licensed; see [LICENSE](LICENSE). Preserve third-party notices.
