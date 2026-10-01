# Handout PDF acceptance - 1 October 2026

Both Public Universe variants were rendered by actual wkhtmltopdf and all five
final page PNGs were visually inspected. No live API requests or production
changes were made. The artifacts are offline QA snapshots, prominently labelled
on every page, and are not current catalogue reports.

## Renderer and isolation

- Local Docker Desktop engine 28.3.0, explicitly selected `desktop-linux` Unix
  socket context; Linux arm64 container. No other containers were changed.
- Official `debian:bookworm-slim` base, manifest digest
  `sha256:3783cc01769c7b2b1b83a5c5ad96c815348e28ed7da68e2e3687004faa906251`.
- Distro `wkhtmltopdf` **0.12.6-2+b1**, Qt WebKit
  **5.212.0~alpha4-30**, Poppler **22.12.0-2+deb12u3**.
- Fonts: Carlito **20220224-1**, DejaVu **2.37-6**. Font families match the
  template's explicit fallbacks. Host Poppler **26.09.0** extracted text/links
  and rendered the final previews at 100 dpi.
- Local image `public-universe-handout:bookworm`, observed image ID
  `sha256:a318e562f40ce11302f1699f7c20b5c5f54aabe9d4efdbbe0a83e6abbc26d71c`.
  Package versions are recorded evidence, not a guarantee that future apt
  installations yield identical bytes. CI also checks the resulting structure.
- Only image construction used the network to fetch distro packages. Rendering
  used `--network none`, `--read-only`, dropped capabilities, no new privileges,
  the invoking UID/GID and temporary `/tmp`; only its artifact directory was
  mounted. JavaScript and local file loading were disabled in wkhtmltopdf.

[Official Debian image](https://hub.docker.com/_/debian) and
[Debian wkhtmltopdf package](https://packages.debian.org/bookworm/wkhtmltopdf)
identify the renderer distribution. This is the distro's Qt build, not a
replacement browser/PDF engine or an unofficial wkhtmltopdf binary.

## Data used

`tools/handout/fixture.json` is a small public-astronomy extract of the retained
local catalogue, captured on 1 October: eight planetary orbital records,
per-type object counts and moon counts by parent. The source SQLite file was
opened read-only and never rebuilt or modified. Its SHA-256 is
`c9d2e45061214d739927c40418c1d7a23d1d3b3783fcdf0706f88bf635aa46ea`.

The fixture has 2,431 objects and 492 moons; it does **not** represent the
current full catalogue. Its eight-planet moon total is 480, compared with the
existing 60-moon 1991 reference, and the remaining 12 are at dwarf planets.
The page distinguishes snapshot differences from new-discovery claims. The
J2000 model is propagated to the fixed date 1 October 2026, 12:00 UTC. It remains
a two-body approximation, not a precision ephemeris or an observation.

## Findings and fixes

The initial two-page template produced **four PDF pages**: a blank page after
the introduction and a planetary footer orphaned on another page. Reduced
oversized section/footer spacing and planetary row padding corrected the
pagination while retaining legible type, source notes and all eight dials.

The former hardcoded "Four hundred and twenty new moons" heading and zero
inner-planet claim were replaced with a neutral heading and calculated snapshot
differences. Differences correctly show negatives for incomplete catalogues.
Exact total counts replace "0 million" for small catalogues. Source/API planet
names are HTML-escaped, and PDF execution does not need JavaScript or local file
access. Malformed count envelopes and incomplete/repeated moon pages now stop
generation instead of printing invented zero or partial totals. Existing website, API and download defaults were retained.

## Final checks

| Variant | A4 pages | Words by page | Lowest text bounding box, points |
| --- | --- | --- | --- |
| Default | 2 | 434, 362 | 800.31, 811.83 |
| With moons | 3 | 434, 362, 304 | 800.31, 811.83, 719.63 |

Each page is approximately 595.28 x 841.89 points (A4). Automated checks verify
page count/size, nonempty text, expected page headings/footers, no unfilled
placeholders, safe text bounds and website/REST/MCP/docs/download URI annotations.
All five PNGs were inspected for clipped/overlapping content, missing symbols,
blank pages, dark-panel contrast, moon table alignment and complete footers.
Every page visibly includes the offline QA label. Automated bounds support the
visual review; they do not by themselves prove absence of overlap.

Reproduction:

```bash
docker --context desktop-linux build --tag public-universe-handout:bookworm tools/handout
python3 -B tools/handout/test_generate.py
python3 -B tools/handout/pdf_smoke.py --docker-image public-universe-handout:bookworm
```

Final PDFs are local ignored artifacts at
`output/pdf/public-universe-handout-2page-qa.pdf` and
`output/pdf/public-universe-handout-3page-qa.pdf`. A structured report is beside
them; page previews are under `tmp/pdfs/`. No PDF, preview or local database is
committed. The fixture and validation code are committed test inputs/tools.

Remaining scope: live data handouts require a fresh generation and visual check;
custom names/endpoints/fonts may change pagination. Actual network destination
availability, printer output and other renderer versions were not tested.
GitHub Actions changes were checked locally, not executed remotely in this task.
