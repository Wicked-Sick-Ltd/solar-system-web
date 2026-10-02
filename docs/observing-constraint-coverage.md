# Refined per-constraint coverage

Optional `target.constraint_coverage` metadata explains altitude and darkness
independently for the **selected UTC observing interval**. It does not classify
an object as permanently circumpolar or never rising. Manual plans and shortlist
results use the same target display and validator.

The exact optional object contains `scope: "selected_interval"`, `start_utc`,
`end_utc`, `altitude` and `darkness`. Each state is one of `always_satisfied`,
`never_satisfied`, `partial` or `unresolved`. Returned endpoints must exactly
match the validated selected interval, including explicit shorter windows and
clock-change nights. All supplied target darkness states must agree. Missing
metadata from an older backend remains supported without inventing a state;
a present null, partial object, unknown field/type/state or mismatched interval
makes the response unavailable.

A never-satisfied state describes the absence of a resolved satisfying segment,
not a claim about an isolated exact threshold contact at an endpoint.

Altitude means the larger of the requested minimum and the supplied interpolated
horizon. Darkness means the requested solar-altitude threshold. The backend
classifies refined segments and boundaries; the frontend does not infer these
states from plotted samples. A known never-satisfied constraint cannot accompany
combined target windows. Known darkness throughout the interval requires the
shared darkness interval to cover it exactly; never-satisfied darkness requires
no shared darkness interval. Partial darkness requires both a nonempty satisfying
interval and some of the selected interval outside it.

Unresolved diagnostics and the older combined target status are deliberately
independent. A sub-second segment may make coverage unresolved without changing
the older status; conversely solar/lunar separation can make the target unresolved
while altitude and darkness are known. Satisfying both diagnostics does not imply
a combined window or physical visibility. Wording includes the exact interval,
threshold definition, numerical ambiguity and this limitation.

Session JSON retains the optional object unchanged, even when coordinates and
terrain are omitted by default. The existing CSV remains a window overview and
points to companion JSON for complete constraints/provenance. There is no new
request, cache or location transfer.

## Verification

`NightConstraintCoverageTest` exercises all four display states, strict optional
validation, legacy/mixed rollout, cross-target consistency, impossible states,
independence from grazing status and rendered session JSON. Mutated enum fixtures
are labelled structural/render tests, not independent astronomy evidence.
`night-session.test.js` checks exact retention with default location redaction.
The real JPL DE440s fixtures retain both a 25-hour London clock-change night
and a selected eight-hour interval. Their source commit, kernel identity,
calculation hash, catalogue notices and decoded/file hashes are recorded in
`tests/fixtures/observing/coverage-provenance.json`; outputs were not edited.
The selected interval has actual always-satisfied darkness and M31 altitude,
while Moon/Sirius altitude is partial. These fixtures derive from
[backend PR44](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/44).
Source catalogue licences, including OpenNGC CC BY-SA, remain attached; MIT
does not replace their terms.

```sh
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= vendor/bin/pest tests/Feature/NightConstraintCoverageTest.php tests/Feature/NightPlannerTest.php tests/Feature/NightConstraintsTest.php tests/Feature/CatalogueNightSessionTest.php
node --test tests/js/night-session.test.js
```

Numerical crossing tolerance is not physical accuracy. Browser acceptance and
backend scientific event tests remain separate evidence.
