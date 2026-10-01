# Catalogue versions, cache transitions and downloads

The frontend accepts the backend D1 contract at `/api/v1/catalogue`. The typed
`CatalogueIdentity` reads schema version 1, status, logical catalogue ID, build
ID, hash policy and UTC-aware build finish. Unsupported, malformed, missing or
failed responses remain unknown/unavailable. The frontend does not hash a
catalogue or infer an ID from dates, counts or filenames. Older backends remain
usable without requiring a newly built database.

`CatalogueContext` shares a 60-second observation lease through the configured
Laravel cache. One producer uses a five-second cache lock and a two-second
HTTP timeout to refresh it; other workers neither reuse an expired known scope
nor wait on that network request. They may fetch uncached scientific data.
A producer checks ownership and its lease deadline before publishing; an expired
producer yields to a newer observation. A failure is also remembered for 60 seconds, avoiding a slow probe per object.
Metadata requests refuse redirects, request uncompressed JSON, reject encoded
responses, check Content-Length and transfer progress against 256 KiB, and
check the final body length before decoding. There is no retry loop.

Cache keys include the configured backend and a random observation-generation
token. A successful check of the same known logical/build ID retains the token.
A change of ID or known/unknown/unavailable status creates a new generation;
returning to an earlier build creates another new token, rather than reviving
its old cache. Existing unversioned cache entries are not reused. Keys are
bounded hashes and contain no raw query text or observer coordinates.

Solar API and starter-catalogue reads use this scope. Known generations retain
the existing cache durations and Solar's stale-while-revalidate behavior.
Unknown or unavailable identities use at most 60 seconds of fresh data and
never fall back to long-stale scientific values. Batched positions follow the
same rule; a failed body remains unavailable if its identity scope is unknown.
Queued refresh jobs compare the current key before fetching and again before
writing. An old generation or another configured backend cannot receive that
write. This also requires restarting workers during normal code deployment so
already running old application code does not continue its previous behavior.

Observation and download cache payloads are plain arrays. Starter DTOs are
encoded to scalar data and reconstructed with their validators on cache hits.
`cache.serializable_classes` remains false; file/database cache use does not
require PHP object unserialization. Existing independent-process queue tests
and new file-cache round trips exercise the production cache behavior.

## Honest snapshot limits

The lease means a backend change can remain unobserved for roughly one minute.
Anonymous catalogue-bearing HTML (home, planets, dwarf planets and About) adds at
most another 60-second shared-cache window, without stale-while-revalidate.
Static API guidance retains its longer edge policy. This trades more frequent
edge-to-application requests for bounded catalogue freshness. Normal deployment
must purge old edge entries or wait out their prior 600-second fresh plus
86,400-second stale allowance: changing origin headers cannot retire copies
already held by a CDN. No CDN purge or live configuration change was performed.
Requests made concurrently with a backend replacement can span two versions.
D1 does not attach an identity to each scientific response, so observing the
identity before or after another request **does not certify that response** as
belonging to a particular immutable snapshot. The frontend therefore does not
attach the observed ID as a certified version to live filtered exports or night
plans. Atomic pinned exports require a future backend response-level identity
or snapshot-selection contract. Parent night-planner calculations are unchanged.

Known IDs do not promise scientific correctness, unchanged upstream services,
or permanent retention of historical files. A catalogue rebuild timestamp is
not a measurement's observation epoch or its original source retrieval date.
Upstream versions absent from the backend remain unknown.

## Download awareness

The About page separately reads `/download` metadata using a 60-second cache.
It displays the compressed artifact checksum, optional uncompressed SQLite
checksum and the download's own logical/build IDs. Copy distinguishes the same
reported build, the same logical data with different provenance, different data,
and unknown identity. It never labels two snapshots equal just because their
build dates match. Malformed metadata does not remove the configured manifest
link. No arbitrary manifest URL is fetched by this implementation.

The configured download link remains unchanged and can differ from the API's
manifest source. The page labels its values as API-reported and asks readers to
check the actual downloaded manifest. Save the artifact with its manifest,
source metadata and licences. Daily URLs and backend retention are unchanged;
there is no promise that a historical ID can still be downloaded.

## Validation

- `CatalogueIdentityTest`: rollovers, rollback, failures/recovery, legacy fresh
  TTL, contended probe lock, delayed/in-flight refresh, backend isolation,
  metadata validation, download comparisons and file-cache DTO round trips.
- `RefreshSolarCacheTest`: independent producers sharing the file cache and a
  disposable SQLite queue still deduplicate stale refresh jobs.
- All tests use synthetic payloads and isolated caches, without live services.

Existing endpoint tests prime a stable synthetic observation to keep their
request-count assertions focused on their endpoint. Identity tests start with
an empty cache and exercise the real probe and transition paths.
