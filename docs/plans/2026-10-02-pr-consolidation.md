# Frontend PR consolidation audit ? 2 October 2026

## Finding and recommended route

27 open PRs were reviewed against `main` at `4a98a62` and the latest combined
branch `codex/observing-acceptance-integration` at `51ec327`. Only #65 targets
`main`; the other 26 target development branches, including integration bases
without their own PR. The later integration already incorporates or supersedes
25 of the 27 heads; two have later fixes that had not propagated.

Keep one integration PR against current `main` and retain the existing PRs as
review history until it lands. Do not merge all topic branches, rebase every
stack, or squash each checkpoint separately: most work was already cherry-picked
into the combined branch. A stack can appear green against its old base while
still conflicting with current production code.

The isolated branch `codex/pr-consolidation-20261002` combines the latest
integration with `main`, without rewriting any existing branch. It also retains
three stranded corrections: asteroid cursor validation (`3f8ad10`, #65), journal
import error/reset handling (`6af1eaf`, #73), and binocular results on invalid
field input (`244e21a`, the unmerged fix branch linked to #70's review).

After the consolidated PR is validated and approved for deployment, merge it
once. Then close any remaining old PRs as superseded, linking to that merge;
retain branch tips until the coverage ledger has been checked. Do not retarget
all 26 stacked PRs to main: that would turn focused historical diffs into
repeated programme-sized changes.

## Conflicts and semantic overlap

A merge simulation of current `main` and the latest integration found **ten
conflicted files**, despite the individual topic checks mostly being green:

- `.env.example`, `DEPLOYMENT.md`, `deploy.sh`.
- `SitemapController.php`, `Seo.php`.
- Header and footer Blade components.
- The handout generator, template and README.

The candidate preserves main #97's Educators page, static classroom PDFs,
canonical-host SEO and permanent non-redirecting old-host alias. It also retains
the newer observing routes, accessible navigation, branded/versioned share
images, handout validation, account-safe deployment and release ledger. The
older domain-migration runbook is reconciled with #97's hostname policy.

Additional semantic conflicts caught during reconciliation: the handout's
`DEFAULT_SITE`/`DEFAULT_SITE_URL` naming, old unversioned OG expectations, and the
Educators cache test's older browser max-age. Regression checks cover both
canonical-host preservation and the newer share-image versioning. Journal retry
coverage was added; the binocular fix brings its existing regression test.

GitHub reports #68 and #75 as conflicting with their historical bases. Their
changes are already represented in the latest combined branch; those historical
conflicts are not a reason to replay those branches.

## Coverage ledger

?Already incorporated? is evidence against `51ec327`, before the reconciliation
commits. Ancestry, `git cherry` patch equivalence, exact file comparison and
manual review of the exceptional commits were used together; patch-ID alone
would incorrectly flag #67 and #86 as missing.

| PR | Audited head | Disposition |
| --- | --- | --- |
| [#65](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/65) | `c50d4f5` | Foundation retained; #69 squash is superseded by the later planner; recover cursor guard 3f8ad10. Main #97 must also be reconciled. |
| [#67](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/67) | `34ba7a1` | Refresh deduplication retained; TLS 1.2 and broader codex/** workflow triggers supersede the older patches. |
| [#68](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/68) | `b7e1f79` | Already incorporated (identical patch under a different commit). |
| [#70](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/70) | `0d5eb5d` | PR content retained; separately recover unresolved binocular-field review fix 244e21a from its Cursor branch. |
| [#73](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/73) | `6af1eaf` | Journal retained; recover later import error/input reset fix 6af1eaf. Teardown disabling was already present. |
| [#74](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/74) | `954c201` | Already incorporated (head is an ancestor of the integration). |
| [#75](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/75) | `2d90eb2` | Already incorporated (identical patch under a different commit). |
| [#76](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/76) | `19f45e3` | Already incorporated (head is an ancestor of the integration). |
| [#77](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/77) | `e39df13` | Already incorporated (head is an ancestor of the integration). |
| [#78](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/78) | `5635c59` | Already incorporated (identical patch under a different commit). |
| [#79](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/79) | `a740e36` | Already incorporated (identical patch under a different commit). |
| [#80](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/80) | `dd6c26b` | Already incorporated (identical patch under a different commit). |
| [#81](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/81) | `112d676` | Already incorporated (head is an ancestor of the integration). |
| [#82](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/82) | `df60d65` | Already incorporated (identical patch under a different commit). |
| [#83](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/83) | `5f46d1e` | Already incorporated (identical patch under a different commit). |
| [#84](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/84) | `72543c0` | Already incorporated (identical patch under a different commit). |
| [#85](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/85) | `3111845` | Already incorporated (identical patch under a different commit). |
| [#86](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/86) | `24ec3eb` | Seven changed files match the integrated 4acaa0d checkpoint exactly; patch-ID differs because surrounding history differs. |
| [#87](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/87) | `1b14b81` | Already incorporated (head is an ancestor of the integration). |
| [#88](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/88) | `087e70e` | Already incorporated (identical patch under a different commit). |
| [#89](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/89) | `44b66f4` | Already incorporated (identical patch under a different commit). |
| [#90](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/90) | `51ec327` | Already incorporated (head is an ancestor of the integration). |
| [#91](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/91) | `ed54b0c` | Already incorporated (identical patch under a different commit). |
| [#92](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/92) | `f985af2` | Already incorporated (identical patch under a different commit). |
| [#93](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/93) | `553ab9c` | Already incorporated (identical patch under a different commit). |
| [#94](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/94) | `a927d7d` | Already incorporated (identical patch under a different commit). |
| [#96](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/96) | `e36224d` | Already incorporated (identical patch under a different commit). |

## Review threads

Five unresolved threads exist across #70, #73 and #83 at the audit snapshot.
Three concern journal import/reselection/teardown; one concerns invalid binocular
field input; one concerns negative catalogue magnitudes. The candidate includes
the missing journal and binocular fixes. Negative magnitude formatting was
already corrected in the shared `opticalNumber` function (absolute-value
threshold), with regression coverage. GitHub's unresolved/outdated flags are
not proof that a fix is absent or present; the code was checked. Existing threads
have not been marked resolved or posted to during this audit.

## Production dependencies and scope

Repository properties are `main-only`, `production`, `critical`, P0, with
`deployment` automation membership. `CONTRIBUTING.md` says merging main deploys
production. Required branch checks are `tests (PHP 8.4)` and `assets build`, with
an up-to-date base. No production merge or deployment is performed by this audit.

The backend still has **15 open PRs (#31?#45)**. At minimum, catalogue contracts,
night calculations, starter catalogues/coordinate provenance, selected-hour and
horizon support, mixed target planning, target discovery (#43), and interval
coverage (#44) must be reconciled before a coordinated frontend rollout. Optional
legacy responses are supported in places, but that does not make all newly
linked journeys operational against an older backend. The existing frontend
Lighthouse workflow builds backend main and audits only home/Saturn; green
Lighthouse alone cannot certify these new journeys.

The candidate also introduces migrations and the exact-commit/backup-hook deploy
contract from #65. Confirm Forge's actual saved deployment script, persistent
database, backup/restore, key continuity and backend revision before authorizing
main. A repository script is not proof of the live Forge configuration.

Historical browser evidence remains under `docs/qa/`. Native file-import,
physical-device and field-performance limitations recorded there remain limits;
a consolidated automated suite does not retroactively complete that acceptance.

## Validation

Validation results are recorded in [replacement draft #98](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/98). Local tools are PHP
8.4.22 and an isolated Node 22.23.3 installation; dependencies use the lockfiles.
The Windows release-hook suite invokes a Linux shell with Windows paths and
cannot establish the Linux deployment result locally. The Linux CI and local
HTTPS rehearsal remain necessary for the combined revision. All tests use
fixtures or isolated local services; no live customer data is used.

At `ca14452`, Linux CI passed 1,339 PHP tests / 5,556 assertions (the ten
built-asset performance cases are skipped in that job and pass separately),
249 JavaScript tests, Pint, PHPStan, deployment ordering, four loopback transport
checks, release metadata, production assets, fixture budgets, Lighthouse,
handout PDF generation and the local HTTPS publication rehearsal. Windows passed
1,343 of 1,349 PHP tests; its four OG renderer failures and two subprocess
transport timeouts did not reproduce on Linux. The 65 directly affected
canonical-host/Educators/asteroid/contract cases passed locally.

CodeQL initially flagged two inherited test-only selector helpers as incomplete
escaping. They now extract the fixed selector prefix/suffix with `slice`, matching
the journal harness, rather than using chained first-match replacements. Their
24 focused checks pass; the final revision is checked again by CI. These helpers
receive selectors from the test modules, not user-controlled product inputs.
