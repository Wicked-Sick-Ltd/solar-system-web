# Browser observing workspace: schema version 2

The application keeps the historical local-storage key
`public_universe_observing_v1` so existing browser workspaces remain discoverable.
The key name is not the document schema version. Canonical documents now contain
`schemaVersion: 2`, `equipment`, `sites`, and nullable `activeSiteId`.
All records have stable UUIDs and names. Duplicate IDs, unknown fields and
unsupported versions are rejected, never silently stripped into a partial import.

## Backward compatibility

Strict version-1 files remain supported with their original 128 KiB file and
canonical-payload bounds and original field/type validation. They normalize in
memory to version 2, adding `horizonMask: null` to sites. Loading or exporting
never rewrites storage. The next successful explicit save writes canonical v2;
export downloads `public-universe-workspace-v2.json`. Raw serialized storage
snapshots still detect another tab's changes, even if two versions normalize to
the same in-memory values.

V2 files/canonical data have a 256 KiB bound. This accommodates added site fields
without rejecting a formerly valid near-limit v1 document. Both versions retain
at most 100 equipment entries and 100 sites. Each mask has at most 72 points;
the overall byte bound still applies when many sites have large masks. Imports
require preview and explicit replacement. They never activate an imported site.
Unknown/corrupt documents remain recoverable through the original-data download.
Old application versions reject v2 safely; retain a backup before downgrading.

## Cameras

A camera record has `id`, `name`, `kind: "camera"`, `sensorWidthMm`,
`sensorHeightMm`, and nullable `pixelSizeUm`. Sensor dimensions must be finite
numbers from 0.01 to 1,000 mm. A known pixel size must be finite from 0.01 to
1,000 micrometres and no larger than either sensor dimension after conversion.
Null means unknown, not zero. No control, exposure, binning, manufacturer
compatibility or instrument-resolution promises are attached to these records.
The single optional pixel dimension assumes equal pixel pitch in both axes.

## Site horizons

V2 sites retain `id`, `name`, rounded `latitude`/`longitude`, canonical IANA
`timezone` and independent `minAltitudeDeg` (0–90). `horizonMask` is either null
(unknown) or 2–72 objects with exactly `azimuthDeg` and `minAltitudeDeg`.
Azimuths are 0–360 degrees clockwise from north; 360 canonicalizes to 0 before
sorting and duplicate detection. Altitudes are −90–90 degrees, allowing a
user-measured depressed horizon. A value of 90 represents an obstruction up to
the zenith; it is never silently reduced to a lower altitude.

Values between points use linear circular interpolation, including the segment
across north. Two points are valid but coarse; an interval with few measurements
can miss obstacles. All masks are user-entered, not a terrain database or a
survey claimed by this application. The local geometry helper
`minimumAltitudeAt(site, azimuth)` takes the maximum of the independent
baseline and interpolated mask. Its `horizonKnown` flag distinguishes a missing
mask from an entered zero-degree horizon.

The night planner accepts the full 0–90° baseline. Its explicit Copy this site
into the form action copies rounded coordinates, timezone, baseline and terrain;
submitting that form sends those inputs for the selected calculation. It neither
activates the site nor saves the edited form back to the profile. Choosing Use in
the workspace still transfers only rounded coordinates into the independent
`observer_location` setting; the object sky panel does not apply a site mask.

Signing in does not upload site records. An explicit account-backup transfer can
include the whole workspace, including sites; exported files can also include
private names and locations. See [private synchronization](observing-sync.md)
for consent, encryption, deletion and browser replacement limits.

## Camera angular geometry

For each sensor dimension `s` in mm and effective focal length `f` in mm:

`field = 2 * atan(s / (2*f)) * 180/pi` degrees.

This is the full angle of an ideal rectilinear projection at infinity. It uses
the arctangent expression rather than replacing it with a small-angle ratio.
The optional central pixel angular width uses the same expression with
`s = pixelSizeUm / 1000`, then converts degrees to arcseconds. It describes a
pixel centred on the optical axis; it is neither a global pixel scale at wide
angles nor resolving power. Distortion, vignetting and compatibility are outside
the model.

The rectangle/circle comparison uses tangent-plane coordinates:
`tan(width/2)`, `tan(height/2)` and `tan(enteredDiameter/2)` on one scale. This
preserves sensor aspect ratio at wide angles. It compares a centred circular
angular target against a rectangular field, not an observed sky image. Tiny
shapes are omitted with a textual explanation rather than exaggerated.

Primary source checked 2026-10-01: Gregory Hollows and Nicholas James,
[Edmund Optics: Understanding Focal Length and Field of View, equation 1](https://www.edmundoptics.com/knowledge-center/application-notes/imaging/understanding-focal-length-and-field-of-view).
The one-pixel angle and centred target projection are direct applications of
that geometry. Visual eyepiece references remain in [observing-optics.md](observing-optics.md).

Tests retain the original v1 fixture, add a v2 camera/mask fixture, and cover
near-limit migration, unknown versions, rejected duplicate seam directions,
sparse masks, malformed dimensions, exact wide-angle results, optional pixels,
mode changes and transient UI state. Browser acceptance is tracked separately.
