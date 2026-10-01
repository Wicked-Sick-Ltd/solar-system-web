# Guest optical calculator

The calculator on `/observatory` uses saved equipment specifications and temporary
browser-only choices. It makes no catalogue, weather, account or external requests.
Calculations and user-entered angular sizes are not persisted as measurements.
There is no equipment control, compatibility assessment or visibility prediction.

## Equations and units

All optical lengths use millimetres. Angular results use degrees; one degree is
60 arcminutes. Raw calculations retain floating-point precision; display values
use four significant figures, with scientific notation for small nonzero results.

- Effective telescope focal length: telescope focal length multiplied by the
  selected accessory's stated factor (one Barlow or reducer, or explicitly none).
- Magnification: effective telescope focal length divided by eyepiece focal length.
- Exit pupil: aperture divided by magnification. This is the optical exit pupil,
  not the observer's pupil or a brightness guarantee.
- True field, preferred estimate: effective eyepiece field-stop diameter divided
  by effective telescope focal length, multiplied by `180 / pi`. Manufacturer
  instructions round the radians-to-degrees factor to 57.3. The published
  effective field stop is required, not a guessed barrel diameter.
- True field, fallback approximation: eyepiece apparent field divided by
  magnification. Only used when the field stop is unknown, not when a supplied
  field stop is invalid. Distortion means this can differ from the actual field.
- Binocular magnification uses the stated value; exit pupil is aperture divided
  by it. Binocular true field stays unknown unless the visitor supplies a stated
  true field in degrees. Changing instruments or their saved specifications clears that temporary value.

The field-stop equation is a paraxial estimate. Neither model handles vignetting,
mechanical compatibility, spacing-dependent accessory factors, distortion,
central obstruction, limiting magnitude, seeing or the observer's eye pupil.
A geometric field exceeding 180 degrees is withheld as outside the model's
physical domain. Missing/invalid required values produce unknown outputs,
never fabricated zeros. Independent quantities remain available where possible.

## Angular comparison

The visitor enters an angular diameter in arcminutes, not a physical diameter.
No default Moon/planet size or cached catalogue size is supplied. The diagram
compares `diameterArcmin / 60 / trueFovDeg`; both concentric circles share a scale.
If a target is larger than the field, the field circle becomes smaller. A circle
below half a viewBox unit in radius is omitted and explained in the text, rather
than enlarged to a misleading minimum size. Text provides the diameter, percentage
of field diameter, and geometric fit independently of the diagram.

An entered target diameter remains when equipment changes, so the same angular
size can be compared across setups. It is always labelled as user-entered. The
binocular true-field input is cleared when the selected instrument changes, including replacement records reusing an ID.
Equipment deletion/import/reload refreshes selectors and removes stale IDs.

## Primary references checked 2026-10-01

- [Celestron: telescope magnification and Barlow lenses](https://www.celestron.com/blogs/knowledgebase/what-is-magnification-power-as-it-pertains-to-telescopes).
  The independent example is a 1,000 mm telescope / 20 mm eyepiece = 50×;
  a stated 2× Barlow doubles magnification.
- [Tele Vue DeLite instructions and Al Nagler's “Choosing Your Eyepieces”](https://astronomics.com/cdn/shop/files/DeLite_9_pkg.pdf?v=17679851986320741285),
  manufacturer-authored PDF hosted by retailer Astronomics, pages 2 and 5.
  Provides the field-stop equation and exit-pupil equation. The initially
  supplied TV-85 manual mirror returned HTTP 403, so this accessible Tele Vue
  authored source was checked instead.
- [Celestron SkyPortal telescope manual](https://celestron-site-support-files.s3.amazonaws.com/support_files/SkyPortal%20130-90-70%205%20language%20manual.pdf),
  “Telescope Basics”: apparent-field/magnification approximation.
- [Celestron: binocular image brightness](https://www.celestron.com/blogs/knowledgebase/what-determines-the-brightness-of-the-image-in-my-binoculars):
  exit pupil from objective diameter and magnification. The calculator does not
  turn this into a brightness or observing-success score.

## Validation and remaining acceptance

Pure Node tests cover fixed known examples, accessory scaling, field-stop
precedence, missing and invalid values, dimensional conversion, tiny positive
values, and angular ratios above/below one. UI tests cover selection refresh,
non-persistence, unknown binocular fields and lifecycle cleanup. PHP checks
cover guest rendering and disabled controls before JavaScript initialization.
Real browser/keyboard/touch acceptance remains part of the draft feature review;
mocked DOM tests do not establish that acceptance.
