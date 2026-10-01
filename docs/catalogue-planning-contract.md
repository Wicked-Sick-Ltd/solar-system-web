# Catalogue planning consumer contract

These pure helpers prepare the web consumer for backend observing planner PR39.
They perform no HTTP calls, automatic calculation, persistence or activation of
saved sites. They are independent of the night form's selected-hours/horizon
implementation. A successful syntax check is not proof that an ID belongs to
the backend's currently packaged sample.

## Request and journal integration

- `NightTargets::parse($input['targets'] ?? null)` accepts a PHP list of one to
  eight distinct exact strings. Supported solar names retain lowercase names;
  catalogue syntax matches the backend's bounded `bsc5p:hr…` and `openngc:…`
  identifiers. It does not case-fold, trim, remove duplicates or copy a changing
  sample membership list. Invalid input throws a field-linked
  `ValidationException`; pinned membership remains authoritative upstream.
- `NightTargets::hints($request->query())` returns a validated list from GET
  `targets[]`, or an empty list if the key is absent. A malformed present key
  throws validation errors. The controller can preselect these targets while
  keeping date/location empty. **Do not auto-submit or copy coordinates from
  query parameters.** The hint URL contains only public target IDs.
- After validating a calculated target, `NightTargets::journalIdentity($id)`
  returns `catalogue` and `id` for the existing save component. Moon maps to
  `solar`/`moon-luna`, planets to `solar`/`planet-{name}`, and catalogue IDs remain
  exact under `starter`. There is no fabricated `planet-bsc5p:…` identifier.
- `NightTargets::canPlan($starterTarget)` permits browser planning links only
  when the existing typed row has verified supported frame metadata and the
  required angular-motion components. Old rows, incomplete BSC motion and the
  unsupported Mel022 row remain browsable without promising a calculation.

The browser link contract is a native GET link built with
`route('observe.night', ['targets' => [$target->id]])`. It offers preparation of
a night form, never an automatic calculation. Activate these links together
with the controller/form integration that consumes `NightTargets::hints`.

## Response and export integration

Validate a catalogue target's `catalogue` member using
`NightCatalogueProvenance::validate($raw, $exactTargetId)`. It returns the
unchanged validated array, preserving numeric zero, small signed angular motions,
explicit unknown values and the source's attribution and licence links.
Required data includes:

- Snapshot/upstream/evidence SHA256 identities; source, retrieval marker,
  attribution, licence, HTTPS URLs and bounded evidence context.
- Input RA/Dec and verified FK5 J2000 or ICRS interpretation, Julian reference
  epoch2000.0 and explicitly unknown individual observation epoch.
- The applied angular-only/static model, strict boolean motion flag,
  `frame_transform`, refraction conditions and `accuracy_note`. BSC metadata
  retains the stated omission of empirical FK5/Hipparcos frame spin.
- Explicit `distance_au: null`, rather than an invented physical distance.

Call `NightTargets::distance($targetId, $sample)` for **each sample**, replacing
validation that assumes every target has a physical solar-system distance. A
catalogue sample must contain an explicit null. A solar-system sample must
contain a finite positive numeric AU value in the existing supported range;
missing fields, booleans and numeric strings are rejected.

Validate the response's entire `method` through
`NightProviderProvenance::validate($rawMethod)`. The supported provider/schema
enums are checked, while reported library versions, IERS data release/hash and
JPL kernel hash are retained rather than fabricated or hardcoded to one release.
It requires an ordered UTC IERS coverage interval, measured/predicted status,
canonical effective-column identity, and for JPL the kernel name/size/source/hash,
ordered TDB coverage and jplephem version. TDB calendar labels are validated as
TDB labels, not reinterpreted as UTC timestamps. The caller still verifies that
IERS coverage contains the requested night.

All dictionary levels use explicit key sets, so unexpected backend fields fail
closed instead of leaking into an export. The existing planner error handling
can convert `SolarApiException` into its unavailable state. Render labels as
escaped text and URLs as normal HTTPS anchors. CSV exporters must apply their
own established string/formula escaping. Provenance validation does not certify
observational accuracy, recompute backend hashes or compare against a live
catalogue. A valid hash string is a reported identity, not a signature.

## Offline reference fixture

`tests/fixtures/observing/catalogue-provenance.json` contains compact actual
backend outputs from commit `c4f618ab6f0503d871e417680a9a008cd1a29dec`. Its
`providers.builtin` was calculated with the builtin provider and
`providers.jpl` with the verified local DE440s kernel. The two catalogue entries
retain full source credits and licence links. The observer is illustrative;
these are offline model results, not live observations.

Older `tests/fixtures/observing/night.json` lacks full ERFA/IERS identity. It must
not be accepted by the new provenance helper or silently assigned an identity.
For integration, refresh the **whole** night fixture from an actual current
builtin calculation with its original request. Do not attach JPL metadata to
builtin positions or claim a source hash inferred from a version/date string.
