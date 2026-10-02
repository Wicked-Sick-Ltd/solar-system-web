# Explained shortlist browser acceptance

2 October 2026, native Chrome controls, local web `f81d8cc` at loopback 18023
against backend `a4ef9bb` at 18009 with the pinned JPL DE440s provider. Synthetic
site 51.50° north, −0.12° east; Europe/London, night starting 2026-10-02. No
account, saved profile, geolocation request, weather request or production write.

The initial form had no site/date and defaulted to naked eye. Selecting a maximum
of three and explicitly submitting returned Moon, HR 1017 and HR 4301. The page
reported 157 supported catalogue records plus eight solar-system targets, one
unsupported record, 73 screening instants, 79 coarse matches and three refined
choices. The Moon window was 00:19:49–05:52:14 +01:00; the stars' displayed windows
were 19:47:54–05:52:14 +01:00. Reasons, unknown extent/solar brightness, source
magnitude bands, tables, JPL identity and sampling limits remained explicit.
[Actual result screenshot](shortlist-desktop.jpg).

Changing to telescope mode, true field 1°, and UTC hours 19:00–02:00 returned
Neptune, Saturn and Uranus with solar-system family explanations. The displayed
interval was 20:00–03:00 +01:00, with 22 sampled instants and 71 coarse matches.
Unknown angular extents did not become a field-fit claim. These checks establish
that controls affect the real service result, not independent astronomical
accuracy or optical suitability.

An explicit V-magnitude cutoff of −30 produced zero candidates, with 157 fainter
catalogue records and eight unknown-photometry records excluded. The empty state
explained that this bounded screening does not establish an empty observable sky.
It retained the source/model context instead of substituting targets.
[Actual empty-state screenshot](shortlist-empty.jpg).

Clearing that cutoff and resubmitting restored the three telescope candidates.
The native handoff link selected Neptune, Saturn and Uranus in the manual planner.
Its URL contained target identifiers only; date, latitude and longitude remained
blank and timezone UTC. No result appeared automatically. The link explains that
later equipment comparisons and exports describe a new calculation, not the
shortlist's screening/ranking.

The result URL remained `/observe/shortlist` throughout native POST submissions.
Screenshots are desktop evidence, not narrow-screen, physical-touch or assistive
technology certification. Existing browser overlays are not application UI.

Combined local checks at `f81d8cc`: 1,297 PHP tests / 5,809 assertions, 239 JS
tests, Pint, PHPStan, production build and diff check passed. Topic and combined
counts are deliberately not added together.
