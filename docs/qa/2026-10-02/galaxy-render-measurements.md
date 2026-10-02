# Actual renderer timing and resource lifecycle

Three local Chrome 154 runs on 2 October 2026 used the disposable
[measurement harness](../../../tools/performance/render-harness/README.md),
build `7ca6c56`, against the unchanged production renderer from `44b66f4`.
The later `a927d7d` only moved its tests into the standard CI directory.
Native browser controls performed every interaction; no scripted camera loop,
injected timing code or production telemetry was used.

The Mac M3 Max used device pixel ratio 2. Each settled canvas was 1673 × 420
CSS pixels / 3346 × 840 drawing-buffer pixels. The deterministic fixture contains
5,000 synthetic hosts, not scientific catalogue records. The report retains
source, lockfile, instrumentation and fixture hashes. Built artifacts:

- `index.html`: `f7ed9c81855f356e287fb63f5199445001d1b199e049e589c0f10878a41ef1a5`
- `index-p73PH0yI.js`: `c68b560562a0e30ef45c783666406753eb7afa34a0912ee55c9577b0a7951226`

## Workload and observations

Each reload mounted the renderer, marked the interaction phase, selected All
radius, selected Synthetic host 100, selected Galaxy view, reset the camera,
pressed `+` with the canvas focused, then disposed after GPU queries completed.
Each run recorded exactly seven render calls. Initial mount produced two draws:
the default 600 × 300 buffer, followed by its resized buffer. These contain 241
nearby hosts plus the Sun (242 points), not all 5,000 hosts.

Values below are CPU submission / GPU query milliseconds, rounded to three
decimals. These are individual calls, not percentiles or frame rates.

| Draw | Run 1 | Run 2 | Run 3 |
| --- | --- | --- | --- |
| Initial default buffer, 242 points | 29.700 / 0.043 | 4.900 / 0.039 | 2.500 / 0.042 |
| Initial resized buffer, 242 points | 1.500 / 0.593 | 0.300 / 0.278 | 0.000 / 0.277 |
| All radius, 5,001 points | 6.400 / 3.936 | 10.200 / 3.854 | 3.200 / 3.322 |
| Select host, 5,001 points | 0.300 / 3.132 | 0.600 / 0.353 | 0.400 / 0.368 |
| Galaxy, 5,002 points and 129 lines | 3.800 / 0.232 | 9.900 / 0.420 | 4.800 / 0.413 |
| Reset, same geometry | 0.500 / 0.201 | 3.000 / 0.353 | 0.500 / 0.418 |
| Keyboard zoom, same geometry | 23.200 / 3.718 | 0.500 / 2.437 | 0.300 / 3.376 |

All 21 GPU queries returned available, non-disjoint measurements; pending queries
were zero before disposal. Each lifecycle snapshot changed from four geometries,
zero textures and two shader programs to zero for all three counters. The
renderer requests context loss after its normal disposal path.

Raw reports: [run 1](galaxy-render-run-1.json), [run 2](galaxy-render-run-2.json),
[run 3](galaxy-render-run-3.json).

## Interpretation limits

CPU measurement surrounds only the synchronous `renderer.render` call, including
submission or possible driver blocking. GPU measurement is asynchronous elapsed
query time for commands issued in that interval. Neither is whole-page startup,
frame presentation or input latency. A reported CPU zero is timer resolution,
not proof of free work. The first draw can include cold compilation effects; caches
and other host activity were not controlled between reloads.

Three resource counters are counts, not browser heap or GPU bytes, and zero does
not prove immediate physical driver reclamation. The observed variation prevents
a general performance or causal speedup claim. These results extend the earlier
[callback-cadence evidence](combined-browser-evidence.md); they do not substitute
for lower-power hardware, physical-device or field Core Web Vitals acceptance.
Nearby and Galaxy stages also have different camera scale, rasterized coverage
and shader programs; their GPU timings are different workloads, not evidence
that one code path is intrinsically faster.
