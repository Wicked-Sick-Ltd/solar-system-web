# Performance baseline and cache refreshes

Measured locally on 1 October 2026 from website revision `71e4f49`, with the
existing isolated HTTP fixtures and real Laravel route rendering. These counts
measure upstream requests, not production latency. No live load tests were run.
Each route starts with an empty application cache; a second request uses that
route's fresh cache.

| Public route | Cold API requests | Fresh-cache requests |
| --- | ---: | ---: |
| `/` | 2 | 0 |
| `/planets` | 1 | 0 |
| `/objects/planet-saturn` | 6 | 0 |
| `/objects/moon-luna` | 4 | 0 |
| `/search?q=Saturn` | 2 | 0 |
| `/exoplanets` | 1 | 0 |
| `/systems` | 1 | 0 |
| `/galaxy` | 1 | 0 |
| `/orrery` | 11 | 0 |
| `/meteor-showers` | 1 | 0 |
| `/explore`, `/observe`, `/learn` | 0 | 0 |

The orrery's ten position requests run in an HTTP pool; the eleventh is its
health probe. Saturn's two position requests are pooled; its other calls are
sequential. Counts exclude browser assets, the optional galaxy renderer/data
request, and observer requests after a visitor explicitly supplies a location.
They do not establish timings for the full live catalogue.

## One background refresh per stale key

Previously 100 reads of one soft-stale statistics entry queued 100 identical
`RefreshSolarCache` jobs. The job now implements Laravel `ShouldBeUnique`, using
the complete existing cache key as its identity. One job keeps the lock while
queued **and while executing**. Other cache keys can refresh independently.

The web response still receives the same stale value. A successful refresh
replaces the cached value; a failed HTTP request leaves it intact and releases
the unique lock so a later read can retry. The synchronous queue still refreshes
inline and returns the stale value for that request. This change does not
modify response cache/privacy rules, cold misses, or `positionsBatch()` pooling.

Operational bounds:

- The unique lease expires after 900 seconds, allowing recovery after an
  abandoned dispatch or worker. It is not an indefinite mutex.
- The job timeout is 60 seconds. Keep the queue's `retry_after` above this
  timeout (the repository defaults to 90 seconds), and set the API HTTP timeout
  below the worker budget (the default is 8 seconds).
- Keep queue wait below 14 minutes so the remaining lease covers a full worker
  attempt. Monitor backlog and queue age: after lease expiry, another producer
  may enqueue a replacement even if an old job is still waiting. Uniqueness
  reduces duplicate work; it does not replace worker/backlog monitoring.
- Every application process and worker must use the same lock-capable cache.
  Production's shared Redis cache provides this across hosts. A file cache
  coordinates only processes that share that filesystem; an array cache cannot
  coordinate separate workers. Existing worker setup remains in
  [DEPLOYMENT.md](../DEPLOYMENT.md).
- Laravel's lock-owner token prevents a late old job from releasing a newer
  producer's lock after the old lease expires.

Regression command:

```bash
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan test --compact tests/Feature/RefreshSolarCacheTest.php
```

The tests launch four independent producer processes against temporary SQLite
queue storage and a temporary shared file cache. Their 100 stale reads enqueue
one job and issue no HTTP requests. A separate real `queue:work --once` process
uses synthetic HTTP responses to verify refresh, uniqueness during execution,
failure recovery and lock ownership after expiry. The suite also checks distinct
keys and the synchronous driver. It requires no Redis, production credentials,
external HTTP, or background worker left running.

## Existing budgets and next measurements

The production asset test caps initial galaxy JavaScript at 20 kB and verifies
that the heavyweight renderer remains a dynamic import. Run `npm run build`
before `node --test tests/js/*.test.js` to enforce that check.

Lighthouse currently audits only `/` and Saturn, once per route, using a local
fixture backend. Accessibility and SEO require 0.95; performance and best
practices warn below 0.90. It does not yet impose route request-count, full-page
transfer, or interaction budgets, and its backend clone follows the current
backend default branch. A reproducible performance gate should pin/report the
backend revision and extend representative native catalogue and galaxy routes
before treating its scores as a broad baseline.

Further candidates require their own measurements: concurrent independent
object-detail/search calls, a cheaper or cached orrery health probe, and a slim
backend sky lookup that avoids loading full object detail for every time sample.
Do not add planner time sampling by repeating today's full sky HTTP lookup for
every object and timestamp. Record cold, warm and stale behavior separately,
with fixed catalogue identity and bounded synthetic load.

## Reproducible route and asset budgets

The [built-asset and request-kernel workload](performance-budgets.md) extends
this historical baseline with deterministic catalogue/map/planner fixtures,
response/DOM sizes, timing and PHP-memory samples, and structural CI gates.
It keeps browser/GPU and field Core Web Vitals acceptance separate.
