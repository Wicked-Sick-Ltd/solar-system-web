# Public Universe releases

Application deployments and community releases are related but distinct. A Git
commit identifies code. A stable version identifies a useful batch of changes.
A successful live readiness check gates the on-site publication of its reviewed
notes. A GitHub tag or a passing build alone is not proof of deployment.

The workflow follows project-gambit's separation of generated technical history,
reviewed community notes and a durable publication ledger. This website has no
community-message dispatcher: rendering copy does not send it anywhere.

## First release and version policy

`version.txt` starts at `0.0.0`, a bootstrap sentinel, with a draft at
`resources/releases/1.0.0.json`. The draft is not a public announcement and the
sentinel publication hook verifies live readiness but inserts no public release. The first community release is planned as
1.0.0; do not invent historical releases or backdate publication.

Use stable MAJOR.MINOR.PATCH versions. Compatible fixes are patches, features
are minor releases, and incompatible supported behaviour/integration changes
require a major release. A large feature alone is not a breaking change. Keep
Conventional Commit PR titles: `fix:`, `feat:`, and explicit `!`/`BREAKING CHANGE`
when appropriate. See [Semantic Versioning](https://semver.org/).

Release Please prepares the technical `CHANGELOG.md`, version file and manifest
in a release PR. It does not replace editorial review of community notes. Review
a useful batch roughly weekly during active development; urgent fixes may be
released sooner. Release PRs must pass the same checks and review as other work.
The largest compatible-change category determines the batch's version.

## Write once, use on the site and in the community

Prepare `resources/releases/MAJOR.MINOR.PATCH.json` with a title, summary and
sections of plain-text bullets. Write for children, teachers, observers and
researchers: explain what became possible, what was corrected, known limitations
and any action a user needs to take. Keep internal implementation detail and
security exploit specifics out of community notes. The initial draft covers the
Public Universe foundation and accessible measured-system directory.

The file must contain exactly `title`, `summary`, and `sections`. Limits are
120 characters for title, 500 for summary, 1–6 sections, 80 per section heading,
1–10 bullets per section and 500 per bullet; total file size at most 32 KiB.
Use non-numeric section headings and plain single-line text without HTML,
control characters or angle brackets. Markdown is not needed in the JSON.
Published note files are immutable; corrections belong in a subsequent release.

Render review copy locally:

```sh
php artisan universe:releases:render 1.0.0 --format=markdown
php artisan universe:releases:render 1.0.0 --format=json
```

The command can render a draft for review. It does not publish the site, create a
GitHub release or send a message. When ready, community copy should link to the
verified permanent `/whats-new/MAJOR.MINOR.PATCH` page. Select the actual community
destination before introducing automated delivery. No general outbound-message
authorization is implied by preparing copy.

## On-site publication

Visitors see published notes at `/whats-new`, with permanent version pages.
Only versions at or below the running application version are visible; draft
files alone never appear publicly. A rollback hides newer records without
rewriting their notes or original publication date. The release ledger survives
cache clears and belongs in the existing account-database backup/recovery plan.
It contains public release history, not account-specific read receipts.

`/up/release` exposes only the running version, source commit and database-ready
flag. It is never cached. It must fail readiness when build identity or the
release-ledger database table is unavailable. No request-time Git/shell commands
or environment dumps are used to supply the public build identity.

The publish command requires the exact prepared commit and validates the live
HTTPS endpoint without redirects, using a fresh nonce and no-store requests.
The returned version, commit and database readiness must match. Only after this
check can it persist the reviewed notes. Repeating publication of the same
version preserves the first notes, date and commit; it does not re-announce it.

## Automation activation

The release workflow is deliberately inactive until a dedicated GitHub App is
configured for **Wicked-Sick-Ltd/solar-system-web only**. It needs repository
Contents, Issues (labels) and Pull requests write access; no new access is
provisioned by these files. Set repository variable `RELEASE_APP_ID` and secret
`RELEASE_APP_PRIVATE_KEY` through the established secure process after approving
that separate access grant. Do not reuse another project's credentials or widen
its installation silently.

A short-lived App token lets the resulting PR events trigger CI. The repository
`GITHUB_TOKEN` is not a fallback because its generated events generally do not
trigger new workflow runs. See the official
[Release Please authentication guidance](https://github.com/googleapis/release-please-action#github-credentials).

Require release metadata checks alongside existing CI. Without App setup, the
same metadata checks and reviewed notes can support a manually prepared release
PR; keep the version file and manifest in agreement. Neither path authorizes a
production merge, a domain cutover, new credentials or a community message.

## Deployment and recovery

Follow [DEPLOYMENT.md](../DEPLOYMENT.md) for persistent accounts, backup/drain,
maintenance and recovery. The release must use a reviewed exact source revision,
not whatever happens to arrive on a moving branch during deployment. Prepare the
build stamp after checkout and before configuration caching. Publish notes only
as the final step after successful activation and required process restarts.

A failed readiness/publication step can occur after the new code has started
serving. Diagnose the running version and database first; retry the publication
hook from that active release after correcting the cause. Do not blindly roll
back or restore an older account database over new writes. A version rollback
and an account-data restore are separate operations.

For the first production release, record the approved environment/host, current
and target commits, backend prerequisites, backup/restore evidence, scheduler and
worker drain plan, prepared migration/rollback commands, smoke results and
release-note preview. The publicuniverse.net domain migration remains a separate
change governed by [its runbook](PUBLIC-UNIVERSE-MIGRATION.md).

The automatic readiness check certifies the web build and release database. It
does not prove scheduler, queue worker or email delivery health. Resume drained
processes after the required smoke checks and verify their logs before marking
the operational rollout complete; never send real visibility alerts as a test.
