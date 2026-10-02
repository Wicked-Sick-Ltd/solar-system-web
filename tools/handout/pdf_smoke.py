#!/usr/bin/env python3
"""Render deterministic handout PDFs with real wkhtmltopdf, never live API data.

Use --docker-image for the isolated distro renderer; otherwise use a local
wkhtmltopdf binary. Poppler utilities on PATH validate and render every page.
The output is conspicuously marked as an offline QA snapshot, not live data.
"""
from __future__ import annotations

import argparse
import datetime as dt
import json
from html.parser import HTMLParser
import os
from pathlib import Path
import re
import subprocess
import xml.etree.ElementTree as ET
from unittest.mock import patch
from urllib.parse import urlsplit

import generate

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
NOTICE = "OFFLINE QA SNAPSHOT - NOT CURRENT CATALOGUE COUNTS"


def run(command: list[str]) -> str:
    return subprocess.check_output(command, text=True, stderr=subprocess.STDOUT)


def fixture_html(fixture: dict, moons: bool) -> str:
    # Exercise the real count formatter and orbital propagation. Any accidental
    # network fetch fails immediately instead of silently replacing the fixture.
    with patch.object(generate, "fetch_json", return_value=fixture["stats"]), \
            patch.object(generate.urllib.request, "urlopen", side_effect=AssertionError("Offline QA must not fetch data")):
        stats = generate.fetch_stats(generate.DEFAULT_API)
    when = dt.datetime(2026, 10, 1, 12)
    panels = [generate.panel_html(fixture["planets"][slug]["name"], slug,
              generate.propagate(fixture["planets"][slug]["orbital"], generate.julian_day(when)))
              for slug in generate.PLANETS]
    template = (HERE / "template.html").read_text()
    template = template.replace('catalogue counts at generation time', 'retained catalogue counts; not current data')
    template = template.replace('read from the API at generation time', 'taken from the committed offline QA fixture')
    template = template.replace('Source: NASA/JPL via <code>{{API_HOST}}</code>', 'Source: retained catalogue QA fixture (NASA/JPL)')
    template = template.replace('re-run\n      <code>tools/handout/generate.py</code> at any time for a current one.',
                                'this fixed model date is used for offline layout verification.')
    template = template.replace('</style>', '.qa-notice { margin: 0 0 3mm; font: bold 7pt "DejaVu Sans", sans-serif; color: #8a3c16; }\n</style>')
    template = re.sub(r'(<div class="page[^\"]*">)', r'\1<div class="qa-notice">' + NOTICE + '</div>', template)
    return generate.build_html(template, stats, panels, when, generate.DEFAULT_API,
                               fixture["moons"] if moons else None)


def render(html: Path, pdf: Path, image: str | None, context: str, binary: str) -> None:
    if not image:
        generate.render_pdf(html, pdf, binary)
        return
    command = ['docker', '--context', context, 'run', '--rm', '--network', 'none',
               '--read-only', '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges',
               '--tmpfs', '/tmp:rw,nosuid,size=128m', '--user', f'{os.getuid()}:{os.getgid()}',
               '--mount', f'type=bind,source={html.parent},target=/work', image,
               'wkhtmltopdf', '--page-size', 'A4', '--disable-local-file-access', '--disable-javascript',
               '--margin-top', '0', '--margin-bottom', '0', '--margin-left', '0', '--margin-right', '0',
               '/work/' + html.name, '/work/' + pdf.name]
    run(command)


def validate(pdf: Path, expected_pages: int, *, qa: bool = True, links_expected: list[str] | None = None) -> dict:
    info = run(['pdfinfo', '-box', str(pdf)])
    pages = int(re.search(r'^Pages:\s+(\d+)', info, re.M).group(1))
    if pages != expected_pages:
        raise AssertionError(f'{pdf.name}: expected {expected_pages} pages, got {pages}')
    bbox = ET.fromstring(run(['pdftotext', '-bbox', str(pdf), '-']))
    page_nodes = bbox.findall('.//{http://www.w3.org/1999/xhtml}page')
    markers = ['Public Universe', 'Eight worlds, model snapshot', 'Moons then and now']
    bounds = []
    for index, page in enumerate(page_nodes):
        width, height = float(page.get('width')), float(page.get('height'))
        if abs(width - 595.276) > 1 or abs(height - 841.89) > 1:
            raise AssertionError(f'{pdf.name}: page {index + 1} is not A4: {width}x{height}')
        words = page.findall('{http://www.w3.org/1999/xhtml}word')
        text = ' '.join(''.join(word.itertext()) for word in words)
        for phrase in ([NOTICE] if qa else []) + [markers[index], f'Page {index + 1} of {expected_pages}']:
            if phrase not in text:
                raise AssertionError(f'{pdf.name}: missing page {index + 1} marker: {phrase}')
        if len(words) < 50 or '{{' in text:
            raise AssertionError(f'{pdf.name}: blank/incomplete page {index + 1}')
        for word in words:
            if (float(word.get('xMin')) < 10 or float(word.get('yMin')) < 8
                    or float(word.get('xMax')) > width - 10 or float(word.get('yMax')) > height - 8):
                raise AssertionError(f'{pdf.name}: text outside safe page bounds: {word.text}')
        bounds.append({'page': index + 1, 'words': len(words),
                       'bottom_pt': max(float(word.get('yMax')) for word in words)})
    if len(page_nodes) != expected_pages:
        raise AssertionError('PDF text extraction omitted pages')
    def normalise_url(url: str) -> str:
        parsed = urlsplit(url)
        return parsed._replace(path=parsed.path or '/').geturl()

    links = {normalise_url(line.split(None, 2)[2])
             for line in run(['pdfinfo', '-url', str(pdf)]).splitlines()
             if re.match(r'^\s*\d+\s+Annotation\s+', line)}
    expected = links_expected or [generate.DEFAULT_SITE_URL, generate.DEFAULT_API,
                generate.DEFAULT_DOWNLOAD_URL, 'https://api.sol.wickedsick.com/mcp',
                'https://api.sol.wickedsick.com/docs']
    for url in expected:
        if normalise_url(url) not in links:
            raise AssertionError(f'{pdf.name}: missing PDF link annotation: {url}')
    preview = ROOT / 'tmp/pdfs' / pdf.stem
    preview.parent.mkdir(parents=True, exist_ok=True)
    run(['pdftoppm', '-r', '100', '-png', str(pdf), str(preview)])
    return {'file': pdf.name, 'pages': pages, 'page_bounds': bounds, 'links_checked': len(expected)}


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--out-dir', type=Path, default=ROOT / 'output/pdf')
    parser.add_argument('--docker-image')
    parser.add_argument('--docker-context', default='desktop-linux')
    parser.add_argument('--wkhtmltopdf', default='wkhtmltopdf')
    parser.add_argument('--render-html', type=Path, help='Render and validate an already generated Public Universe handout; no data fetch')
    parser.add_argument('--expected-pages', type=int, choices=(2, 3), default=2)
    args = parser.parse_args()
    if args.render_html:
        class Links(HTMLParser):
            def __init__(self):
                super().__init__()
                self.urls = []

            def handle_starttag(self, tag, attrs):
                if tag == 'a':
                    url = dict(attrs).get('href', '')
                    if url.startswith(('https://', 'http://')) and url not in self.urls:
                        self.urls.append(url)

        source = args.render_html.resolve()
        links = Links()
        source_text = source.read_text()
        links.feed(source_text)
        if len(links.urls) < 5:
            raise AssertionError('Handout should provide website, REST, MCP, docs and download links')
        pdf = source.with_suffix('.pdf')
        render(source, pdf, args.docker_image, args.docker_context, args.wkhtmltopdf)
        print(json.dumps(validate(pdf, args.expected_pages, qa=NOTICE in source_text, links_expected=links.urls), indent=2))
        return
    output = args.out_dir.resolve()
    output.mkdir(parents=True, exist_ok=True)
    fixture = json.loads((HERE / 'fixture.json').read_text())
    results = []
    for count in (2, 3):
        pdf = output / f'public-universe-handout-{count}page-qa.pdf'
        html = pdf.with_suffix('.html')
        html.write_text(fixture_html(fixture, moons=count == 3))
        render(html, pdf, args.docker_image, args.docker_context, args.wkhtmltopdf)
        results.append(validate(pdf, count))
    report = {'fixture_provenance': fixture['provenance'], 'results': results,
              'visual_review': 'Inspect every generated PNG; automated bounds do not prove absence of overlap.'}
    (output / 'validation.json').write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps(report, indent=2))


if __name__ == '__main__':
    main()
