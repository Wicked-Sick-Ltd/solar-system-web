# Public Universe local acceptance — 1 October 2026

Development branch only. Chrome against loopback web `127.0.0.1:18011` and API
`127.0.0.1:18003`, using the retained September 22 exoplanet snapshot and a small
solar-system fixture. Screenshot counts are not production catalogue counts.
No scheduler, live email or third-party observing services were enabled.

## Verified

- Galaxy: 251 nearby measured hosts, 4,747 in the schematic Milky Way view.
- Focused canvas ArrowRight moved the rendered Sun label from 400px to 393px;
  `+` changed its projection to 391.6px and Reset camera restored 400px.
- Selecting TRAPPIST-1 showed seven planets, distance and uncertainty, with a
  working host link. Clicking an isolated plotted point selected GJ 9404.
  Dragging empty map space retained the selection. Overview switching worked.
- Unified search for TRAPPIST-1 showed seven planets with planet/host links
  and an honest empty solar-system group. Desktop and 390 × 844 layouts checked.
- Public Universe wordmark and four primary links fit at 1024px. The mobile
  menu at 390px exposed Explore, Observe, Learn and Data without clipping.
- Learn page: native detail disclosure opened with Enter; source links and
  filtered transit-discovery link are present. Guest hubs also have automated
  backend-outage coverage.
- Homepage reviewed at 1024 × 900 and 390 × 844. Viewport override reset.

## Limitations

Physical touch rotate/pinch was not tested; mouse drag and responsive layout
do not certify touch behaviour. No production/domain cutover was performed.
Browser extensions emitted storage-access errors; map and navigation remained
functional. The screenshot may include extension UI.

The initial baseline had 216 PHP tests / 652 assertions, five JS tests and a
passing asset build. Post-change totals belong in the development progress log.

![Public Universe at 1024px](home-1024.png)
![Unified search on mobile](search-mobile.png)

Additional evidence: [desktop search](search-desktop.png),
[mobile homepage](home-mobile.png), [mobile learning page](learn-mobile.png).
