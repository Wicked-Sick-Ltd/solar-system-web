# Solar System DB — Historical Front-End Handover

**Status:** Historical planning brief; the application has shipped
**Audience:** Maintainers reviewing the original product intent
**Owner:** Craig
**Last updated:** 31 May 2026

> This document is retained as decision history, not as the current setup or
> contributor contract. The shipped stack is Laravel 13 on PHP 8.4 with
> Livewire 4; use [`README.md`](README.md), [`CONTRIBUTING.md`](CONTRIBUTING.md),
> and [`DEPLOYMENT.md`](DEPLOYMENT.md) for current instructions.

---

## 1. What you are building

A public, server-rendered **astronomy reference website** for the general public, students, hobbyists, and sci-fi worldbuilders. It is a clean, modern, accessible, fast read-only browse of the solar system: planets, moons, dwarf planets, named asteroids, periodic comets, notable TNOs and centaurs, and planetary rings.

This is an **astronomy** site, not an astrology site. Do not import any astrological framing, language, glyphs, "houses", or zodiac references anywhere. If a copy decision is ambiguous, lean towards a museum-label / Wikipedia-infobox tone, never a horoscope tone.

The site is a *front end only*. All data comes from an existing REST API (Section 2). There is no database, no auth, no user-generated content, no admin surface.

---

## 2. Backend you are consuming

There is an existing backend repo: **`Wicked-Sick-Ltd/solar-system-db`** (public, GitHub). You do **not** touch it from this repo. You consume its public REST API only.

What the backend provides:

- A nightly-refreshed SQLite database of ~10–15k objects.
- A FastAPI REST API, read-only, with a live OpenAPI spec at `/docs` on the deployed host.
- Endpoints include (non-exhaustive — treat the OpenAPI spec as authoritative, not this list):
  - `GET /api/v1/objects`
  - `GET /api/v1/objects/{id}`
  - `GET /api/v1/planets/{name}/moons`
  - `GET /api/v1/neos`
  - `GET /api/v1/comets/periodic`
  - `GET /api/v1/positions/{id}?date=…`
  - `GET /api/v1/search`
- A custom MCP server **out of scope** for this build.

The public URL will be approximately `https://solar.[domain].com/api/v1/` — exact value supplied via the `API_BASE_URL` env var. Do not hardcode it anywhere.

**First task on day one:** fetch the OpenAPI JSON from the deployed backend and generate or hand-write DTOs from it. The endpoint list above is illustrative; the spec is the source of truth.

---

## 3. Stack (non-negotiable)

- **Laravel** — latest stable at the time you start. As of late May 2026 that is the Laravel 12.x line; confirm before you `composer create-project`, don't trust this doc.
- **Herd** for local development (Laravel team's Mac-native PHP environment). If Herd Pro is available, use a named local site over HTTPS — the project should be reachable at e.g. `https://solar-system.test` locally so OG/share previews can be tested against real TLS.
- **Livewire** for interactive components — this is the default.
  - Trade-off: Livewire keeps everything in the Laravel/Blade world, which is the simplest mental model and shortest path to shipping. Inertia + Vue would give richer client-side interactivity for the orrery and search-as-you-type but doubles the runtime stack and the SEO story gets fiddlier.
  - **Decision rule:** start with Livewire. Only escalate to Inertia + Vue if Livewire genuinely cannot deliver an acceptable orrery or search experience after a real attempt. Document the decision in `README.md` if you flip.
- **Alpine.js** for the small client-side bits where Livewire round-trips are overkill (dropdowns, theme toggle, modal show/hide).
- **Tailwind CSS** for styling. No additional UI kit. Hand-rolled components only.
- **PHP 8.3 or higher.**
- **Vite** for the asset pipeline (Laravel's default).

No SPA framework, no Next.js, no Nuxt, no React. The site is server-rendered HTML with sprinkles of interactivity.

---

## 4. Information architecture

### 4.1 Routes — P0 (must ship)

- `/` — Homepage. Brief intro paragraph (what the site is, that the data is public domain / freely usable, link to the API for devs). A "Featured object today" panel — randomised but deterministic per UTC day, so it's stable while a user shares the link. Quick-browse cards to the major sections. A stats strip showing live counts: *X planets, Y moons, Z asteroids, last refreshed [timestamp]*.
- `/objects` — Browseable, filterable list of all objects. Filters: type, parent body, size range, NEO yes/no, named-only toggle. Server-side pagination — never load 15k rows.
- `/objects/{slug}` — Object detail page. Single canonical template for any object regardless of type. Cleanly laid out: orbital elements (with a small inline visualisation where it adds something), physical properties, visual/observation properties, discovery info, parent body link, child objects (e.g. Saturn's page lists its moons), sources. Include a *"Where is it now"* panel for any object with orbital elements, hitting `/positions/{id}` for today's date.
- `/planets` — Compact landing page for the 8 planets. Visual, editorial, not a table.
- `/planets/{name}` — Planet detail. Uses the same template as `/objects/{slug}` but with the moons section promoted to a sortable table and a rings section if applicable.
- `/dwarf-planets`, `/asteroids`, `/comets`, `/tnos` — Category landing pages. Filtered views over `/objects` with category-appropriate sort and copy.
- `/search?q=…` — Search results, calls `/api/v1/search`. Each result shows object type as a small tag and links to detail.
- `/about` — What this is. Data sources and licensing (NASA public domain, IAU Minor Planet Center freely usable, etc.). How to use the API yourself (link to backend OpenAPI). Credits. Link to the backend repo. The "this is for astronomy not astrology" line. Contact / report-an-error.

### 4.2 Routes — P1 (nice to have, ship if time allows)

- `/orrery` — Interactive 2D solar system visualisation at a chosen date. Planets and selected dwarf planets positioned from `/positions/{id}`. **Do not reinvent.** Use a small custom SVG renderer driven by Alpine, or a thin library like `d3` for the maths only. Keep payload small. This is a flourish, not the product.
- `/api` — Developer-facing landing. Plain-English explainer, a worked `curl` example, rate-limit notes, link to OpenAPI.
- Light/dark theme toggle (default **dark**).
- Per-object permalink with Open Graph + Twitter share preview cards (generated server-side or with a small Blade-based OG-image route).
- "Random object" button, in the header or footer.

### 4.3 Explicitly out of scope

- Login, accounts, profiles.
- Comments, ratings, user-generated content of any kind.
- Server-side state beyond Laravel's session and cache.
- Any direct database access. Everything goes through the API.
- Mobile apps, native wrappers.
- Internationalisation (English only at launch — but don't actively block i18n by hardcoding strings in Blade; use the `__()` helper so a translator could pick it up later).

---

## 5. Data layer

- A single **API client class** in `app/Services/SolarApi/` that wraps the REST endpoints. One public method per endpoint. Returns typed DTOs (use `spatie/laravel-data` or hand-written immutable PHP classes — your call, pick one and be consistent).
- Use Laravel's HTTP client with a base URL pulled from `config('services.solar.base_url')`, populated from `API_BASE_URL`.
- **Cache aggressively.** The backend refreshes nightly, so a 6-hour TTL on most endpoints is fine. Long-lived references (planet list, moon catalogues) can be 24 hours. Live-positional data (`/positions/...`) should be cached at most for a few minutes.
- **Stale-while-revalidate** where it matters: serve cached data immediately, refresh in a background job (Laravel queue, sync driver acceptable for local dev) so the cache never goes cold in production.
- **Graceful degradation.** If the backend is unreachable: render a calm "we can't reach the data right now" panel inline in the affected section, keep the rest of the page working, log the error. Never crash the request. Never surface a stack trace. Health-check the backend cheaply on every request via a cached HEAD/ping with a short TTL.
- Pagination, filter state, and search queries belong in the URL — they should be linkable and back-button-friendly. No state hidden in cookies.

---

## 6. Design language

- **Dark by default.** The subject demands it. Deep navy or near-black background (`#0a0e1a` territory), warm off-white body text (avoid pure white), one or two accent colours — a subtle gold/amber for highlights, a cool blue for links and active states.
- **Typography:**
  - Headings: a clean modern serif — **Newsreader**, **EB Garamond**, or **Crimson Pro** all work. Pick one and commit.
  - Body: a workhorse sans — **Inter** or **IBM Plex Sans**.
  - Self-host the fonts; do not pull from Google Fonts at runtime (privacy + perf).
- **Layout:** generous whitespace, editorial feel, long line-length restraint (max ~70ch on body copy). Not a dashboard, not a database admin tool. Closer to a museum exhibit web page than an SaaS app.
- **Imagery:** no stock photos, no AI slop. NASA imagery where the licence allows (most NASA imagery is public domain — verify per-image). Otherwise elegant SVG or CSS illustrations. If in doubt, ship without imagery rather than with a wrong-feeling one.
- **Mobile-first.** Should look as good on a phone as on a 27" desktop. Test at 360px width regularly.
- **Accessibility:** WCAG 2.1 AA minimum. Semantic HTML before ARIA. Keyboard navigable. Focus states visible and pretty, not hidden. Colour contrast verified — don't rely on amber-on-navy looking "vibey" if it fails the ratio.
- **Motion:** quiet. Respect `prefers-reduced-motion`. No parallax. No starfield background that distracts from text.

---

## 7. Performance budget

- **Lighthouse 95+** on the homepage and on a representative object detail page (use Earth or Saturn for benchmarking).
- **Server response:** under 200 ms cached, under 800 ms uncached. If you're hitting the API on a cold request, do it concurrently where you have multiple calls.
- **Page weight:** under 200 KB on the homepage excluding hero imagery; under 500 KB total including imagery.
- **Critical CSS inlined** in `<head>`. The rest deferred.
- **Images lazy-loaded** (`loading="lazy"` and proper `width`/`height` to avoid CLS). Serve WebP with a JPEG fallback.
- **No render-blocking JS.** Alpine and Livewire scripts at end of body or `defer`.
- **Font loading:** `font-display: swap`, preload the two primary weights.

---

## 8. SEO and metadata

- Server-rendered HTML throughout. Laravel does this naturally — do not accidentally turn it into a SPA via Livewire-everywhere or aggressive client routing.
- Every page: proper `<title>`, meta description, canonical URL, Open Graph tags, Twitter card tags.
- **JSON-LD structured data** on object pages. Use Schema.org `Thing` as a baseline; if a more specific type exists for celestial bodies at the time of build, use it.
- **`sitemap.xml`** generated dynamically from the API, paginated if it exceeds 50k URLs (it won't, but be ready). Cached for 24 hours.
- **`robots.txt`** — allow everything except `/api/*` if you proxy anything, and the admin/debug routes Laravel ships with.
- Object detail URLs are permalinks and must remain stable: prefer slug-based URLs (`/objects/ceres`) over IDs where the API supports it.

---

## 9. Deployment considerations

Out of scope for this build — Craig will deploy — but the repo should be ready:

- Requires PHP 8.3+, Composer, Node (for the asset build only, not at runtime).
- All env-dependent config in `.env`, with a complete `.env.example`. At minimum: `APP_*`, `API_BASE_URL`, `CACHE_DRIVER`, `SESSION_DRIVER`.
- No vendor lock-in. Should run on a £5 VPS via Laravel Forge, a Cloudflare-fronted PHP host, or a container. Don't bake in any provider-specific assumptions.
- Include a one-page `DEPLOYMENT.md` listing the env vars, the build commands (`composer install --no-dev`, `npm run build`, `php artisan optimize`), and the cache-warming cron suggestion.

---

## 10. Repository conventions

- `BRIEF.md` (this document) lives at the repo root from commit one.
- `README.md` covers local dev setup with Herd, how to point at the backend, and how to run tests.
- Pest for tests (preferred over PHPUnit). Cover the API client class and a handful of route smoke tests. Don't aim for 100% — aim for the bits that would silently rot.
- Conventional Commits.
- One PR per route or per concern, not one big-bang merge.

---

## 11. Open questions for Craig

Flag these to Craig **before** building the affected piece — don't guess and force a rework:

1. **API base URL.** What's the exact public URL of the deployed backend? Until known, develop against a local mock or against a staging URL Craig provides.
2. **Front-end domain.** What domain will this site live at? Affects canonical URLs, OG tags, sitemap, and any absolute links in the JSON-LD.
3. **Branding.** Site name, logo, accent palette. Default suggestion if Craig doesn't specify: name *"Solar"* or *"Orrery"*, no logo at launch (wordmark in the chosen serif), accent `#E0B872` amber + `#7AB8FF` blue.
4. **Analytics.** Default: none, in keeping with the public-data-archive ethos. Confirm. If yes, prefer Plausible or a privacy-respecting alternative; never GA without explicit opt-in to it.
5. **Imagery hosting.** Host NASA imagery locally (download + serve from the front-end, better perf and reliability, slightly more storage and licence diligence) or hotlink (zero storage, fragile, possibly rude to NASA). Default suggestion: local for the ~50 hero images on planet/dwarf-planet pages, hotlink or skip for anything else.
6. **Contact mechanism.** None, a `mailto:` link, or a simple contact form (which means email delivery config and basic spam handling)? Default: `mailto:` only at launch.
7. **Orrery scope.** Is `/orrery` truly P1 ship-if-time, or P0 must-have? It's the single biggest variable in scope.

---

## 12. Definition of done

The build is shippable when:

- All P0 routes in Section 4.1 render correctly with live data from a real backend.
- Lighthouse hits 95+ on `/` and on `/objects/{slug}` (Saturn as the benchmark).
- The site renders cleanly when the backend is down (Section 5).
- Mobile (360px) and desktop (1440px) both look intentional, not just functional.
- `BRIEF.md`, `README.md`, `DEPLOYMENT.md`, `.env.example` are all present and accurate.
- A short Loom or written walkthrough handed to Craig covering: how the API client is structured, how to add a new route, how to flush caches.

Anything beyond this is P1.
