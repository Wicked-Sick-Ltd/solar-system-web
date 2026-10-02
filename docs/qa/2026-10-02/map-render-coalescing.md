# On-demand map render coalescing

Source baseline: `8a06a4a`. Node 22.23.2, the actual `galaxy-renderer.js` module
executed in the existing VM harness with real Three.js vectors/scenes and a fake
WebGL renderer, controls and animation-frame scheduler. One synthetic host at
100 pc exercises selection outside the initial 25 pc filter. No live catalogue,
GPU timing or telemetry was used.

| Deterministic operation | Baseline render calls | Coalesced render calls |
| --- | ---: | ---: |
| Mount, resize notification, then flush the frame | 3 | 1 |
| Select the host outside the initial radius, then flush | 5 | 1 |
| Twenty controls change events before one frame | 20 | 1 |
| Reset camera, then flush | 2 | 1 |

These are calls to the renderer, not latency measurements. The changes do not
remove initial WebGL context creation, buffer allocation, scene rebuilding or
GPU work. They do not establish improved browser frame time or lower-power
hardware performance. Parent browser before/after profiling remains separate.

Every redraw trigger requests the same single pending animation frame. Its
callback reads the current camera, scene and selection, updates labels, and does
not schedule an idle loop. Disposal cancels the pending frame (including handle
zero), and late callbacks cannot draw or schedule more work. Picking updates the
camera world matrix immediately: a click between a camera change and its paint
must use the new camera rather than the last rendered transform.

`tests/js/galaxy-renderer.test.js` exercises the real module with that harness:
initial resize coalescing, a mixed controls/rebuild/reset/selection burst, latest
camera/marker state, no idle work, cancellation and pre-paint picking. Existing
text-only WebGL fallback, focus permission and resource disposal cases remain.

Validation on the changed source: all 238 JavaScript tests and the Node 22
production build passed; `git diff --check` passed. The existing optional Three.js
chunk remains about 558 kB minified; this change does not claim a bundle-size
reduction or resolve that existing build warning.

```sh
node --test tests/js/galaxy-renderer.test.js
node --test tests/js/*.test.js
npm run build
```
