# Public Universe handout

A two-page A4 introduction to Public Universe, served at the existing
[sol.wickedsick.com](https://sol.wickedsick.com) origin until the domain move.
The planetary pages remain a solar-system snapshot generated from API data.

- **Page 1** — what Public Universe is, the catalogue counts, the free REST API, the MCP
  server and the nightly database download.
- **Page 2** — a dated snapshot of where the eight planets are, one dial each.
- **Page 3** *(optional, `--moons-page`)* — today's moon count per planet against the
  sixty known in 1991.

Both pages are built from the public API at run time: the counts come from
`/stats`, the J2000 orbital elements from `/objects/planet-<name>`. The optional
1991 moon counts are documented historical constants; current counts come from
the selected API. The generator does not claim a complete universe census.

## Build one

```bash
python3 tools/handout/generate.py
```

The PDF lands in `tools/handout/build/solar-handout-<date>.pdf`.

Needs Python 3.9+ (standard library only — no `pip install`) and
[wkhtmltopdf](https://wkhtmltopdf.org) on `PATH`:

```bash
sudo apt-get install wkhtmltopdf      # Debian/Ubuntu
brew install --cask wkhtmltopdf       # macOS
```

### Options

| Flag | What it does |
|---|---|
| `--moons-page` | Add page 3: moons known in 1991 vs today |
| `--date 2027-03-20T12:00:00Z` | Positions for a given UTC instant rather than now |
| `--out path/to/file.pdf` | Where to write the PDF |
| `--api-base http://127.0.0.1:8003/api/v1` | Build against a local `solar-system-db`; also controls printed API/MCP/docs links |
| `--site-name "Public Universe"` | Visitor-facing title and introduction |
| `--site-url https://sol.wickedsick.com` | Printed website links |
| `--download-url https://s3.wickedsick.com/solar-system-db/latest.json` | Printed catalogue manifest link |
| `--keep-html` | Keep the intermediate HTML beside the PDF |
| `--html-only` | Write the HTML and stop — no wkhtmltopdf needed |
| `--wkhtmltopdf /path/to/bin` | Use a wkhtmltopdf that isn't on `PATH` |

## Refreshing it

The sheet says on its face that it is a dated snapshot. The slow movers hold up
for months; Mercury shifts noticeably inside a fortnight. Reprint whenever you
need a current one — it takes a few seconds.

The `Handout` workflow in `.github/workflows/handout.yml` builds one on the
first of each month and on demand (**Actions → Handout → Run workflow**), and
attaches the PDF to the run. Nothing is committed: built PDFs are generated
artefacts and stay out of the repo, per `AGENTS.md`.

## How the positions are worked out

Two-body Kepler propagation of the J2000 elements — the same method as the
site's orrery and the MCP server's `compute_position` tool. Mean anomaly is
advanced to the target instant, Kepler's equation solved by Newton-Raphson,
and the result rotated into the ecliptic frame by the orbit's node,
inclination and argument of perihelion.

It ignores planetary perturbations and the slow secular drift of the elements,
which is fine for a printed dial and no use as an ephemeris. A quick sanity
check: at either equinox Earth should sit at 0° or 180° heliocentric ecliptic
longitude.

```bash
python3 tools/handout/generate.py --date 2027-03-20T12:00:00Z --html-only
#   Earth     180.38 deg    0.9961 AU
```

## The moons page

`--moons-page` adds a third sheet setting today's moon count against the sixty
known at the end of 1991, when *Deuteros* shipped on the Amiga. Today's column
is counted live from the catalogue; the 1991 column is a historical constant in
`MOONS_1991`, because it cannot be derived from discovery dates alone.

Two moons carry pre-1992 dates but were not known then, and both are counted as
modern discoveries: Jupiter's **Themisto** was seen in 1975, lost, and not
recovered until 2000; Uranus's **Perdita** was imaged by Voyager 2 in 1986,
went unnoticed until 1999, and was not confirmed until 2003. Excluding them
gives Jupiter 16 and Uranus 15 — the figures quoted at the time.

## Editing the design

`template.html` holds the whole document — layout, type and colour. The script
fills `{{PLACEHOLDER}}` tokens and generates the eight dials as inline SVG.

Two things to know before you move anything:

- **Horizontal measurements are percentages, not millimetres.** wkhtmltopdf
  renders the page at a wider viewport than CSS millimetres assume, so a
  horizontal `mm` value comes out about three-quarters of its nominal size and
  the page ends up left-aligned with a white gutter. Vertical `mm` behave
  normally.
- **Don't set `min-height` on `.page`.** With `box-sizing: border-box` it
  rounds past the paper height and emits a trailing blank page. The content is
  sized to fill the page instead.

After a change, check both pages still come out at two pages and edge to edge.

## Astronomy, not astrology

Same rule as the rest of the repo. No horoscopes or natal charts. Astronomical transits remain part of the
science.


## Branding and endpoint configuration

Command-line flags override `SITE_NAME`, `APP_URL`, `API_BASE_URL` and
`SOLAR_DOWNLOAD_URL` environment variables, respectively. The generator does
not load Laravel or read `.env` files. Defaults print **Public Universe** while
retaining the existing website/API origins and the same S3 manifest default as
the website configuration. Merely changing the brand does not move a service.
API documentation and MCP links use the configured API origin plus `/docs` and
`/mcp`; website and download links can be configured independently. Printed
public URLs must use HTTP(S) without credentials, query parameters or fragments.
Brand text and URL substitutions are HTML-escaped.

The default `solar-handout-<date>.pdf` filename and workflow artifact name are
retained for automation compatibility. Before a domain cutover, configure the
verified new endpoints explicitly and check the links in the generated artifact.
Source-specific reuse terms apply; the repositories' MIT licences do not make
all contributed scientific data public domain.

## Offline checks and PDF release prerequisite

```bash
python3 -B tools/handout/test_generate.py
```

These tests use isolated fixture counts and mocked fetches, exercise HTML-only
CLI output without network access or a PDF renderer, check optional planetary
pages, and verify branding, escaping and endpoint configuration. `--html-only`
itself still fetches API data in ordinary use; tests replace those reads.

**PDF rendering remains a release prerequisite.** The Public Universe template
changes have been checked as HTML and have not yet been rendered with
`wkhtmltopdf`. Before publishing a handout, render both the two-page default and
three-page `--moons-page` version, confirm A4 page counts and working links, and
inspect every page for clipping and overflow, especially the longer masthead and
endpoint labels. HTML tests do not establish PDF pagination or visual acceptance.
