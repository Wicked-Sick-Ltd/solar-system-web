#!/usr/bin/env python3
"""Build the two-page A4 Solar handout as a PDF.

Page 1 is an overview of the site, the free API, the MCP server and the
nightly database download. Page 2 is a dated snapshot of where the eight
planets are, one dial each, drawn from live data. An optional page 3
(--moons-page) sets today's moon count against the sixty known in 1991.

Everything on both pages is pulled from the public REST API at run time --
the catalogue counts from /stats and the J2000 orbital elements from
/objects/planet-<name> -- so re-running this produces a current sheet.
Positions are two-body Kepler propagations of those elements, the same
method the site's orrery and the MCP server's compute_position tool use.

Astronomy, not astrology.

Usage:
    python3 tools/handout/generate.py
    python3 tools/handout/generate.py --date 2027-03-20T12:00:00Z
    python3 tools/handout/generate.py --moons-page
    python3 tools/handout/generate.py --out build/handout.pdf --keep-html

Requires: Python 3.9+ (standard library only) and wkhtmltopdf on PATH.
"""

from __future__ import annotations

import argparse
import datetime as dt
import json
import math
import shutil
import subprocess
import sys
import urllib.error
import urllib.request
from pathlib import Path

DEFAULT_API = "https://api.sol.wickedsick.com/api/v1"
J2000_JD = 2451545.0
AU_KM = 149_597_870.7
HTTP_TIMEOUT = 30
USER_AGENT = "solar-handout-generator/1.0 (+https://sol.wickedsick.com)"

PLANETS = ("mercury", "venus", "earth", "mars",
           "jupiter", "saturn", "uranus", "neptune")

SYMBOL = {"mercury": "☿", "venus": "♀", "earth": "⊕",
          "mars": "♂", "jupiter": "♃", "saturn": "♄",
          "uranus": "♅", "neptune": "♆"}

# Moons known per planet at the end of 1991, when Deuteros shipped on the
# Amiga. Historical constants -- this column cannot be derived from discovery
# dates alone, because two moons carry pre-1992 dates but were not known then:
# Jupiter's Themisto (seen 1975, lost, recovered 2000) and Uranus's Perdita
# (imaged by Voyager 2 in 1986, unnoticed until 1999, unconfirmed until 2003).
# Both are counted as modern discoveries here, which reproduces the 16 and 15
# that textbooks quoted at the time.
MOONS_1991 = {"mercury": 0, "venus": 0, "earth": 1, "mars": 2,
              "jupiter": 16, "saturn": 18, "uranus": 15, "neptune": 8}

# Muted, print-safe tints. Deliberately not the "true" planet colours:
# these have to survive a mono office printer.
COLOUR = {"mercury": "#8c8378", "venus": "#c8922f", "earth": "#2f6f8f",
          "mars": "#b2472c", "jupiter": "#a8713b", "saturn": "#b8993f",
          "uranus": "#4f8f8a", "neptune": "#3a5ea8"}


# --------------------------------------------------------------------------
# API
# --------------------------------------------------------------------------

def fetch_json(url: str) -> dict:
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT,
                                               "Accept": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=HTTP_TIMEOUT) as resp:
            return json.load(resp)
    except urllib.error.HTTPError as exc:
        raise SystemExit(f"API returned HTTP {exc.code} for {url}") from exc
    except (urllib.error.URLError, TimeoutError) as exc:
        raise SystemExit(f"Could not reach {url}: {exc}") from exc
    except json.JSONDecodeError as exc:
        raise SystemExit(f"{url} did not return JSON: {exc}") from exc


def fetch_stats(api_base: str) -> dict:
    """Catalogue counts for the page-1 stats strip."""
    data = fetch_json(f"{api_base}/stats")
    by_type = data.get("by_object_type", {})
    tnos = by_type.get("tno", 0) + by_type.get("centaur", 0)
    return {
        "N_PLANETS": f"{by_type.get('planet', 0):,}",
        "N_DWARF": f"{by_type.get('dwarf_planet', 0):,}",
        "N_MOONS": f"{by_type.get('moon', 0):,}",
        "N_ASTEROIDS": f"{by_type.get('asteroid', 0):,}",
        "N_COMETS": f"{by_type.get('comet', 0):,}",
        "N_TNOS": f"{tnos:,}",
        "N_TOTAL_M": f"{data.get('total_objects', 0) / 1e6:.2f}".rstrip("0").rstrip("."),
    }


def fetch_moon_counts(api_base: str) -> dict:
    """Moons per parent body, counted from the catalogue itself.

    Returns {parent_id: count}, e.g. {"planet-saturn": 316, "dwarf-pluto": 5}.
    """
    counts: dict[str, int] = {}
    after = None
    while True:
        url = f"{api_base}/objects?type=moon&limit=500"
        if after:
            url += f"&after={after}"
        page = fetch_json(url)
        results = page.get("results", [])
        for moon in results:
            parent = moon.get("parent_id") or "unknown"
            counts[parent] = counts.get(parent, 0) + 1
        after = page.get("next_after")
        if not results or not after:
            break
    if not counts:
        raise SystemExit("the API returned no moons -- cannot build the moons page")
    return counts


def fetch_planet(api_base: str, slug: str) -> dict:
    record = fetch_json(f"{api_base}/objects/planet-{slug}")
    if not record.get("orbital"):
        raise SystemExit(f"planet-{slug} has no orbital elements in the API response")
    return record


# --------------------------------------------------------------------------
# Orbital mechanics -- two-body Kepler propagation from the J2000 elements
# --------------------------------------------------------------------------

def julian_day(when: dt.datetime) -> float:
    """Julian Day for a UTC datetime (proleptic Gregorian)."""
    a = (14 - when.month) // 12
    y = when.year + 4800 - a
    m = when.month + 12 * a - 3
    jdn = (when.day + (153 * m + 2) // 5 + 365 * y
           + y // 4 - y // 100 + y // 400 - 32045)
    day_fraction = (when.hour - 12) / 24 + when.minute / 1440 + when.second / 86400
    return jdn + day_fraction


def solve_kepler(mean_anomaly_deg: float, e: float) -> float:
    """Eccentric anomaly (radians) by Newton-Raphson. Converges in <10 passes
    for every planetary eccentricity; the cap is belt-and-braces."""
    m = math.radians(mean_anomaly_deg % 360.0)
    ecc_anom = m if e < 0.8 else math.pi
    for _ in range(60):
        delta = ((ecc_anom - e * math.sin(ecc_anom) - m)
                 / (1 - e * math.cos(ecc_anom)))
        ecc_anom -= delta
        if abs(delta) < 1e-13:
            break
    return ecc_anom


def propagate(orbital: dict, jd: float) -> dict:
    """Heliocentric ecliptic position at `jd` from J2000 Keplerian elements.

    Good to a fraction of a degree over decades, which is far finer than a
    printed dial can show. It ignores planetary perturbations and the slow
    secular drift of the elements, so do not use it for an ephemeris.
    """
    a = orbital["semi_major_axis_au"]
    e = orbital["eccentricity"]
    inc = math.radians(orbital["inclination_deg"])
    node = math.radians(orbital["longitude_ascending_node_deg"])
    argp = math.radians(orbital["argument_periapsis_deg"])
    period = orbital["orbital_period_days"]

    mean_anom = orbital["mean_anomaly_deg"] + 360.0 * (jd - J2000_JD) / period
    ecc_anom = solve_kepler(mean_anom, e)
    true_anom = 2 * math.atan2(math.sqrt(1 + e) * math.sin(ecc_anom / 2),
                               math.sqrt(1 - e) * math.cos(ecc_anom / 2))
    r = a * (1 - e * math.cos(ecc_anom))

    u = argp + true_anom
    x = r * (math.cos(node) * math.cos(u) - math.sin(node) * math.sin(u) * math.cos(inc))
    y = r * (math.sin(node) * math.cos(u) + math.cos(node) * math.sin(u) * math.cos(inc))

    return {
        "a": a,
        "e": e,
        "r": r,
        "period_days": period,
        "longitude_deg": math.degrees(math.atan2(y, x)) % 360.0,
        "perihelion_longitude_deg": math.degrees(node + argp) % 360.0,
        "orbit_fraction": (mean_anom % 360.0) / 360.0,
    }


# --------------------------------------------------------------------------
# Rendering
# --------------------------------------------------------------------------

def dial_svg(pos: dict, colour: str) -> str:
    """One planet's dial: its orbit to true eccentricity, the Sun at the
    focus, the planet at its current heliocentric ecliptic longitude.

    Drawn in a 220x220 user-unit box: ring and tick labels sit outside the
    orbit so nothing collides at print size.
    """
    cx = cy = 110.0
    semi_major = 64.0
    e = pos["e"]
    semi_minor = semi_major * math.sqrt(1 - e * e)
    peri = math.radians(pos["perihelion_longitude_deg"])

    # The Sun sits at a focus, so the ellipse centre is offset by a*e away
    # from perihelion. SVG y grows downwards, hence the sign flips.
    ox = cx - semi_major * e * math.cos(peri)
    oy = cy + semi_major * e * math.sin(peri)
    rotation = -math.degrees(peri)

    radius = pos["r"] / pos["a"] * semi_major
    lon = math.radians(pos["longitude_deg"])
    px = cx + radius * math.cos(lon)
    py = cy - radius * math.sin(lon)

    peri_x = cx + semi_major * (1 - e) * math.cos(peri)
    peri_y = cy - semi_major * (1 - e) * math.sin(peri)

    ticks = []
    for degrees in (0, 90, 180, 270):
        t = math.radians(degrees)
        ticks.append(
            f'<line x1="{cx + 84 * math.cos(t):.1f}" y1="{cy - 84 * math.sin(t):.1f}"'
            f' x2="{cx + 89 * math.cos(t):.1f}" y2="{cy - 89 * math.sin(t):.1f}"'
            f' stroke="#b9b2a2" stroke-width="1"/>'
            f'<text x="{cx + 99 * math.cos(t):.1f}" y="{cy - 99 * math.sin(t):.1f}"'
            f' font-size="9" fill="#a09889" text-anchor="middle" dy="3.2">{degrees}&#176;</text>'
        )

    return (
        '<svg viewBox="0 0 220 220" width="100%" height="100%" '
        'xmlns="http://www.w3.org/2000/svg">'
        f'<circle cx="{cx}" cy="{cy}" r="84" fill="none" stroke="#e2ddd0" stroke-width="1"/>'
        + "".join(ticks)
        + f'<ellipse cx="{ox:.2f}" cy="{oy:.2f}" rx="{semi_major:.2f}" ry="{semi_minor:.2f}"'
          f' transform="rotate({rotation:.3f} {ox:.2f} {oy:.2f})" fill="none"'
          f' stroke="#c9c2b2" stroke-width="1.1" stroke-dasharray="3 2.6"/>'
        + f'<circle cx="{peri_x:.1f}" cy="{peri_y:.1f}" r="2" fill="#c9c2b2"/>'
        + f'<line x1="{cx}" y1="{cy}" x2="{px:.1f}" y2="{py:.1f}" stroke="{colour}"'
          f' stroke-width="1.1" stroke-dasharray="2 2"/>'
        + f'<circle cx="{cx}" cy="{cy}" r="6" fill="#e8a42a"/>'
          f'<circle cx="{cx}" cy="{cy}" r="10" fill="none" stroke="#e8a42a"'
          f' stroke-width="0.8" opacity="0.45"/>'
        + f'<circle cx="{px:.1f}" cy="{py:.1f}" r="6" fill="{colour}"/>'
          f'<circle cx="{px:.1f}" cy="{py:.1f}" r="9.5" fill="none" stroke="{colour}"'
          f' stroke-width="0.8" opacity="0.35"/>'
        + "</svg>"
    )


def format_period(days: float) -> str:
    years = days / 365.25
    return f"{days:,.0f} days" if years < 2 else f"{years:,.1f} years"


def panel_html(name: str, slug: str, pos: dict) -> str:
    colour = COLOUR[slug]
    rows = [
        ("Longitude", f"{pos['longitude_deg']:.1f}&deg;", False),
        ("Sun distance", f"{pos['r']:.3f} AU", False),
        ("&nbsp;", f"{pos['r'] * AU_KM / 1e6:,.1f} million km", True),
        ("Orbit progress", f"{pos['orbit_fraction'] * 100:.1f}%", False),
        ("Period", format_period(pos["period_days"]), False),
        ("Orbit radius", f"{pos['a']:.3f} AU", False),
    ]
    body = "".join(
        f'<div class="row"><span class="k">{k}</span>'
        f'<span class="v{" sm" if small else ""}">{v}</span></div>'
        for k, v, small in rows
    )
    return (
        '<td class="panel">'
        f'<div class="pname"><span class="sym" style="color:{colour}">'
        f'{SYMBOL[slug]}</span>{name}</div>'
        '<table class="pin"><tr>'
        f'<td class="dia">{dial_svg(pos, colour)}</td>'
        f'<td class="dat">{body}</td>'
        "</tr></table></td>"
    )


def moons_page_values(counts: dict) -> dict:
    """Fill the optional page-3 table and pull-facts from live moon counts."""
    rows = []
    total_then = total_now = 0
    for slug in PLANETS:
        then = MOONS_1991[slug]
        now = counts.get(f"planet-{slug}", 0)
        added = now - then
        total_then += then
        total_now += now
        css = ' class="none"' if added == 0 else ""
        rows.append(
            f"<tr{css}>"
            f'<td class="n"><span class="sym" style="color:{COLOUR[slug]}">'
            f"{SYMBOL[slug]}</span>{slug.title()}</td>"
            f"<td>{then}</td><td>{now:,}</td>"
            f'<td class="add">{"&mdash;" if added == 0 else f"+{added:,}"}</td>'
            "</tr>"
        )
    rows.append(
        '<tr class="tot"><td class="n">All eight planets</td>'
        f"<td>{total_then}</td><td>{total_now:,}</td>"
        f'<td class="add">+{total_now - total_then:,}</td></tr>'
    )

    dwarf = sum(n for parent, n in counts.items() if parent.startswith("dwarf-"))
    return {
        "MOON_ROWS": "".join(rows),
        "FACT_SATURN": f"+{counts.get('planet-saturn', 0) - MOONS_1991['saturn']:,}",
        "FACT_DWARF": f"{dwarf:,}",
    }


def build_html(template: str, stats: dict, panels: list[str],
               when: dt.datetime, api_base: str,
               moons: dict | None = None) -> str:
    start, end = "<!--PAGE3_START-->", "<!--PAGE3_END-->"
    if moons is None:
        head, _, rest = template.partition(start)
        _, _, tail = rest.partition(end)
        template = head + tail
    else:
        template = template.replace(start, "").replace(end, "")

    grid = "".join(f"<tr>{panels[i]}{panels[i + 1]}</tr>" for i in range(0, 8, 2))
    day = when.strftime("%d %B %Y").lstrip("0")
    values = dict(stats)
    values["PANELS"] = grid
    values["DATE_HUMAN"] = f"{day}, {when:%H:%M} UTC"
    values["API_HOST"] = api_base.split("//", 1)[-1].split("/", 1)[0]
    values["N_PAGES"] = "3" if moons else "2"
    if moons:
        values.update(moons_page_values(moons))

    for key, value in values.items():
        template = template.replace("{{" + key + "}}", value)

    leftover = [t for t in ("{{PANELS}}", "{{DATE_HUMAN}}") if t in template]
    if leftover:
        raise SystemExit(f"template placeholders left unfilled: {leftover}")
    return template


def render_pdf(html_path: Path, pdf_path: Path, binary: str) -> None:
    """wkhtmltopdf with page margins at zero -- the template owns its own
    margins so the two pages stay consistent edge to edge."""
    if shutil.which(binary) is None:
        raise SystemExit(
            f"{binary} not found on PATH.\n"
            "  Debian/Ubuntu: sudo apt-get install wkhtmltopdf\n"
            "  macOS:         brew install --cask wkhtmltopdf\n"
            "  Or pass --keep-html and print the HTML from a browser."
        )
    result = subprocess.run(
        [binary, "--page-size", "A4", "--enable-local-file-access",
         "--margin-top", "0", "--margin-bottom", "0",
         "--margin-left", "0", "--margin-right", "0",
         str(html_path), str(pdf_path)],
        capture_output=True, text=True,
    )
    if result.returncode != 0 or not pdf_path.exists():
        sys.stderr.write(result.stderr)
        raise SystemExit(f"{binary} failed with exit code {result.returncode}")


# --------------------------------------------------------------------------

def parse_when(raw: str | None) -> dt.datetime:
    if raw is None:
        now = dt.datetime.now(dt.timezone.utc)
        return now.replace(minute=0, second=0, microsecond=0, tzinfo=None)
    try:
        parsed = dt.datetime.fromisoformat(raw.replace("Z", "+00:00"))
    except ValueError:
        raise SystemExit(f"--date must be ISO 8601, e.g. 2027-03-20T12:00:00Z (got {raw!r})")
    if parsed.tzinfo is not None:
        parsed = parsed.astimezone(dt.timezone.utc).replace(tzinfo=None)
    return parsed


def main() -> None:
    here = Path(__file__).resolve().parent
    parser = argparse.ArgumentParser(
        description="Build the two-page A4 Solar handout as a PDF.")
    parser.add_argument("--api-base", default=DEFAULT_API,
                        help=f"REST API root (default: {DEFAULT_API})")
    parser.add_argument("--date", default=None,
                        help="UTC instant for the planet positions, ISO 8601 "
                             "(default: now, rounded down to the hour)")
    parser.add_argument("--out", default=None, type=Path,
                        help="output PDF (default: build/solar-handout-<date>.pdf)")
    parser.add_argument("--template", default=here / "template.html", type=Path,
                        help="HTML template to fill")
    parser.add_argument("--wkhtmltopdf", default="wkhtmltopdf",
                        help="path to the wkhtmltopdf binary")
    parser.add_argument("--moons-page", action="store_true",
                        help="add page 3: moons known in 1991 vs today")
    parser.add_argument("--keep-html", action="store_true",
                        help="keep the intermediate HTML next to the PDF")
    parser.add_argument("--html-only", action="store_true",
                        help="write the HTML and stop (no wkhtmltopdf needed)")
    args = parser.parse_args()

    when = parse_when(args.date)
    api_base = args.api_base.rstrip("/")

    if not args.template.is_file():
        raise SystemExit(f"template not found: {args.template}")
    template = args.template.read_text(encoding="utf-8")

    out = args.out or here / "build" / f"solar-handout-{when:%Y-%m-%d}.pdf"
    out.parent.mkdir(parents=True, exist_ok=True)
    html_path = out.with_suffix(".html")

    print(f"Fetching catalogue counts from {api_base} ...", file=sys.stderr)
    stats = fetch_stats(api_base)

    jd = julian_day(when)
    panels = []
    for slug in PLANETS:
        record = fetch_planet(api_base, slug)
        pos = propagate(record["orbital"], jd)
        panels.append(panel_html(record.get("name", slug.title()), slug, pos))
        print(f"  {record.get('name', slug):<8} "
              f"{pos['longitude_deg']:7.2f} deg  {pos['r']:8.4f} AU", file=sys.stderr)

    moons = None
    if args.moons_page:
        print("Counting moons ...", file=sys.stderr)
        moons = fetch_moon_counts(api_base)
        total = sum(n for p, n in moons.items() if p.startswith("planet-"))
        print(f"  {total:,} at the eight planets, "
              f"{sum(MOONS_1991.values())} known in 1991", file=sys.stderr)

    html_path.write_text(
        build_html(template, stats, panels, when, api_base, moons),
        encoding="utf-8")

    if args.html_only:
        print(html_path)
        return

    render_pdf(html_path, out, args.wkhtmltopdf)
    if not args.keep_html:
        html_path.unlink(missing_ok=True)
    print(out)


if __name__ == "__main__":
    main()
