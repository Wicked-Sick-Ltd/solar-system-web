# Local equipment comparison for a night plan

The equipment panel compares saved eyepieces on one explicitly selected telescope,
with at most one saved Barlow or reducer. It can also show a selected binocular's
magnification and exit pupil. A visitor can enter a temporary telescope and one
temporary eyepiece, even if browser storage is unavailable. The panel reads only
`public_universe_observing_v1`; it never saves a workspace, session or observation,
reads observer coordinates separately, makes requests, or controls equipment.

Ordering is explicit: lowest magnification, widest known estimated field, or
highest magnification. Stable exact IDs break ties. Unknown fields sort last in
field order. Each result includes the calculation method and explains angular
containment when both field and extent are available. There is no overall
suitability/visibility score, purchasing advice, compatibility assertion or implied
optimal power. The shared `optics.js` equations and limitations remain authoritative;
see [observing-optics.md](observing-optics.md).

## Source appearance and absence

Optional `target.catalogue.appearance` carries the same pinned row's families,
magnitude, band, quality flag/code and major/minor axes. It uses the outer source
attribution, licence links and snapshot hash. Older responses lacking appearance
remain usable, with no inferred measurements. Negative or zero magnitudes are real
numbers; nulls stay unknown. The UI copies only documented fields from the supplied
plan into its local comparison state.

Only a positive deep-sky major axis at most 180 degrees is used as an angular
extent. A zero remains visibly a reported zero, but is not treated as a measured
zero-diameter point source. Double-star separation is never a diameter. Stellar
or planetary angular sizes are not guessed. Integrated magnitudes of extended
objects and varying bands do not establish point-source visibility or surface
brightness. No field-fit result implies that a target is visible.

An explicit manual-size mode can compare a visitor-supplied angular diameter in
arcminutes, with an optional plain-text source note. This is labelled user-entered,
not substituted into source metadata. Input and source notes stay unsaved. Stated
binocular true field is also temporary and clears when the selected record or its
specifications change, including replacement imports that reuse an ID.

## Mount contract

Include `observing.equipment-suggestions` outside the night-plan request form.
It has no named request fields, submit controls, or Livewire bindings. Controls
start disabled and no-JavaScript help points to the existing numeric night results.
The page's existing lifecycle owner calls:

```js
const comparison = mountEquipmentSuggestions(root, { targets: validatedPlan.targets });
// Before leaving/replacing the page, including pagehide:
comparison.dispose();
```

`targets` is at most eight validated plan targets with `id`, `name` and optional
`catalogue`; samples and other target fields are not consumed. Storage can be
injected for tests. The mount returns `refresh()` to reread saved equipment and
`dispose()` to remove all three delegated listeners and disable the controls.
The existing page owner remounts on persisted `pageshow`. Reloading equipment
rebuilds selectors without duplicate options or dangling IDs. Damaged/denied
storage degrades to temporary inputs without displaying internal exception text.

## Validation

Offline pure-function tests cover the manufacturer 1000/20 = 50× example,
accessory scaling, field-stop precedence, unknown values, ties, true-field
containment, zero/negative magnitudes, source-link guards and optional old metadata.
Mock DOM tests cover source context, literal hostile labels, no writes, temporary
setups with storage denied, invalid-input recovery, same-ID replacement and
listener cleanup. Blade tests verify labels, initial disabled state and absence
of submission fields. Mock DOM tests do not replace integrated browser acceptance.

Primary guidance checked 2026-10-02:
[Celestron's magnification explanation](https://www.celestron.com/blogs/knowledgebase/what-is-magnification-power-as-it-pertains-to-telescopes)
provides the optical ratio and recommends starting at lower power. No maximum-power
rule is converted into an automatic recommendation. Catalogue context comes from
the existing pinned [OpenNGC source](https://github.com/mattiaverga/OpenNGC), whose
attribution and licence are retained with every supplied appearance record.
