# Deployment

The website consumes the astronomy catalogue through the Solar System DB REST
API and **has a persistent database of its own** for accounts, visibility
alerts and published release history. Deploying application code must preserve that database, `APP_KEY`, and
private storage. This runbook prepares a release; it does not authorize a live
release or domain cutover.

## Requirements

- PHP **8.4+**, normal Laravel extensions (`mbstring`, `openssl`, `curl`, `dom`,
  the PDO driver for your database), and `imagick` for OG cards.
- Composer and Node **22** + npm at build time.
- A reachable catalogue API (`API_BASE_URL`).
- Persistent SQLite or a supported database server for accounts. SQLite must
  use an absolute path outside disposable releases and a single writable host.
- Redis for production cache, sessions and background cache-refresh jobs; all
  application/scheduler hosts must share the same cache and cache prefix.
- A transactional mail transport if visibility email alerts are enabled.

## Environment and persistent data

Start from `.env.example`. Store secrets outside Git and retain the existing
`APP_KEY` across releases; generating a new key invalidates encrypted data and
sessions. On an existing installation, inspect configuration before editing it.

| Variable | Production requirement |
| --- | --- |
| `APP_KEY` | Stable secret, generated once at first installation |
| `APP_ENV`, `APP_DEBUG` | `production`, `false` |
| `APP_URL` | Canonical origin (`https://publicuniverse.net`); mail links and SEO use it on every serving hostname |
| `DB_CONNECTION`, `DB_DATABASE` | Existing account database; absolute path for SQLite |
| `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` | If using a database server |
| `API_BASE_URL` | Catalogue REST root, e.g. `https://api.sol.wickedsick.com/api/v1` |
| `SOLAR_API_TIMEOUT` | HTTP timeout, defaults to 8 seconds |
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | `redis` recommended |
| `CACHE_PREFIX` | Stable, application-specific; retain during branding changes |
| `SESSION_COOKIE` | Stable name; explicitly configure before changing `APP_NAME` |
| `SESSION_SECURE_COOKIE` | `true` on HTTPS |
| `SESSION_DOMAIN` | Prefer host-only (`null`) |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | Private Redis connection |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Verified sender/transport; `log` does not deliver email |
| `POSTMARK_API_KEY` | Required when `MAIL_MAILER=postmark` |
| `OG_DISK` | `local` or `s3`; see [Ceph storage](docs/CEPH-S3.md) for `AWS_*` |
| `CONTACT_EMAIL`, `API_DOCS_URL` | Optional public contact and backend docs overrides |

Keep `.env`, the account database and backups out of `public/`. For SQLite,
create the parent directory and database file as the application user, with
permissions restricted to that user. Do not point production at a checkout's
throwaway development database. Preserve `storage/` (including private files)
across releases; multi-host deployments also need shared session/cache storage.

## First installation

Provision the persistent database, environment, PHP extensions and storage
permissions first. Keep the site inaccessible and the scheduler disabled until
installation and smoke checks pass. From the repository root:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
# First installation only, if APP_KEY has not already been provisioned:
php artisan key:generate
npm ci --no-audit --no-fund
npm run build
php artisan migrate --force
bash scripts/release-deploy.sh prepare
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Set the web root to `public/` and configure HTTPS. The application forces HTTPS
URLs in production. Configure the scheduler, supervised queue worker and tested
backup procedure below before opening account registration.

## Existing installation: backup and release

The Forge [deploy script](deploy.sh) is an **in-place maintenance release**, not
an atomic or zero-downtime deployment. Use it only for an installed application
with working dependencies. It checks out an exact revision in detached HEAD
state. Configure Forge's `FORGE_SITE_*`, `FORGE_PHP`,
`FORGE_COMPOSER` and `FORGE_PHP_FPM` variables as usual.

Before invoking it:

1. Verify the target host, branch, reviewed commit and pending migrations. Record
   the currently deployed commit and backup location. Live deployment needs
   authorization for that environment. Set `RELEASE_COMMIT` to that full,
   lowercase 40-character SHA. The revision must be reachable from the fetched
   configured branch; moving the branch later must not change the target.
   Confirm that Forge supports the resulting detached checkout.
   **The repository script does not replace Forge's saved deploy script.**
   Install or invoke a reviewed copy of this script explicitly; the old
   branch-pulling wrapper must not run first.
2. Disable the site's scheduler and stop/drain its queue worker and any running
   `alerts:send-visibility` processes. Maintenance alone cannot stop a command
   that is already executing. Prevent deploy hooks from restarting them early.
3. Export `ACCOUNT_BACKUP_HOOK` in the Forge deploy shell to an **absolute path
   to an executable script**. It must back up this installation's account
   database, verify the backup and return nonzero on any failure. It receives
   no arguments and runs from the site root after maintenance begins, before
   code or schema changes. Provision it outside the checkout; do not put
   credentials in the deploy script or output account records into deploy logs.

For SQLite, use its online backup facility (`sqlite3 … '.backup …'`) or an
application-consistent snapshot including WAL state; do not copy just a live
`.sqlite` file. Run `PRAGMA integrity_check` on the backup and periodically
restore it into an isolated environment. For database servers, use the
provider's consistent snapshot/dump procedure and test restoration. Back up
`APP_KEY`/environment separately with restricted access. Encrypt off-site
copies, apply a retention policy, and monitor backup failures.

The script serializes releases with a lock at `<site-path>.deploy.lock` (beside
the checkout, so the lock cannot appear as an untracked file) and checks for a
clean checkout, including untracked files. It fetches the configured branch and
validates the target before
maintenance begins. It then prerenders maintenance HTML, requires a successful
backup, checks out `RELEASE_COMMIT`, installs locked dependencies, builds assets,
clears stale configuration, migrates, writes the build identity, rebuilds
framework caches, signals worker restart, reloads FPM, and brings the site up.
It deliberately avoids `optimize:clear` because that also clears application
cache/lock entries. A failure after maintenance starts and before reopening
**leaves the site down** for investigation. Cache warming after reopening is
best-effort. The final publication hook verifies the live HTTPS build and database
before recording release notes. A failure at this final step means **code is
already active**; investigate and retry publication instead of assuming the site
is still protected by maintenance. See [release workflow](docs/releases.md).
The standalone `errors/503` view
has inline styles and no application layout, JavaScript or asset dependencies,
so ordinary HTML requests stop before Composer autoloading while dependencies
are replaced. On the first upgrade to this script, provision that view before
the release (the previous version used the application layout). Laravel still
bootstraps JSON requests and excluded paths such as `/up`; use a reverse-proxy
503 gate if those must stay independent of PHP during an in-place release.

After successful smoke checks, resume the supervised worker and scheduler.
Verify their logs and delivery failures. Do not run the alert command as a
smoke check against real accounts: it can send mail.

## Scheduler, workers and alert delivery

Install one cron entry per scheduler host (Forge's Scheduler supports this):

```cron
* * * * * cd /path/to/site && timeout 3300 /usr/bin/php artisan schedule:run >> /path/to/private-scheduler.log 2>&1
```

Use the correct PHP 8.4+ executable. `timeout` is GNU coreutils on the Linux
Forge host. It bounds a scheduler process to 55 minutes, shorter than the alert
command's one-hour lock lease. Apply the same bound to manual alert runs.
Monitor timeouts; a catalogue with enough alerts to exceed this limit needs
bounded queued delivery before scaling. Do not silently raise the timeout
beyond the lock lease.

The schedule warms catalogue caches at **04:30 and 12:30** and checks visibility
alerts **every 15 minutes**, in the configured application timezone (UTC by
default). `onOneServer()` and the command's lock need shared cache storage.
Cache prefixes must match across scheduler hosts. Avoid flushing that cache
while alerts are running.

Supervise background cache-refresh jobs, restarting stopped workers:

```bash
php artisan queue:work redis --sleep=3 --tries=1 --max-time=3600
```

Visibility notifications are currently sent **synchronously by the scheduled
command**, not queued; queue retry flags do not retry mail. Transport failures
are reported, leave the alert pending, allow other alerts to proceed, and make
the command exit nonzero. The next scheduled check retries if the object is
still up after dark. Unknown observer data preserves the last known state.
Concurrent command invocations skip while the delivery lock is held.

Delivery is not exactly-once: a process crash after the provider accepts mail
but before the database records it can cause a duplicate on retry. Monitor
mail-provider errors and scheduler exit status. A future durable outbox with
provider idempotency would be required to close that ambiguity. `MAIL_MAILER=log`
records mail bodies (including approximate locations) rather than sending them;
restrict log access and retention, and never call that a delivery test.

## Hostnames: canonical and alias

Configure one Forge site with `publicuniverse.net` as its canonical hostname,
`www.publicuniverse.net` and `sol.wickedsick.com` as aliases, and a valid TLS
certificate covering all three. Keep the document root at `public/`. The
hostname policy below supplements the account-safe release procedure above.

The site answers on more than one hostname. **`publicuniverse.net` is
canonical**; **`sol.wickedsick.com` is an alias that stays live
indefinitely** — the printed classroom handouts on `/educators` and their QR
codes carry `sol.wickedsick.com`, and paper does not get redeployed.

What that means in practice:

- **No host redirect.** There is deliberately no middleware or edge rule that
  301s the alias to the canonical host. A visitor who scans a handout lands on
  `sol.wickedsick.com` and browses there; every link, asset and Livewire
  round-trip stays on the host that served the page.
- **SEO surfaces always name the canonical host.** `<link rel="canonical">`,
  the Open Graph / Twitter URLs and images, JSON-LD, every `<loc>` in
  `sitemap.xml` and the `Sitemap:` line in `robots.txt` are rewritten onto
  `APP_URL` whichever host the request arrived on (`App\Support\Links::canonical()`,
  used by `Seo`, `SitemapController` and `RobotsController`). Search engines
  therefore treat the alias as a duplicate of the canonical host rather than a
  second site. Covered by `tests/Feature/CanonicalHostTest.php`.
- **Nothing hard-codes a hostname at runtime.** `APP_URL` is the single source
  of truth (also for the Mailchimp signup tag and the `tools/handout`
  generator's printed URL). Add a hostname in Forge + Cloudflare and the app
  needs no change.
- **`/educators` uses root-relative URLs** for the PDFs and page images, so the
  same markup works on both hosts.

**Cloudflare (`publicuniverse.net` zone)**

1. DNS: `A`/`AAAA` (or `CNAME`) for the **apex** and **`www`** → the Forge
   server, both **proxied** (orange cloud).
2. SSL/TLS → **Full (strict)**; *Always Use HTTPS* on. The origin presents the
   Let's Encrypt certificate Forge issued for the aliases above.
3. **Redirect `www` → apex**: a Redirect Rule — *if* hostname equals
   `www.publicuniverse.net`, *then* dynamic redirect to
   `concat("https://publicuniverse.net", http.request.uri.path)`, status
   **301**, *preserve query string* ticked. `www` is the one hostname that
   *does* redirect — nothing printed points at it.
4. Cache rule for the cookie-less pages, as in *Headers and edge caching*.

**Cloudflare (`wickedsick.com` zone)** — leave the existing proxied
`sol.wickedsick.com` record and its *Full (strict)* setting exactly as they
are. Do **not** add a redirect rule for it.

**If `sol.wickedsick.com` is ever retired** (it should not be): it must
**301 permanently, path for path, to the same path on `publicuniverse.net`** —
every path, including `/orrery` (printed on the handouts), `/api`,
`/educators` and the PDF paths under `/handouts/*.pdf`, with the query string
preserved — and that redirect must stay in place forever, because the printed
URLs cannot be recalled. Do it as a Cloudflare Redirect Rule on the
`wickedsick.com` zone (hostname equals `sol.wickedsick.com` → dynamic
`concat("https://publicuniverse.net", http.request.uri.path)`, 301, preserve
query string), *not* in application code, so it keeps working even if the app
is down or moves host again.

> **Backend dependency:** the API subdomain must be deployed and reachable
> before launch — the front end is a pure consumer. If it's down the site still
> renders (degradation panels), but it has no data to show.

## Headers and edge caching

Baseline headers include `Permissions-Policy: geolocation=(self)`: same-origin
pages may request a location with browser permission; camera and microphone
remain disabled. Location access still requires HTTPS in browsers.

Anonymous, cookie-free GET responses on `/`, `/planets`, `/about`, `/api` and
`/dwarf-planets` can use shared caching with `s-maxage=600` and
`stale-while-revalidate=86400`. Browser `max-age=0` requires revalidation, and
`Vary: Cookie` separates account-bearing requests. When the newsletter form is
configured these HTML pages retain their private Livewire session response.
Interactive routes need session/CSRF state and are never made public-cacheable.
Authenticated, cookie-bearing and Authorization-bearing requests get
`Cache-Control: private, no-store`. Sitemap and robots responses are public
when requested anonymously without cookies.

At the CDN, **bypass cache lookup and storage for any Cookie or Authorization
header**. Respect origin cache headers and limit HTML caching to the explicit
routes above. Do not rely on every CDN honoring `Vary: Cookie`, and do not use
an unrestricted "cache everything" rule. Bypass `/login`, `/register`,
`/logout`, `/alerts`, `/settings`, `/livewire/*` and all non-GET requests. Purge
previous HTML cache entries when introducing accounts or changing these rules.
Verify the CDN using separate anonymous and authenticated browser sessions.

## Recovery and rollback

For failures before activation, keep maintenance enabled and scheduler/workers
stopped. If the final publication hook fails, code is already active: inspect
`/up/release`, correct the cause and retry `bash scripts/release-deploy.sh publish`
from the active checkout. Do not restore a database just to retry publication.
Record the
failed step and inspect logs without exposing personal data. Prefer fixing the
release forward. Never automatically run `migrate:rollback`: down migrations
may delete account/alert data and older code may not support the new schema.

If reverting code, first verify the previous commit supports the **current**
schema. Restore that reviewed commit and its lockfile dependencies/assets,
rerun `bash scripts/release-deploy.sh prepare` before rebuilding
config/routes/views/events, reload FPM and check locally before
`php artisan up`. If a database restore is necessary, it must be a separately
authorized recovery with an explicit data-loss window; new registrations and
alert changes since the snapshot would be lost. Restore into an isolated target
and verify integrity before replacing production. Preserve the failed database
for investigation with the same privacy controls as backups.

## Post-release checks

Confirm `/up`, `/up/release`, `/whats-new`, the homepage, an object page,
`/login`, `robots.txt` and
`sitemap.xml` respond correctly and use the intended public origin. Check that
`/up/release` matches the reviewed SHA and version, reports database readiness,
and returns `Cache-Control: no-store` through the public HTTPS edge. Bypass
edge caching for this endpoint. At version `0.0.0`, release history stays empty;
a stable release must display its reviewed notes only after publication. Confirm
`php artisan migrate:status` shows the expected schema and
`php artisan schedule:list` shows warming and alerts. Inspect scheduler and
worker logs after resuming them. Validate registration, login/logout, alert
ownership/deletion, and notification content in an isolated staging database
with a mail sink; use no real account data or outbound mail during development.
