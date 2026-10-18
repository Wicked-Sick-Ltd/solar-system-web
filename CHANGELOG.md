# Changelog

## 1.0.0 (2026-10-18)

First community release. Reviewed notes: `resources/releases/1.0.0.json`, published at `/whats-new/1.0.0` after the live readiness check.

### Features

* **seo:** nosnippet boilerplate, nofollow auth gates, single flyby summary, home/about schema, ranked objects sitemap ([#141](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/141))
* **seo:** noindex auth pages, descriptive titles, object summaries and Dataset/WebAPI/LearningResource JSON-LD ([#138](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/138))
* **api:** advertise api.publicuniverse.net as the catalogue API hostname ([#134](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/134))
* **api:** fetch the catalogue over loopback; public URL kept for visitor links; 5-minute probe ([#133](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/133))
* link official Public Universe social profiles ([#125](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/125))
* **sitemap:** index exoplanet and host-system pages ([#122](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/122))
* **observatory:** locate a site from a what3words address ([#121](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/121))
* **objects:** add an ecliptic flyby diagram for close approaches ([#118](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/118))
* **share:** redesign share cards and add a shareable object of the day ([#117](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/117))
* **educators:** show US grades beside UK Key Stages ([#116](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/116))
* **releases:** publish notes from merged pull requests ([#115](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/115))
* **api:** public catalogue developer docs ([#113](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/113))
* **home:** show the next close approach within 10 lunar distances ([#111](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/111))
* **analytics:** add consent-gated Google Analytics 4 ([#108](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/108))
* add education resources, plugin guide and private feedback ([#99](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/99))
* **close-approaches:** show mass when one is on record ([#105](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/105))
* consolidate observing programme with current main ([#98](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/98))
* **educators:** /educators page with downloadable classroom handouts ([#97](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/97))

### Fixes

* triage unaddressed review follow-ups (#126–#132) ([#140](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/140))
* public plugin links, working catalogue download and project credits ([#124](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/124))
* **seo:** ship static public/robots.txt so robots returns 200 behind Forge nginx ([#123](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/123))
* **home:** place featured today above the next close approach ([#120](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/120))
* **api:** catalogue search paths and night try-it ([#119](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/119))
* **a11y:** meet WCAG AA contrast for the light-theme amber accent ([#114](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/114))
* **a11y:** clear launch contrast, duplicates, and landmarks ([#112](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/112))
* **objects:** serve provisional moon ids that contain a slash ([#110](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/110))
* **deploy:** unblock Forge's release lock and composer command ([#109](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/109))
* **objects:** show Pluto's catalogue discovery date ([#106](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/106))
* **deps:** bump shell-quote to 1.11.0 and source-map-js to 1.2.2 ([#104](https://github.com/Wicked-Sick-Ltd/solar-system-web/pull/104))
