# Offline catalogue contract checks

The committed `tests/fixtures/catalogue-contract.json` is produced from the
backend's small offline catalogue. It contains scientific fixture records and
its generating Git revision, not production or account data. Standard PHP tests
verify that the website emits exact requests and consumes those real response
shapes for exoplanets, hosts, the map, meteor parameter sets, asteroid cursor
boundaries and observer calculations. The observer cases cover London and both
polar regions at a fixed instant, preserving circumpolar and never-rises states,
null event times and numerical values. Unit tests separately cover unavailable
and malformed responses.

To check the selected backend checkout against the current website, use a Python
environment with that repository's development/API/MCP dependencies installed:

```sh
../solar-system-db/.venv/bin/python tools/check_catalogue_contract.py --backend ../solar-system-db
```

For the integration worktree, use `--backend ../.worktrees/public-universe-db`.
The runner builds in a temporary directory with publishing disabled, imports
that selected checkout, compares FastAPI TestClient responses with the MCP tool
**functions**, and invokes the PHP consumer test with those responses. It does
not test MCP transport or dispatcher registration. It does not need a live API,
network access, account database or retained local catalogue.

Add `--write-fixture` to refresh the committed fixture after the check passes;
review the resulting diff and provenance before committing. Retrieval timestamps
are fixed only in this temporary fixture to make the contract deterministic.
`backend_modified` includes untracked files. An immutable scientific production
snapshot is a separate concern; these fixtures do not certify one.

The backend `codex/public-universe-data-integrity` branch also corrects legacy
asteroid-filter refusal and fractional meteor windows. Integrate it before
release; older running APIs can silently ignore unsupported filters. The web
preserves graceful unavailability when the corrected API reports those limits.

## Asteroid cursor paging

`GET /api/v1/objects` has two paging modes. Without `after` it is
offset-paginated in the backend's orbital order and `next_after` is null.
With `after` (an empty string for the first page) it is keyset-paginated:
`api/main.py` declares `after` as "keyset pagination: last id of the previous
page" and `offset` as "ignored when `after` is given", `find_objects` applies
`o.id > :after ORDER BY o.id`, and the response's `next_after` is the last
returned id while a full page came back. Backend `main` and the deployed API
(`https://api.sol.wickedsick.com/api/v1/objects?type=asteroid&after=`) both
behave this way; the committed `objects`/`next_objects` fixtures are its
recorded page 1 and page 2.

The website's "full catalogue by ID" mode (`/asteroids?order=id`) therefore
sends `after`, requires `next_after` in the envelope, and additionally checks
that every returned id is strictly after the cursor in ascending byte order.
An older API that ignores `after` fails those checks, and the page reports
unavailability instead of repeating page 1. `tests/Feature/CatalogueContractTest.php`
follows the rendered `rel="next"` link onto page 2 against the recorded
responses; `tests/Feature/AsteroidFiltersTest.php` covers the fail-closed path.

## Identity continuity

Exoplanet ingestion currently hashes exact names and replaces records on refresh.
No trustworthy planet-level alias relation is supplied by the selected source
columns. Automatic reconciliation is deferred. Gaia host identity, suffixes and
similar orbits are not enough to merge planets. A future persistent alias registry
needs explicit authoritative equivalence citations, collision/cycle checks,
kind checks, preservation across fresh builds and historical withdrawal evidence.
See backend `docs/EXOPLANETS.md` for the inspected limitation. Missing rows must
not be automatically labeled withdrawn or redirected to a guessed replacement.
