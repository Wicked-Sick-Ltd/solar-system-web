# Accessible measured-system directory

`/systems` presents the measured exoplanet host records returned by the existing
cached galaxy endpoint. Its forms and pagination use ordinary GET requests and
links: JavaScript, a 3D display and an account are not required. The map, Explore,
Learn and footer link to the directory; each host links to its detail and map.

Search matches host names (case-insensitive literal text). Distance options use
25, 100 or 1,000 parsecs, alongside all returned systems. Cards also give rounded
light-year equivalents. Sort by distance or name. Equal sort values use the host
ID to keep pagination deterministic within one catalogue response. Pages contain
at most 24 records; navigation retains validated filters. The filter form starts
a new selection on page 1. Invalid inputs show a recoverable error without
querying the catalogue; out-of-range pages do not silently change the selection.

This is not a census of stars or planets. It only includes returned hosts with
usable positions, capped by the backend map limit (currently 10,000 hosts).
A truncated response or unknown truncation flag is disclosed. Missing omission
counts are unknown, not zero. Searches operate on this returned sample, so the
page never promises that a missing name does not exist elsewhere in the archive.
Distance filters compare the reported central value, not an uncertainty interval.
Catalogue refreshes can change results between page requests.

Cards report distance and the upper/lower uncertainty separately in parsecs;
small nonzero errors, zero errors and unreported sides remain distinguishable.
An approximate light-year distance is also shown. The map selection likewise
preserves small and one-sided errors in its light-year display. Follow host
records and NASA archive references for scientific use.

The shared map client now validates response shape, unique IDs, measurements,
counts and coverage fields before serving either view. An invalid response is
unavailable rather than silently dropping records or reporting an empty sample.
REST/MCP payloads and catalogue storage are unchanged by this frontend feature.

The galaxy page starts with its text links and directory link. Choose **Load 3D
map** to fetch the renderer and map data; a host selection in the URL is applied
after loading. The host picker stays disabled until then. Failed loads can be
retried, and navigating away cancels pending work and releases map resources.
Visitors who move to another control while loading keep their keyboard focus.

Validation lives in `SystemsDirectoryTest.php`, `GalaxyContractTest.php`, the
existing exoplanet and offline catalogue contract tests, and `tests/js/galaxy*.test.js`.
Browser evidence and current combined results are recorded in
[the progress log](plans/PROGRESS.md).
