# Evening development acceptance — 1 October 2026

Craig authorized an autonomous development window of up to two hours. This pass
focuses on responsive navigation, accessible catalogue controls, on-demand 3D and
release publication rehearsal. No production rollout or external announcement is
part of this acceptance.

## Galaxy loading

The original galaxy entry loaded 557.56 kB of JavaScript (138.83 kB gzip).
After splitting the lightweight page controller from the opt-in renderer and
adding respectful focus continuation, the entry is 3.59 kB (1.62 kB gzip).
The optional renderer remains 557.53 kB (138.78 kB gzip); its size warning is not
suppressed. The renderer and map JSON are requested only by the load action.
This is a measured transfer-size change, not a hardware or network-speed benchmark.

A 5,000-host fixture previously produced 256,682 bytes of initial galaxy HTML;
deferring the disabled picker options reduced it to 28,248 bytes before shared
header changes. Both measurements use the same fixture and view scope. The
20 nearby links and measured-system directory remain server-rendered. Actual
catalogue row count and shared navigation affect the final page size.

Node regressions cover lazy imports/data requests, failed-load retry, cancelled
fetches, stale navigation results, Livewire/browser-cache lifecycle, resource
cleanup and focus continuation. A production-manifest check limits initial
imports to 20 kB without counting the optional renderer as initial transfer.
Real Three.js math and fake GPU resources exercise renderer disposal; they do
not certify actual GPU rendering or device touch interaction.

## Navigation and input

Shared keyboard outlines now override conflicting Tailwind focus utilities.
Mobile controls have larger targets and an explicit search submission button;
the menu supports Escape/focus return, bounded scrolling and no-JavaScript
navigation/search/account fallbacks. Account errors are associated with their
fields and use theme-specific error colours. The skip link has a focusable main
target and sticky-header clearance.

Native GET forms and pagination preserve catalogue filters and rendered initial
values. Livewire remains an enhancement. Modifier clicks use its built-in
navigation handler, retaining ordinary new-tab/window behaviour. Malformed raw
URL/Livewire state has regression coverage; literal `true`/`false` search text
is preserved. Empty later pages offer recovery instead of claiming no matches.

## Observation accuracy

The orrery accepts only real ISO calendar dates in years 1–9999, supplies native
date-navigation links, and omits malformed per-body positions with named partial
coverage. Its copy distinguishes approximate Kepler positions from schematic
orbital rings. Body links are available outside the SVG.

Close approaches validate the Earth-only response envelope and required fields;
missing tables, impossible timestamps and malformed records are unavailable,
not an empty result. The page states the UTC window and distinguishes a capped
nearest-200 subset from chronological order within that subset. Null, zero and
positive distances smaller than the normal display precision remain distinct.
Independent review exercised 36 observation regressions with 151 assertions.

## Local release rehearsal

`python3 -B tools/rehearse_release.py` exercises real loopback HTTPS, Laravel
configuration caching and the SQLite publication ledger in a disposable clone.
It rejects missing database tables, stale builds, redirects and cacheable health
responses, then verifies publication, immutable retry and code rollback without
losing release history. It never changes a global trust store or copies local
production configuration. See [release instructions](../../releases.md).

Independent review corrected an inherited HTTP-proxy path in the Python startup
probe and added version metadata to the dedicated CI workflow's path triggers.
The built-in PHP server disables opcode caching; this does not replace production
FPM reload or environment-specific backup, worker, scheduler and edge checks.

## Browser limits and remaining visual checks

The existing local `/login` page loaded through native Chrome controls and
exposed the updated shared navigation and labelled form in its accessibility
tree. Browser automation otherwise timed out; native controls subsequently
reported no available Chrome window. The application server itself continued
returning successful responses. No cause is inferred from these tool failures.

Consequently the following are **not certified by this pass**: real network
request timing, actual WebGL rendering after activation, physical touch,
320px/landscape layout, visible keyboard outlines, Escape focus return, and a
browser session with JavaScript disabled. Tests verify emitted HTML, GET flows
and lifecycle logic, not these browser observations. Do not reuse earlier
screenshots as evidence of the new layouts. Resume those checks when browser
control is available, with a loopback server and synthetic account data.
