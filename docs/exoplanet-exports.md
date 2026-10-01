# Exoplanet page exports

`GET /exoplanets/export/json` and `GET /exoplanets/export/csv` download the current
filtered page without an account. Both use the same validated filters as
`/exoplanets`: `q` (200 characters), `method` (100 characters), `distance`
(empty, 10, 25, 100 or 1000 parsecs), and `page` (1–4000). Unknown query keys are
ignored. Invalid known inputs return JSON with HTTP 422; unavailable or malformed
catalogue data returns JSON with HTTP 503 and no attachment header. Requests are
limited to 30 per minute. Responses are private and not stored by shared caches.

Each export requests one API page, displaying at most 24 planets. The API client
requests an extra row only to determine `has_more`; that row is excluded from the
export. Downloads follow the filters and page at request time. They do not pin the
browser's earlier results or traverse the entire catalogue.

## JSON

The `public-universe.exoplanets.page.v1` schema has two top-level fields:

- `metadata`: generation time in UTC, NASA Exoplanet Archive/PSCompPars source and
  documentation URL, applied API filters, pagination, units and value conventions.
- `results`: identifiers, names, host information, discovery fields, mass provenance,
  controversy flag, retrieval timestamp, archive record URL and `source_data`.

`source_data` retains all source keys provided by the API, including references
and fields not displayed by the website. An absent key remains absent; an explicit
null remains null; zero remains zero. Values are serialized without display
rounding. Numeric values retain their JSON/PHP numeric representation; this is not
an arbitrary-precision decimal interchange format.

When the backend reports `catalogue_snapshot` alongside an exoplanet page, the
export preserves that exact validated object. Its `association` is
`same-read-transaction`: the backend read the page rows and catalogue identity
metadata within one SQLite read transaction. This is a reported association, not
an independent rehash or verification of the downloaded rows.

- A known identity sets `snapshot_id` to that page's `catalogue_id` and retains
  the build identifier and `catalogue-logical-v1` hash policy.
- An associated unknown identity leaves `snapshot_id` null and preserves the
  backend reason (`not_recorded`, `not_finalized_or_changed`, `invalid_metadata`,
  `unsupported_metadata` or `schema_changed`). It remains distinct from an
  unavailable response and from an older unassociated backend.
- An older response without the member has `catalogue_snapshot: null`,
  `snapshot_association: "unassociated"` and `snapshot_id: null`. A separately
  observed `/catalogue` identity never supplies a missing page identity.
- Present but malformed metadata fails closed with HTTP 503 for both formats;
  it is never silently downgraded to legacy or a successful empty download.

For associated responses `snapshot_association` is `same-read-transaction`.
The identifier applies only to the exported page. Another page, host detail or
later request can belong to another catalogue; this endpoint does not pin a
multi-page session or promise an atomic upstream NASA model. NASA's composite
parameters can combine studies. A mismatch with separately probed global metadata
does not relabel a correctly associated page.

Cached rows retain their own associated identity, including while a stale copy is
served during background refresh. `generated_at` records export serialization
time, not the time backend rows were read; the metadata says this explicitly.
Each record's source `retrieved_at` remains separate. An export may use a cached
page and must not be described as a fresh live catalogue query.

## CSV

CSV uses UTF-8, a header row, comma separators, double-quoted escaping and CRLF
record endings. Every row has the same columns and can be parsed by standard CSV
readers, including records with commas, quotes or embedded newlines.

The first data row has `record_type=metadata` and the metadata JSON in
`metadata_json`; other cells are blank. Remaining rows have `record_type=planet`.
This preserves metadata even when no planets match. When importing into a table,
select the planet rows for analysis.

Planet rows contain the typed record fields, measurement/error/limit/reference
columns, and complete `source_data_json`. Blank scalar measurement cells combine
missing and null for spreadsheet convenience; consult `source_data_json` to
preserve the distinction. `metadata_json` is blank on planet rows.

Strings beginning with spreadsheet formula markers (`=`, `+`, `-`, `@`),
whitespace or control characters receive a leading apostrophe in scalar CSV
cells. Actual numeric values, including negative lower uncertainties, do not.
The JSON payload columns preserve the original strings. Float cells use JSON's
round-trippable numeric representation rather than PHP's shorter display cast.

## Measurement units and conventions

| Field | Unit |
| --- | --- |
| `pl_rade` | Earth radii |
| `pl_bmasse` | Earth masses |
| `pl_orbper` | days |
| `pl_orbsmax` | astronomical units |
| `pl_eqt`, `st_teff` | kelvin |
| `st_mass` | solar masses |
| `st_rad` | solar radii |
| `sy_dist`, record `distance_pc` | parsecs |
| `ra`, `dec` | degrees |

A field's `err1` and `err2` columns use the same units and preserve source signs.
The suffix `lim` is 1 for an upper limit, -1 for a lower limit and 0 for no limit;
missing/null flags remain unknown. `_reflink` values are original archive reference
text and may contain HTML: consumers should treat them as data, not execute them.
CSV has fixed convenience columns for these suffixes; they are blank if absent.
The full raw data is authoritative when a column is absent from the convenience
set. Mass provenance is retained so consumers can distinguish mass from minimum
mass or estimates.

The backend import contract is defined in the sibling database repository's
`scripts/ingest_exoplanets.py`. Regression tests are in
[`tests/Feature/ExoplanetExportTest.php`](../tests/Feature/ExoplanetExportTest.php).
