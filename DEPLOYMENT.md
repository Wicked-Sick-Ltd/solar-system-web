# Deployment

This is a stateless Laravel front end with **no database**. It needs PHP and a
web server, and it talks to the Solar System DB REST API over HTTP. It runs
happily on a £5 VPS (Laravel Forge), a Cloudflare-fronted PHP host, or a
container — no provider-specific assumptions.

## Requirements

- PHP **8.4+** with the usual Laravel extensions (`mbstring`, `openssl`, `curl`, `dom`, …) plus **`imagick`** (renders the per-object OG share cards)
- Composer
- Node + npm — **build time only**, not at runtime
- A reachable Solar System DB API (`API_BASE_URL`)

## Environment

Copy `.env.example` to `.env` and set at least:

| Var            | Required | Notes                                                                 |
| -------------- | -------- | --------------------------------------------------------------------- |
| `APP_KEY`      | yes      | `php artisan key:generate`                                            |
| `APP_URL`      | yes      | **Canonical** public URL (`https://publicuniverse.net`) — drives canonical tags, OG URLs, sitemap, robots.txt and JSON-LD on every hostname the site answers to; see *Hostnames* |
| `APP_ENV`      | yes      | `production`                                                          |
| `APP_DEBUG`    | yes      | `false` in production                                                 |
| `API_BASE_URL` | yes      | Backend REST root, e.g. `https://api.sol.wickedsick.com/api/v1`       |
| `SOLAR_API_TIMEOUT` | no  | HTTP timeout in seconds (default 8)                                   |
| `CACHE_STORE`  | no       | `file` is fine; `redis` recommended if available (better SWR)         |
| `SESSION_DRIVER` | no     | `file`                                                                |
| `QUEUE_CONNECTION` | no   | `sync` works; a real queue (`redis`/`database`) enables true background cache refresh |
| `CONTACT_EMAIL` | no      | Surfaced on `/about`                                                  |
| `OG_DISK`      | no       | Disk for cached OG cards — `local` (default) or `s3`                  |
| `AWS_*`        | if `s3`  | Ceph RGW bucket + keys for OG storage — see [`docs/CEPH-S3.md`](docs/CEPH-S3.md) |
| `API_DOCS_URL` | no       | Override the backend `/docs` link; otherwise derived from `API_BASE_URL` |

## Build & release

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize          # config + route + view cache
```

If you change env or routes, re-run `php artisan optimize` (or
`php artisan optimize:clear` then `optimize`).

## Laravel Forge (production target)

This is the intended deploy path: a **site on an existing Forge server**, served
at **`publicuniverse.net`** (canonical) with **`sol.wickedsick.com`** as an
alias, talking to the FastAPI backend on its **own subdomain** (e.g.
`https://api.sol.wickedsick.com/api/v1`), with **Redis** for cache + queue and
**Ceph S3** for OG cards.

**1. Server prerequisites** (one-off, on the Forge box):

- PHP **8.4** with **`imagick`**: `sudo apt-get install -y php8.4-imagick && sudo service php8.4-fpm restart`
- **Redis** (Forge: add it from the server's "Services", or it's already present)
- Node (Forge ships it) — used by the deploy build only

**2. Create the site**

- New Site → `publicuniverse.net`, project type **PHP/Laravel**, web directory **`/public`**.
- Repository: `Wicked-Sick-Ltd/solar-system-web`, branch `main`.
- **Aliases** (site → Settings → *Aliases*): add `www.publicuniverse.net` and
  `sol.wickedsick.com`. One site, one deploy, three hostnames.
- **SSL**: Let's Encrypt covering **all three** hostnames (tick every alias
  when requesting the certificate; re-issue it if an alias is added later).
  Cloudflare runs *Full (strict)*, so the origin certificate must be valid for
  whichever hostname is being proxied.
- See *Hostnames: canonical and alias* below for the DNS / Cloudflare side.

**3. Deploy script** — paste [`deploy.sh`](deploy.sh) into the site's Deploy
Script (it pulls, installs `--no-dev`, builds assets, caches config/routes/views,
restarts the queue, and warms the cache). There is **no `artisan migrate`** —
the app has no database.

**4. Environment** — set in Forge's site **Environment** editor:

```dotenv
APP_NAME=Solar
APP_ENV=production
APP_DEBUG=false
APP_URL=https://publicuniverse.net

API_BASE_URL=https://api.sol.wickedsick.com/api/v1   # the backend's subdomain
SOLAR_API_TIMEOUT=8

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# OG share cards on Ceph S3 (see docs/CEPH-S3.md to provision the bucket+keys)
OG_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=solar-system-web
AWS_ENDPOINT=https://s3.wickedsick.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Then `php artisan key:generate` (or set `APP_KEY`).

**5. Queue worker** — add a Forge **Daemon** (or Queue) so background
stale-while-revalidate runs:

```
php artisan queue:work redis --sleep=3 --tries=1 --max-time=3600
```

**6. Scheduler** — enable Forge's **Scheduler** for the site (it installs the
`* * * * * php artisan schedule:run` cron). This drives `solar:warm-cache`
(04:30 + 12:30). Nothing else to add.

**7. CDN** — front the site with Cloudflare and add the cache rule described in
*Headers & edge caching* below so the cookie-less public pages are edge-cached.

## Hostnames: canonical and alias

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
4. Cache rule for the cookie-less pages, as in *Headers & edge caching*.

**Cloudflare (`wickedsick.com` zone)** — leave the existing proxied
`sol.wickedsick.com` record and its *Full (strict)* setting exactly as they
are. Do **not** add a redirect rule for it.

**If `sol.wickedsick.com` is ever retired** (it should not be): it must
**301 permanently, path for path, to the same path on `publicuniverse.net`** —
every path, including `/orrery` (printed on the handouts), `/api`,
`/educators` and the PDF paths under `/educators/*.pdf`, with the query string
preserved — and that redirect must stay in place forever, because the printed
URLs cannot be recalled. Do it as a Cloudflare Redirect Rule on the
`wickedsick.com` zone (hostname equals `sol.wickedsick.com` → dynamic
`concat("https://publicuniverse.net", http.request.uri.path)`, 301, preserve
query string), *not* in application code, so it keeps working even if the app
is down or moves host again.

> **Backend dependency:** the API subdomain must be deployed and reachable
> before launch — the front end is a pure consumer. If it's down the site still
> renders (degradation panels), but it has no data to show.

## Web server

Point the document root at `public/`. Standard Laravel rewrite to
`public/index.php`. HTTPS should terminate at the proxy/load balancer; the app
forces the `https` scheme for generated URLs in production.

## Caching & cache warming

All API responses are cached (see `config/services.php` → `solar.cache`). With a
real queue driver (Redis), stale entries refresh in the background so the cache
never goes cold. The `solar:warm-cache` command pre-warms the hot paths and is
**scheduled** (04:30 + 12:30 daily, see `routes/console.php`) — so as long as
the Laravel scheduler runs, no cron of your own is needed. Run it by hand any
time with `php artisan solar:warm-cache`.

## Headers & edge caching

`SetResponseHeaders` middleware adds baseline security headers to every
response (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
`Permissions-Policy`, `Cross-Origin-Opener-Policy`).

For caching it splits pages in two:

- **Non-interactive pages** (`/`, `/planets`, `/about`, `/educators`, `/api`,
  `/dwarf-planets`) are served **cookie-less** with
  `Cache-Control: public, max-age=120, s-maxage=600, stale-while-revalidate=86400`,
  so a shared cache (Cloudflare) can store one copy for everyone. The sitemap
  and `robots.txt` are public-cacheable too.
- **Interactive pages** (filters, search, sort, pagination) keep Livewire's
  `no-store` — they need the per-request session for CSRF on `wire:*` updates.

To turn this on at the edge, add a Cloudflare **Cache Rule**: *Eligible for
cache* + *Respect origin* TTL for the paths above (or simply "cache everything"
scoped to those routes). Because the app already strips the session cookie on
them, Cloudflare will cache without cookie contamination. Leave everything else
(and `/livewire/*`) on the default bypass.

## Health & resilience

- If the backend is unreachable the site still serves every page (with a calm
  inline panel on the affected section) — it will not 500.
- `robots.txt` and `sitemap.xml` are generated dynamically; the sitemap is
  cached 24h.

## Post-deploy smoke check

```bash
curl -sI https://YOUR_DOMAIN/ | head -1                 # 200
curl -s  https://YOUR_DOMAIN/objects/planet-saturn | grep -o '<title>[^<]*'
curl -s  https://YOUR_DOMAIN/robots.txt | head -1
```

And for the hostnames (both must be 200 — no redirect on the alias — and both
must name the canonical host in SEO surfaces):

```bash
for h in publicuniverse.net sol.wickedsick.com; do
  curl -sI "https://$h/educators" | head -1                                   # 200
  curl -s  "https://$h/educators" | grep -o '<link rel="canonical" href="[^"]*'   # …publicuniverse.net/educators
  curl -s  "https://$h/robots.txt" | grep Sitemap                              # https://publicuniverse.net/sitemap.xml
  curl -sI "https://$h/educators/solar-handout-primary-y1-6.pdf" | grep -i '^content-type'   # application/pdf
done
curl -sI https://www.publicuniverse.net/ | grep -i '^location'              # https://publicuniverse.net/
```
