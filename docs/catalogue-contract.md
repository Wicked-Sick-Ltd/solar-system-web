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

## Identity continuity

Exoplanet ingestion currently hashes exact names and replaces records on refresh.
No trustworthy planet-level alias relation is supplied by the selected source
columns. Automatic reconciliation is deferred. Gaia host identity, suffixes and
similar orbits are not enough to merge planets. A future persistent alias registry
needs explicit authoritative equivalence citations, collision/cycle checks,
kind checks, preservation across fresh builds and historical withdrawal evidence.
See backend `docs/EXOPLANETS.md` for the inspected limitation. Missing rows must
not be automatically labeled withdrawn or redirected to a guessed replacement.
