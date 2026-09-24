# Solar — solar-system-db front end

[![CI](https://github.com/Wicked-Sick-Ltd/solar-system-web/actions/workflows/ci.yml/badge.svg)](https://github.com/Wicked-Sick-Ltd/solar-system-web/actions/workflows/ci.yml)

A clean, public, server-rendered astronomy reference for the solar system:
planets, moons, dwarf planets, asteroids, comets, trans-Neptunian objects and
planetary rings. It is a **front end only** — all data comes from the read-only
[`Wicked-Sick-Ltd/solar-system-db`](https://github.com/Wicked-Sick-Ltd/solar-system-db) REST
API. It includes lightweight user accounts and email visibility alerts; all
astronomical data still comes from the backend API.

This is an **astronomy** site, not astrology.

The full product brief lives in [`BRIEF.md`](BRIEF.md). Deployment notes are in
[`DEPLOYMENT.md`](DEPLOYMENT.md).

## Stack

- **Laravel 13** + **Livewire 4** (full-page components) + **Alpine** (bundled with Livewire)
- **Tailwind CSS 4** via the Vite plugin; self-hosted **Inter** + **Newsreader** fonts (no runtime Google Fonts)
- **PHP 8.4+** (the current Laravel 13 / Symfony 8.1 dependency graph requires 8.4.1)
- **Node 22** for the Vite asset build
- **Pest 4** for tests
- SQLite (or another Laravel-supported DB) for user accounts + visibility alerts
- Filesystem sessions/cache by default; catalogue/object data is fetched from the API and cached.

## Local development

Uses [Laravel Herd](https://herd.laravel.com). The project is expected to be
reachable at `https://solar-system.test` so OG / share previews can be tested
over real TLS.

```bash
composer install
npm ci
cp .env.example .env        # then set API_BASE_URL + APP_URL (see below)
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm run build               # or `npm run dev` for HMR
```

### Pointing at the backend

Everything keys off two env vars — nothing about the backend is hard-coded:

| Var            | Purpose                                              | Example                                  |
| -------------- | ---------------------------------------------------- | ---------------------------------------- |
| `API_BASE_URL` | The backend REST API root                            | `https://api.sol.wickedsick.com/api/v1`  |
| `APP_URL`      | This site's public URL (canonical/OG/sitemap/JSON-LD)| `https://sol.wickedsick.com`             |

**Running the backend locally for development.** The backend repo can be cloned
and run alongside this one. It ships a committed SQLite database and a FastAPI
server:

```bash
# in a clone of Wicked-Sick-Ltd/solar-system-db
python -m venv .venv
.venv/bin/pip install "fastapi" "uvicorn[standard]" "slowapi"
API_PORT=8003 .venv/bin/python api/main.py
```

Then set `API_BASE_URL=http://127.0.0.1:8003/api/v1` in `.env`.
[`docs/openapi.snapshot.json`](docs/openapi.snapshot.json) is a historical
reference copy; it is not automatically synchronized or contract-checked. The
backend's live `/openapi.json` is the source of truth.

## How it's put together

### The data layer (`app/Services/SolarApi/`)

- **`SolarApiClient`** — the single gateway to the backend. One public method per
  endpoint (`objects()`, `object()`, `moons()`, `rings()`, `search()`,
  `position()`, `stats()`, …), each returning typed, immutable DTOs from
  `app/Services/SolarApi/Data/`.
- **Caching** is aggressive and config-driven (`config/services.php` → `solar.cache`):
  reference data 24h, catalogue listings 6h, positions 5m, a health probe 30s.
  Reads use **stale-while-revalidate** — a soft-stale entry is served instantly
  and refreshed out of band by the `RefreshSolarCache` queue job (runs inline on
  the `sync` driver locally; use a real queue in production).
- **Graceful degradation** — a 404 returns `null`/empty (clean "not found"); an
  unreachable backend throws `SolarApiUnavailableException`, which pages catch to
  render a calm inline panel (`<x-api-down>`) while the rest of the page keeps
  working. The request is never crashed and no stack trace is ever shown.
- Pagination is **cursor-style** (the backend returns no total): the client
  over-fetches one row to learn whether a next page exists.

### Routes & pages

Every page is a **full-page Livewire component** (`app/Livewire/`), so the
initial response is fully server-rendered HTML — good for SEO — with Livewire
adding interactivity (filters, search-as-you-type, sortable moon tables) on top.
Filter/search/page state lives in the **URL**, so every view is linkable.

| Route                              | Component                  |
| ---------------------------------- | -------------------------- |
| `/`                                | `Home`                     |
| `/objects`, `/objects/{slug}`      | `Objects\Index`, `Objects\Show` |
| `/planets`, `/planets/{slug}`      | `Planets\Index`, `Objects\Show` |
| `/dwarf-planets` `/asteroids` `/comets` `/tnos` | `Category` |
| `/search`                          | `SearchPage`               |
| `/orrery`                          | `Orrery`                   |
| `/about`, `/api`                   | `AboutPage`, `ApiPage`     |
| `/privacy`                         | `PrivacyPage`              |
| `/login`, `/register`              | Auth controllers + Blade forms |
| `/alerts`                          | `VisibilityAlertController@index` |
| `/random`                          | `RandomObjectController`   |
| `/sitemap.xml`, `/robots.txt`      | `SitemapController`, `RobotsController` |

Object permalinks use the backend's stable `id` as the slug (e.g.
`/objects/planet-saturn`). `/planets/{slug}` reuses the object template but
canonicalises to `/objects/{id}` so the two never compete in search.
Object detail pages also mount `SkyObserver` as a nested Livewire component;
it is not a standalone route.

### Adding a new route

1. Add a `route()` in `routes/web.php` pointing at a Livewire component class.
2. Create the component in `app/Livewire/` and its view in `resources/views/livewire/`.
3. In `render()`, call `SolarApiClient`, wrap calls in `try { … } catch (SolarApiException)`
   and pass an `$apiDown` flag to the view (render `<x-api-down>` when true).
4. Set page metadata with `app(\App\Support\Seo::class)->title(…)->description(…)`.

### Flushing caches

```bash
php artisan cache:clear      # clears all API response caches + the sitemap
```

Cache TTLs are tunable via `SOLAR_CACHE_*` env vars (see `config/services.php`).

## Tests

```bash
php artisan test             # or: ./vendor/bin/pest
```

Tests use `Http::fake` (helpers in `tests/Pest.php`) and never touch the
network. Coverage focuses on the bits that would silently rot: the API client
(DTO mapping, pagination, caching, 404 → null, unreachable → exception) and a
smoke test of every route, including the backend-down degradation path.

## Conventions

- Conventional Commits; one PR per route or concern.
- `./vendor/bin/pint` to format.

## Contributing

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md) for local
setup and the checks CI runs. Wrong *data* belongs in
[`solar-system-db`](https://github.com/Wicked-Sick-Ltd/solar-system-db). For
security problems please follow [SECURITY.md](SECURITY.md) rather than opening
an issue. MIT licensed ([LICENSE](LICENSE)).

<!-- repository-guidance:begin -->
## Contributing and agent guidance

- [Contributor guide](CONTRIBUTING.md): development workflow and validation.
- [Agent instructions](AGENTS.md): shared guidance for Codex and other coding agents.
- [Security policy](SECURITY.md): private vulnerability reporting.

## Repository license

MIT licensed; see [LICENSE](LICENSE). Preserve third-party notices.
<!-- repository-guidance:end -->

## Exoplanets and galaxy explorer

`/exoplanets` searches NASA confirmed planets by name, discovery method and
distance; `/exoplanets/{id}` shows measurements and provenance;
`/systems/{id}` groups a host's planets. `/galaxy` provides a Three.js nearby-system
map and schematic Milky Way overview. Its route-specific bundle is loaded only
on the map page. Keyboard system selection and a linked list remain available
when WebGL is unavailable. The renderer draws on demand, caps pixel ratio and
cleans up resources on navigation.

Requires `solar-system-db` schema v4 and its `/exoplanets`, `/exoplanet-hosts/{id}`
and `/galaxy` endpoints. Deploy the backend and new catalogue first. Missing
list/map support shows an unavailable panel. The frontend fetches its map payload
through `/galaxy/data` and the existing cached API client; browsers never contact
NASA directly. Map distances use parsecs internally and light-years in labels.
Markers represent host systems; their sizes and the galaxy outline are illustrative.

Additional JavaScript checks: `node --test tests/js/*.test.js`.
