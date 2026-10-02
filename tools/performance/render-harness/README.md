# Disposable actual-renderer measurement

This local-only harness instruments the actual `44b66f4` galaxy renderer at build
time. It changes no application module and adds no production telemetry. The
Vite transform fails if its three exact instrumentation anchors change. Source,
lockfile, instrumentation and deterministic 5,000-row synthetic fixture hashes
are visible in the report. `expectedRendererRevision` states the intended baseline;
the recorded source hashes identify the bytes actually built and must be checked
if running this harness on another revision. These are rendering fixtures, not catalogue claims.

With the repository's pinned Node 22 and locked dependencies:

```sh
npm ci
node --test tools/performance/render-harness/metrics.test.js
node node_modules/vite/bin/vite.js build --config tools/performance/render-harness/vite.config.js
python3 -m http.server 18025 --bind 127.0.0.1 --directory tmp/map-render-harness
```

Open the local page using the authorized browser interface. Click Mount, wait
until pending GPU queries is zero, then retain the visible initial report. Mark
interaction, choose All radius, select a host, change view, reset and use keyboard
zoom. Wait for pending queries again, retain the report, then Dispose and retain
the final lifecycle counts. Reload for a new independent run. The harness does
not generate interactions or run an idle animation loop. Its bounded query poll
ends after results, disposal or five seconds. At most 16 GPU queries and 200 draw
records are retained, with at most 50 lifecycle records. Retain the emitted build
asset hashes alongside the JSON for a browser run:

```sh
shasum -a 256 tmp/map-render-harness/index.html tmp/map-render-harness/assets/*
```

## What the measurements establish

- `cpuCallMs`: `performance.now()` around the synchronous `renderer.render`
  call, including JavaScript work and WebGL submission/possible driver blocking.
  Query setup and DOM report generation are outside this timing. It is not total
  initialization, whole-frame CPU cost, GPU duration or frame presentation.
- `gpuMs`: asynchronous WebGL2 `EXT_disjoint_timer_query_webgl2` elapsed-query
  result only after availability with no disjoint event. Unsupported, bounded,
  context-lost, timeout and disposed-before-result cases stay null with reasons.
  No `gl.finish`, inferred zero or RAF-cadence substitute is used. Query timing
  includes the commands issued in the render interval, not presentation latency.
- `geometries`, `textures`, `programs`: Three's renderer resource counters,
  captured after draws and before/after renderer disposal. They are not bytes,
  browser heap, GPU residency or proof of physical driver reclamation. Disposal
  subsequently requests context loss through the unchanged product renderer.

Timing-query semantics follow the
[Khronos extension specification](https://registry.khronos.org/webgl/extensions/EXT_disjoint_timer_query_webgl2/).
Instrumentation, DOM reporting, browser extensions, context compilation, machine
load and driver behavior can affect results. Compare repeated like-for-like
runs; do not turn one sample into a device-performance or causal speed claim.
Physical lower-power-device, assistive-technology and field acceptance remain
separate. Generated bundles/reports stay in ignored `tmp/`, not release assets.
