# Observing starter catalogue browser

`/observing-targets` and `/observing-targets/{source:id}` browse the bounded backend
starter sample. They use ordinary GET forms and links, require no account or
JavaScript, and are linked from `/observe`. They do not submit coordinates or
select observing targets in the night planner.

The dedicated `StarterCatalogueClient` validates envelopes, requested page identity,
record identity, family membership, finite coordinate/measurement values, raw source
strings and provenance before returning DTOs or caching them for one hour. Cache
keys include the configured API base and filters; private user data is absent.
Failures are not cached. List endpoint absence, old-schema unavailable, malformed
responses and connection/HTTP errors show a 503 error panel. Detail 404 checks that
the catalogue itself is available before showing a genuine missing-record page.
Raw invalid filters receive 422 before catalogue requests. Out-of-range result pages
provide explicit first-page recovery, retaining the other filters.

Catalogue coordinates are labelled with the source's equinox/epoch limitations.
No proper motion, current position, companion position angle or separation date
is invented. Double-star separations are historical values with unknown dates;
records are not a census of distinct physical binaries. Original source flags,
missing values, per-field source codes and snapshot hashes remain visible. The
UI explains that geometric extent does not predict visual detectability.

OpenNGC-derived data remains CC BY-SA 4.0, independently of the website's MIT code.
The source panels retain attribution, licence links and change notices. The test
fixture `tests/fixtures/starter-catalogue.json` contains three exact selected rows
and source metadata from backend commit 524e4bf; its OpenNGC portion is copyright
2023 Mattia Verga, CC BY-SA 4.0 (https://creativecommons.org/licenses/by-sa/4.0/),
with subset selection/unit conversion by Public Universe. BSC5P is credited to
Hoffleit/Warren and NASA/GSFC HEASARC; source terms are linked in that fixture.
No new catalogue export is introduced here.

Validation uses that offline backend fixture and HTTP fakes, including malformed
schemas, missing provenance, nonfinite/wrong scalar types, invalid raw URLs,
pagination recovery, literal query preservation, escaped source strings and
failure recovery. Native GET routes add no JavaScript bundle. Browser keyboard
and narrow-screen checks remain an integration acceptance step.
