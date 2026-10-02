# Explained target shortlists

This change adds `/observe/shortlist`: a native POST form that accepts a date,
site and equipment mode without requiring catalogue identifiers. It depends on
[backend discovery PR43](https://github.com/Wicked-Sick-Ltd/solar-system-db/pull/43).
The separate manual planner remains available for explicit target selection.

The equipment modes and preference settings select an explained editorial order.
They do not estimate limiting magnitude, detection probability or visibility.
An optional V-magnitude cutoff is supplied by the user; it excludes unknown or
non-V measurements, including the current Moon/planet records. A supplied true
field is compared only with reported deep-sky extent; missing extent remains
unknown and double-star separation is never treated as a target diameter.

The backend screens at most 158 packaged catalogue records plus eight Solar
System targets at 20-minute sample points. One unsupported catalogue row is
currently excluded. Screening can miss brief windows. At most eight selected
candidates receive the existing refined calculation; selected targets without
confirmed windows are counted and omitted without unbounded backfill. Empty
results describe this bounded screening, not an unobservable sky. Candidates
show their reasons, aliases, sampled peak, catalogue values or unknowns, and
validated refined windows, charts and accessible sample tables.

## Boundary and privacy

GET performs no provider call. The saved-site control copies a browser-local
site only after an explicit action; submitting then sends coordinates rounded
to two decimals and the chosen constraints in a CSRF-protected POST. There is
no automatic location lookup, weather request, account write or persistent
application cache for the result. HTML and error responses are private/no-store
and no-referrer. Input is not flashed to the session or put into query links.
Request bodies are capped at 16 KiB before normalization, the route is throttled,
and one upstream request shares the existing 1.5 MB decoded-response cap,
40–60-second timeout and disabled redirects. No retry substitutes another result.
Operators must continue excluding private POST bodies from infrastructure logs.

The consumer binds returned geometry and options to the submitted request and
requires complete software/provider/source provenance, consistent counts, and
an ordered correspondence between candidates and nonempty refined windows.
Malformed or mismatched responses produce an unavailable panel. Source hashes,
licences and attribution remain visible, including OpenNGC's CC BY-SA terms.

The target-only link to the detailed planner carries candidate IDs. Users choose
or copy the date and site again. Equipment comparisons and exports there describe
the new calculation; they do not reproduce the shortlist screening or ranking.
No shortlist export is currently advertised.

## Offline evidence

`tests/fixtures/observing/shortlist-provenance.json` records backend commit,
normalized inputs, source notices and hashes for two deterministic gzip fixtures.
These are unmodified builtin-provider outputs for an example London night,
including an empty result from an explicit V cutoff of −30. They contain no
customer records. Scientific source values retain their original licences;
this repository's MIT licence does not replace those source terms.

```sh
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= vendor/bin/pest tests/Feature/ObservingShortlistTest.php
node --test tests/js/shortlist-navigation.test.js tests/js/night-sites.test.js
npm run build
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=512M
```

The production shortlist entry and its static saved-workspace imports measured
8,797 bytes (3,804 bytes gzip) with Node 22. Its CI ceiling is 12,000/5,000
bytes respectively; shared layout CSS/Livewire remain separate existing budgets.
These are file-size estimates, not browser transfer or rendering measurements.
A loopback JPL DE440s backend request also passed the actual PHP request parser,
transport and response validator with three candidates; no remote API was used.

The browser acceptance pass is separate from these HTTP-kernel, schema and
lifecycle tests. No production requests or device performance claim are implied.
