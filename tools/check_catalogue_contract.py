#!/usr/bin/env python3
"""Exercise real offline REST/MCP responses through the website's PHP consumer.

Run with the backend virtualenv Python and --backend pointing at its checkout.
No servers or network are used; builds always target a new temporary database.
--write-fixture refreshes the small committed consumer fixture after review.
"""
from __future__ import annotations

import argparse
import importlib.util
import json
import os
from pathlib import Path
import sqlite3
import subprocess
import sys
import tempfile

WEB = Path(__file__).resolve().parents[1]


def require_equal(actual, expected, label):
    if actual != expected:
        raise RuntimeError(f"REST/MCP contract mismatch: {label}")


def collect(backend: Path, database: Path) -> dict:
    os.environ["SOLAR_DB_PATH"] = str(database)
    sys.path.insert(0, str(backend))
    from fastapi.testclient import TestClient
    from api.main import app, limiter

    limiter.enabled = False
    spec = importlib.util.spec_from_file_location("contract_mcp", backend / "mcp-server/server.py")
    server = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(server)

    with TestClient(app) as client:
        def get(path, **params):
            response = client.get("/api/v1" + path, params=params)
            response.raise_for_status()
            return response.json()

        planets = get("/exoplanets", q="Proxima", limit=25)
        require_equal(planets, server.list_exoplanets(q="Proxima", limit=25), "exoplanet list")
        planet = get("/exoplanets/Proxima Cen b")
        require_equal(planet, server.get_exoplanet("Proxima Cen b"), "exoplanet detail")
        host = get("/exoplanet-hosts/" + planet["host_id"])
        require_equal(host, server.get_exoplanet_host(planet["host_id"]), "host detail")
        galaxy = get("/galaxy")
        require_equal(galaxy, server.galaxy_map(), "galaxy map")
        showers = get("/meteor-showers", limit=1000, established_only=True)
        require_equal(showers["items"], server.list_meteor_showers(limit=1000, established_only=True), "shower list")
        shower = get("/meteor-showers/GEM")
        require_equal(shower, server.get_meteor_shower("GEM"), "shower detail")
        objects = get("/objects", type="asteroid", limit=25, after="")
        require_equal(objects["results"], server.find_objects(object_type="asteroid", limit=25, after=""), "asteroid cursor")
        if len(objects["results"]) != 25:
            raise RuntimeError("Offline fixture must exercise overfetch pagination")
        # The consumer displays 24 rows; its next cursor must precede overfetch.
        cursor = objects["results"][23]["id"]
        next_objects = get("/objects", type="asteroid", limit=25, after=cursor)
        require_equal(next_objects["results"], server.find_objects(object_type="asteroid", limit=25, after=cursor), "next asteroid cursor")
        require_equal(next_objects["results"][0]["id"], objects["results"][24]["id"], "overfetch boundary")

    revision = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=backend, text=True).strip()
    dirty = bool(subprocess.check_output(["git", "status", "--porcelain", "--untracked-files=normal"], cwd=backend, text=True).strip())
    return {"provenance": {"backend_revision": revision, "backend_modified": dirty,
                           "source": "offline repository fixtures; not a production snapshot",
                           "retrieval_time": "fixed for deterministic contract checks"},
            "exoplanets": planets, "exoplanet": planet, "host": host, "galaxy": galaxy,
            "meteor_showers": showers, "meteor_shower": shower,
            "objects": objects, "next_objects": next_objects}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--backend", required=True, type=Path)
    parser.add_argument("--php", default="php")
    parser.add_argument("--write-fixture", action="store_true")
    args = parser.parse_args()
    backend = args.backend.resolve()
    if not (backend / "scripts/build_full.py").is_file():
        parser.error("--backend must be a solar-system-db checkout")

    with tempfile.TemporaryDirectory(prefix="public-universe-contract-") as folder:
        database = Path(folder) / "catalogue.sqlite"
        env = {**os.environ, "SSDB_BUILD_PATH": str(database), "SSDB_NO_PUBLISH": "1",
               "PYTHONPATH": str(backend) + os.pathsep + os.environ.get("PYTHONPATH", "")}
        subprocess.run([sys.executable, str(backend / "scripts/build_full.py"), "--fresh", "--offline", "--no-vacuum"],
                       cwd=backend, env=env, check=True, stdout=subprocess.DEVNULL)
        # Stabilize only our temporary fixture, never the developer's catalogue.
        with sqlite3.connect(database) as conn:
            conn.execute("UPDATE exoplanets SET retrieved_at = ?", ("2000-01-01T00:00:00+00:00",))
            conn.execute("UPDATE exoplanet_hosts SET retrieved_at = ?", ("2000-01-01T00:00:00+00:00",))
        payload = json.dumps(collect(backend, database), indent=2, ensure_ascii=False) + "\n"
        fixture = Path(folder) / "contract.json"
        fixture.write_text(payload)
        subprocess.run([args.php, "artisan", "test", "tests/Feature/CatalogueContractTest.php"], cwd=WEB,
                       env={**os.environ, "CATALOGUE_CONTRACT_PATH": str(fixture)}, check=True)
        if args.write_fixture:
            destination = WEB / "tests/fixtures/catalogue-contract.json"
            destination.parent.mkdir(parents=True, exist_ok=True)
            destination.write_text(payload)
            print(f"Wrote {destination.relative_to(WEB)}; review before committing.")
    print("Offline REST/MCP/website contract passed; temporary database removed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
