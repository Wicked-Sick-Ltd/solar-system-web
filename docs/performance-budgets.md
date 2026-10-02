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

The PHPUnit configuration pins `APP_DEBUG=false` before application bootstrap,
including when the developer shell or local `.env` enables debug mode. Livewire
selects its production runtime route during provider boot; changing configuration
after boot cannot make a debug runtime represent a production asset workload.
This affects test processes only. The exact-one-production-runtime assertion
remains required for every HTML sample. The dedicated CI workload deliberately
starts with inherited `APP_DEBUG=true` to exercise this override.

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
cache. Request-scoped services and Livewire/view render state reset before each request,
so every HTML sample includes its runtime and does not accumulate SEO scripts.
The framework/test process remains alive; compiled views and file pages
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
Every repeated sample must also have the same element count and script sources;
this detects request-state accumulation in the harness. No wall-clock or memory threshold fails CI because hosted-machine load and
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

## Recorded baseline — 2026-10-02

The [raw route samples](performance/2026-10-02-routes.json) and
[production asset inventory](performance/2026-10-02-assets.json) were captured at
clean source `b8e9a1d59e6f74db0c6a87f30c411cf3f2beb04e`, incorporating feature
checkpoint `f54398d`. Machine: Apple M3 Max, 64 GiB RAM, macOS arm64, PHP 8.4.23,
Node 22.23.2. Each route has seven cold/fresh pairs. Sizes, DOM counts and memory
below are sample maxima; time values are medians, with every raw sample retained.

| Route workload | API cold/fresh | Response bytes | DOM elements | Kernel median ms cold/fresh | Peak PHP MiB |
| --- | ---: | ---: | ---: | ---: | ---: |
| home | 3/0 | 37,402 | 255 | 6.24/5.21 | 40.9 |
| saturn | 7/0 | 71,565 | 413 | 15.33/13.56 | 44.4 |
| search | 3/0 | 55,544 | 313 | 9.20/7.97 | 43.5 |
| exoplanets_page | 2/0 | 41,886 | 360 | 10.72/9.77 | 44.2 |
| systems_5000 | 2/0 | 57,908 | 569 | 47.26/19.19 | 64.2 |
| galaxy_5000 | 2/0 | 31,986 | 258 | 38.80/11.03 | 64.3 |
| galaxy_data_5000 | 2/0 | 3,477,521 | 0 | 44.69/16.56 | 64.4 |
| orrery | 12/0 | 35,788 | 250 | 7.45/5.61 | 44.5 |
| night_form | 0/0 | 32,130 | 245 | 3.97/4.20 | 44.6 |
| night_eight | 1/1 | 865,879 | 23,892 | 87.00/84.89 | 52.2 |

Cold catalogue routes include one `/catalogue` identity probe. Warm requests
reuse the observed identity and scoped data; the private night calculation still
makes one explicit astronomy request and does not automatically fetch weather.
The synthetic 5,000-host response is 3.48 MB uncompressed: its JSON endpoint is
measured separately from the small initial galaxy HTML. These are workload
limits, not a claim about the current size of the live catalogue.

The eight-target night emits 865,879 HTML bytes and 23,892 elements, including
all 2,312 scientific samples in accessible tables. This is a concrete candidate
for browser/low-power-device profiling: collapsed tables still occupy the DOM.
The 87 ms kernel median excludes the recorded backend calculation and must not
be presented as the time a user waits for a new calculated night.

| Built asset closure | Raw bytes | Gzip level 9 bytes |
| --- | ---: | ---: |
| Shared Livewire runtime, including Alpine | 261,782 | 86,131 |
| Shared CSS | 34,289 | 7,227 |
| Initial galaxy application JavaScript | 3,666 | 1,647 |
| Optional activated galaxy renderer | 557,539 | 137,340 |
| Night planner application JavaScript, including equipment guidance and static imports | 25,146 | 9,672 |

For a first-load galaxy page, add the shared runtime to the application entry;
the 3.7 kB figure alone is not its complete JavaScript cost. Renderer download
remains deferred until activation. Night's 32 kB raw / 12 kB gzip ceiling allows
roughly one quarter of headroom over the reviewed equipment-enabled closure;
it replaces the pre-guidance 20 kB / 7.5 kB trial ceiling. Other application and
response ceilings similarly leave bounded markup/dependency room. The full
night ceiling is 1.1 MB HTML / 130 kB gzip / 30,000 elements. These are explicit
regression tripwires, not universal performance quality thresholds. Changing
fixtures, request counts or ceilings requires a new measured explanation.
