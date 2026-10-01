# Reproducible route and asset performance budgets

This extends the [request-count/cache-refresh baseline](performance.md) with
repeatable built-asset and request-kernel measurements. The workload is entirely
local and deterministic: no production requests, real accounts, locations,
provider calls or new browser automation. Existing browser Lighthouse checks
remain separate and advisory for performance.

## Reproduce

Use PHP 8.4 and Node 22, then run from the repository root:

```bash
composer install
npm ci
npm run build
mkdir -p /tmp/public-universe-performance
node tools/performance/assets.mjs > /tmp/public-universe-performance/assets.json
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
PERFORMANCE_REQUIRED=1 PERFORMANCE_REPORT=/tmp/public-universe-performance/routes.json \
php -d memory_limit=512M vendor/bin/pest tests/Feature/RoutePerformanceTest.php
node --test tests/js/performance-assets.test.js tests/js/galaxy-bundle.test.js \
  tests/js/galaxy-lifecycle.test.js tests/js/galaxy-navigation.test.js tests/js/galaxy-renderer.test.js
```

The synthetic APP_KEY is for these isolated tests only. Do not use it for a
running application. No `.env` copy, database migration or server is needed.
The test kernel uses an in-memory session/cache and HTTP fakes. Production
Vite assets must already exist. A normal source-only Pest run skips this
built-asset workload; `PERFORMANCE_REQUIRED=1` makes missing assets an error.
The dedicated CI job always sets it and retains its JSON reports for 14 days.

The route report records all seven cold/fresh pairs for each workload. The
source HEAD/dirty marker, dependency-lock hashes, workload/fixture hashes,
PHP/OS/architecture and raw samples make a measurement auditable. Asset reports
record the production manifest hash and file sizes. Comparisons must use the
same fixtures, dependencies, build and machine; do not silently regenerate the
fixture to make a regression disappear.

## Meaning of the measurements

“Cold” means an empty **application cache**, not a cold machine or cold OS disk
cache. The framework/test process remains alive; compiled views and file pages
can already be warm. The first sample can include setup/compilation effects;
it remains in the report rather than being discarded. Each cold request is
followed by a fresh-cache request. The night calculation intentionally is not
cached, so each explicit POST still makes one batch API request.

`kernel_ms` is elapsed time around Laravel's testing request kernel, including
PHP routing, validation, fixture HTTP handling and server-side HTML generation.
It excludes real API computation, network, browser HTML parsing, rendering,
JavaScript startup and interaction. It is not live TTFB, LCP or user latency.
HTTP fake payload construction is included, so some cold/warm difference is
test fixture cost. Report median/range and distributions, not a p75 field claim.

PHP peak memory is reset immediately before each request and measured before
HTML analysis. Both total live PHP bytes and increase above the request baseline
are recorded. This is PHP allocator usage, not OS RSS or GPU/browser memory.
Framework state and cached fixtures remain in the process. It is inappropriate
to compare a sample from an accumulated full test suite with an isolated run.

Response sizes are uncompressed body bytes plus reproducible gzip level 9 bytes.
DOM counts are server-generated HTML element counts, not a live browser DOM
including later Livewire/Alpine mutations. Upstream counts and body bytes are
those actually observed by the fake HTTP transport, including pooled requests.
The eight-target night uses a recorded 974,868-byte response from a pinned backend,
with 2,312 target samples; source details are in the
[fixture notice](../tests/fixtures/performance/README.md).

## Stable CI gates

The new `Deterministic performance budgets` workflow builds production assets
and tests 10 representative routes: home, Saturn detail, unified search, a full
exoplanet page, 5,000-host native systems directory, initial galaxy page, optional
galaxy data, orrery, planner form and an eight-target calculated night.
It asserts maximum upstream request counts, upstream/response bytes, gzip bytes
and server HTML element counts. Content/row assertions prevent a smaller error
page from falsely passing. The checked-in ceilings include room for small
markup/dependency changes; counts preserve single-batch and warm-cache behavior.
No wall-clock or memory threshold fails CI because hosted-machine load and
allocator behavior vary. Raw timing/memory samples remain visible in artifacts.

The asset gate walks **static imports recursively**, counting shared chunks once
per entry, and reports dynamic imports separately. It includes the 262 kB
Livewire production runtime (which contains Alpine), rather than describing the
small Vite entry as the whole page's JavaScript. CSS and WOFF2 font inventory
are reported separately; font inventory is not evidence all weights are loaded
on a particular route. Per-file gzip totals are estimates, not observed HTTP
transfer or Brotli sizes.

The existing 20 kB initial galaxy Vite budget stays in place. Three.js remains in
the separately reported optional renderer, fetched after explicit activation.
Existing lifecycle tests verify no eager map-data/renderer request, duplicate
activation suppression, aborts on navigation, late-result isolation, listener
cleanup and GPU resource disposal with mocks. These assertions complement the
size budgets; they do not benchmark rendering speed.

## Acceptance limits

Lighthouse still covers only home/Saturn and its fixture backend follows the
backend default branch. Its accessibility/SEO gates and advisory lab performance
scores are useful but are not this pinned workload or a representative p75.
The new kernel workload adds deterministic catalogue/map/planner coverage without
adding a headless browser or calling live services.

A map frame-time/GPU-memory baseline requires an actual browser with the map
activated, representative 5,000-system data, hardware/renderer details and a
repeatable interaction. Physical low-power/mobile testing and field LCP ≤2.5 s,
INP ≤200 ms, CLS ≤0.1 at p75 remain separate acceptance targets, not results from
this work. No analytics or live load test has been introduced.
