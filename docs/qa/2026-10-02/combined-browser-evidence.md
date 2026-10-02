# Combined browser evidence, 2 October 2026

Chrome 154 on this Mac, local web `8a06a4a` followed by the documentation audit
`ad4c7fb`, API `17583c9`, isolated fixture catalogue and pinned JPL DE440s. All
locations and equipment are synthetic QA examples. No production changes.

## Catalogue and night planner

The native starter search for `NGC0224` returned one record. Its detail exposed
the stable OpenNGC identifier, ICRS/J2000 interpretation, static-direction caveat,
V magnitude, angular axes, pinned source and CC-BY-SA attribution. The preparation
link selected only that target: date and coordinates remained blank, timezone
UTC, and no calculation ran. Explicitly copying the saved QA London site copied
rounded coordinates, Europe/London, altitude 20° and all four horizon points.

Submitting 2 October 2026 returned the pinned JPL calculation, darkness and the
NGC0224 window 19:47:54 +01:00 through 05:52:14 +01:00. The result exposed the
terrain, Moon reference instant, numeric-table alternative, source assumptions,
private export controls and separate optional weather request. This is journey
acceptance, not an independent validation of every astronomical prediction.

[Target detail screenshot](starter-target-detail.jpg).

About showed the fixture's logical catalogue ID
`sha256:585cbe502dde9ace3542221c7a73179d3d668588d20ea2147c7230bf2f828c20`
and distinct build ID. Missing download-manifest metadata was stated explicitly.
[Identity screenshot](catalogue-identity-desktop.jpg) precedes the later audit
copy update; it is evidence of populated identity fields, not the final wording.
Chrome blocked the exoplanet JSON attachment with `ERR_BLOCKED_BY_CLIENT`.
No alternative download mechanism was used to bypass that browser restriction.

## Actual map profiling

An isolated loopback harness loaded the production galaxy entry and renderer
against 5,000 synthetic golden-angle hosts, radii 1–500.9 pc. These are generated
test positions, not astronomy. Browser DOM-visible probe results and source/build
hashes are retained in [before](galaxy-cadence-before.json) and
[after](galaxy-cadence-after.json). The production renderer was unchanged at
baseline `95bd0c5`; revised renderer `44b66f4` coalesces pending paints.

The passive probe measures requestAnimationFrame callback intervals and browser
long tasks. It does **not** count rendered frames, measure GPU completion, or
establish Core Web Vitals. Device: Apple M3 Max, DPR 2, Chrome 154, 1728×855 CSS
viewport, 2396×996 WebGL drawing buffer. The harness omits Laravel/Alpine and the
full application page. Existing browser extensions and concurrent development
work were present; this is diagnostic evidence, not a controlled benchmark.

| Sample | Before p95 / max, ms | After p95 / max, ms |
| --- | --- | --- |
| Unloaded, 10 seconds | 16.7 / 25.3 | 32.9 / 148.8 |
| Activate map, 10 seconds | 17.1 / 250.1 | 23.4 / 258.6 |
| View/radius and keyboard burst, 20 seconds | 16.7 / 108.7 | 17.7 / 58.1 |

The interaction burst had four select changes and two keyboard events in each
run (plus one pointer event before). It occupied only part of the recording.
The earlier mixed sample had a 113 ms long task; the revised mixed sample had
none. Nevertheless the substantially worse unloaded baseline prevents a causal
browser-speed claim. Activation still had a 253 ms long task after the change.
Coalescing removes redundant render calls, as independently tested in
[the renderer report](map-render-coalescing.md); it does not solve initialization.

The earlier idle sample with two visibility changes is excluded from comparison.
The later pointer sample recorded only one key and one click, so it is not a
matched comparison with the earlier drag/wheel sample. Native orbit and wheel
controls worked after that timed sample ended. No continuous-interaction FPS,
memory reduction, mobile GPU or lower-power-device result is claimed.

[Before map](galaxy-5000-before.jpg) · [After map](galaxy-5000-after.jpg).
Screenshots include extension overlays, which are not application UI.

## Retained-catalogue identity finalization

The independently run [raw report](../../performance/2026-10-02-retained-catalogue-finalization.json)
measures backend `a4ef9bb` on a 35,713,024-byte retained SQLite catalogue:
2,431 solar-system objects, 6,366 exoplanets and 4,775 hosts. It is not the full
minor-planet catalogue. A mode=ro SQLite backup supplied three fresh disposable
copies; the original checksum, size and modification time were unchanged.

Finalization took 0.710, 0.637 and 0.642 seconds and produced the same logical ID
each time. Whole-child peak RSS was 33,898,496, 31,801,344 and 33,898,496 bytes on
Python 3.12.14 / macOS arm64. Elapsed time excludes copying/imports; peak RSS
includes imports. OS caches were warm. These runs establish a retained-fixture
baseline only; full-catalogue cost and production throughput remain unmeasured.
