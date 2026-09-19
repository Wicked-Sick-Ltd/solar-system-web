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
| `APP_URL`      | yes      | Public URL — drives canonical URLs, OG tags, sitemap, JSON-LD         |
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
at **`sol.wickedsick.com`**, talking to the FastAPI backend on its **own
subdomain** (e.g. `https://api.sol.wickedsick.com/api/v1`), with **Redis** for
cache + queue and **Ceph S3** for OG cards.

**1. Server prerequisites** (one-off, on the Forge box):

- PHP **8.4** with **`imagick`**: `sudo apt-get install -y php8.4-imagick && sudo service php8.4-fpm restart`
- **Redis** (Forge: add it from the server's "Services", or it's already present)
- Node (Forge ships it) — used by the deploy build only

**2. Create the site**

- New Site → `sol.wickedsick.com`, project type **PHP/Laravel**, web directory **`/public`**.
- Repository: `Wicked-Sick-Ltd/solar-system-web`, branch `main`.
- **SSL**: Let's Encrypt for `sol.wickedsick.com`.

**3. Deploy script** — paste [`deploy.sh`](deploy.sh) into the site's Deploy
Script (it pulls, installs `--no-dev`, builds assets, caches config/routes/views,
restarts the queue, and warms the cache). There is **no `artisan migrate`** —
the app has no database.

**4. Environment** — set in Forge's site **Environment** editor:

```dotenv
APP_NAME=Solar
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sol.wickedsick.com

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

- **Non-interactive pages** (`/`, `/planets`, `/about`, `/api`,
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
