"""Offline handout branding checks. Fixture values are not a live catalogue."""

import argparse
import contextlib
import datetime as dt
import io
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

import generate
import pdf_smoke


TEMPLATE = Path(__file__).with_name("template.html").read_text(encoding="utf-8")
WHEN = dt.datetime(2026, 10, 1, 12)
STATS = dict.fromkeys([
    "N_TOTAL", "N_PLANETS", "N_DWARF", "N_MOONS", "N_ASTEROIDS", "N_COMETS", "N_TNOS", "N_TOTAL_M"
], "8")
PANELS = ["<td>Fixture planetary dial</td>"] * 8


class HandoutBrandingTests(unittest.TestCase):
    def render(self, **options):
        return generate.build_html(TEMPLATE, STATS, PANELS, WHEN, generate.DEFAULT_API, **options)

    def test_default_brand_uses_canonical_site_and_keeps_data_origins(self):
        rendered = self.render()
        self.assertIn("<h1>Public Universe</h1>", rendered)
        self.assertNotIn("<h1>Solar</h1>", rendered)
        self.assertIn('href="https://publicuniverse.net"', rendered)
        self.assertIn('href="https://api.sol.wickedsick.com/api/v1"', rendered)
        self.assertIn('href="https://api.sol.wickedsick.com/mcp"', rendered)
        self.assertIn('href="https://download.sol.wickedsick.com/latest.json"', rendered)
        self.assertIn("This handout focuses on our solar system", rendered)
        self.assertIn("Solar System from above the Sun", rendered)
        self.assertNotIn("public domain data", rendered)
        self.assertNotIn("No horoscopes, houses, transits", rendered)
        self.assertNotIn("{{", rendered)
        self.assertEqual(rendered.count('<div class="page'), 2)

    def test_optional_moons_page_retains_historical_context(self):
        rendered = self.render(moons={f"planet-{slug}": count for slug, count in generate.MOONS_1991.items()})
        self.assertEqual(rendered.count('<div class="page'), 3)
        self.assertIn("1991", rendered)
        self.assertIn("Deuteros", rendered)
        self.assertNotIn("{{", rendered)

    def test_fetch_json_sends_the_canonical_site_in_its_user_agent(self):
        with patch.object(generate.urllib.request, "urlopen") as request:
            request.return_value.__enter__.return_value = io.StringIO('{"fixture": true}')
            self.assertEqual(generate.fetch_json("https://api.example.test/stats"), {"fixture": True})
        self.assertEqual(request.call_args.args[0].get_header("User-agent"),
                         "public-universe-handout/1.0 (+https://publicuniverse.net)")

    def test_brand_is_escaped_and_origins_are_independent(self):
        rendered = generate.build_html(
            TEMPLATE, STATS, PANELS, WHEN, "https://api.example.test/api/v1",
            site_name='<Public & Universe "test">', site_url="https://website.example.test",
            download_url="https://files.example.test/snapshots/latest.json",
        )
        self.assertIn("&lt;Public &amp; Universe &quot;test&quot;&gt;", rendered)
        self.assertNotIn('<Public & Universe "test">', rendered)
        self.assertIn('href="https://api.example.test/docs"', rendered)
        self.assertIn('href="https://api.example.test/mcp"', rendered)
        self.assertIn('href="https://website.example.test"', rendered)
        self.assertIn('href="https://files.example.test/snapshots/latest.json"', rendered)
        self.assertNotIn("sol.wickedsick.com", rendered)

    def test_rejects_non_public_or_credential_bearing_printed_urls(self):
        for url in ["javascript:alert(1)", "https://user:password@example.test", "https://example.test/?token=x", "https://[broken", "https://example.test/#section"]:
            with self.subTest(url=url), self.assertRaises(argparse.ArgumentTypeError):
                generate.public_http_url(url)

    def test_unknown_placeholders_do_not_ship_in_an_artifact(self):
        with self.assertRaises(SystemExit):
            generate.build_html(TEMPLATE + "{{UNKNOWN_BRAND}}", STATS, PANELS, WHEN, generate.DEFAULT_API)

    def test_small_catalogue_totals_and_negative_snapshot_differences_are_honest(self):
        with patch.object(generate, "fetch_json", return_value={"total_objects": 2431, "by_object_type": {"planet": 8}}):
            stats = generate.fetch_stats(generate.DEFAULT_API)
        self.assertEqual(stats["N_TOTAL"], "2,431")
        differences = generate.moons_page_values({"planet-earth": 0})
        self.assertNotIn("+-", differences["MOON_ROWS"])
        self.assertEqual(differences["FACT_SATURN"], "-18")
        self.assertEqual(differences["FACT_INNER"], "-3")
        rendered = self.render(moons={"planet-earth": 0})
        self.assertIn("A partial catalogue can omit known moons", rendered)
        self.assertNotIn("Four hundred and twenty", rendered)

    def test_planet_names_cannot_inject_markup_into_pdf_html(self):
        pos = {"longitude_deg": 0, "r": 1, "a": 1, "e": 0,
               "orbit_fraction": 0, "period_days": 365, "perihelion_longitude_deg": 0}
        rendered = generate.panel_html('<img src="https://example.test/pixel">', "earth", pos)
        self.assertIn('&lt;img', rendered)
        self.assertNotIn('<img', rendered)

    def test_pdf_qa_fixture_never_presents_retained_counts_as_live_data(self):
        fixture = json.loads(Path(__file__).with_name("fixture.json").read_text())
        rendered = pdf_smoke.fixture_html(fixture, moons=True)
        self.assertEqual(rendered.count(pdf_smoke.NOTICE), 3)
        self.assertIn("Eight worlds, model snapshot", rendered)
        self.assertIn("taken from the committed offline QA fixture", rendered)
        self.assertNotIn("read from the API at generation time", rendered)
        self.assertNotIn("whole solar system", rendered)

    def test_malformed_stats_never_become_printed_zero_counts(self):
        for payload in [{}, [], {"total_objects": 1, "by_object_type": []},
                        {"total_objects": True, "by_object_type": {}},
                        {"total_objects": 1, "by_object_type": {"moon": "492"}}]:
            with self.subTest(payload=payload), patch.object(generate, "fetch_json", return_value=payload):
                with self.assertRaisesRegex(SystemExit, "Malformed /stats"):
                    generate.fetch_stats(generate.DEFAULT_API)

    def test_malformed_later_moon_page_never_silently_truncates_counts(self):
        page = {"results": [{"id": f"moon-{i}", "parent_id": "planet-jupiter"} for i in range(500)]}
        with patch.object(generate, "fetch_json", side_effect=[page, {}]):
            with self.assertRaisesRegex(SystemExit, "partial counts"):
                generate.fetch_moon_counts(generate.DEFAULT_API)
        with patch.object(generate, "fetch_json", side_effect=[page, page]):
            with self.assertRaisesRegex(SystemExit, "repeated moon"):
                generate.fetch_moon_counts(generate.DEFAULT_API)

    def test_html_only_cli_uses_environment_with_explicit_flag_precedence(self):
        with tempfile.TemporaryDirectory() as directory:
            output = Path(directory) / "fixture.pdf"
            environment = {
                "SITE_NAME": "Environment Brand", "APP_URL": "https://website.example.test",
                "API_BASE_URL": "https://api.example.test/api/v1",
                "SOLAR_DOWNLOAD_URL": "https://files.example.test/latest.json",
            }
            args = ["generate.py", "--html-only", "--date", "2026-10-01T12:00:00Z", "--out", str(output), "--site-name", "Explicit Brand"]
            with patch.dict(os.environ, environment), patch("sys.argv", args), \
                    patch.object(generate, "fetch_stats", return_value=STATS) as stats, \
                    patch.object(generate, "fetch_planet", return_value={"name": "Fixture", "orbital": {}}), \
                    patch.object(generate, "propagate", return_value={"longitude_deg": 0, "r": 1}), \
                    patch.object(generate, "panel_html", return_value=PANELS[0]), \
                    patch.object(generate, "render_pdf") as pdf, \
                    patch.object(generate.urllib.request, "urlopen", side_effect=AssertionError("Offline test made a request")), \
                    contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
                generate.main()
            stats.assert_called_once_with("https://api.example.test/api/v1")
            pdf.assert_not_called()
            self.assertFalse(output.exists())
            rendered = output.with_suffix(".html").read_text(encoding="utf-8")
            self.assertIn("<h1>Explicit Brand</h1>", rendered)
            self.assertNotIn("Environment Brand", rendered)
            self.assertIn('href="https://files.example.test/latest.json"', rendered)
            self.assertIn('href="https://website.example.test"', rendered)


if __name__ == "__main__":
    unittest.main()
