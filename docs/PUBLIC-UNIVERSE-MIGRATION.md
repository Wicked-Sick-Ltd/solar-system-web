# Public Universe domain migration

Status: reconciled with main PR #97 on 2 October 2026. The website policy is
`publicuniverse.net` canonical with `sol.wickedsick.com` served indefinitely
as an alias, without a redirect. See [hostnames](../DEPLOYMENT.md#hostnames-canonical-and-alias).
API/download hostname moves remain proposals. This document does not authorize
DNS, infrastructure, mail or live service changes.

## Names and compatibility

The public name is **Public Universe** (`SITE_NAME`). Keep the repository names,
API schemas, object IDs, routes and MCP server identifier `solar-system-db`
compatible. Website SEO uses the configured canonical origin on both serving hosts;
API and download service addresses remain independently configured.
`APP_NAME` is an operational identifier too: changing it can change default
session-cookie names and cache prefixes. Leave existing values unchanged.

Proposed addresses, subject to provisioning and production approval:

| Surface | Current configuration/address | Proposed address |
| --- | --- | --- |
| Website | `APP_URL`, currently `https://sol.wickedsick.com` | `https://publicuniverse.net` |
| REST | `API_BASE_URL=https://api.sol.wickedsick.com/api/v1` | `https://api.publicuniverse.net/api/v1` |
| MCP | API host plus `/mcp` | `https://api.publicuniverse.net/mcp` |
| OpenAPI / docs | API host plus `/openapi.json` and `/docs`; `API_DOCS_URL` override | Same paths on new API host |
| Download manifest | `SOLAR_DOWNLOAD_URL`, default `https://s3.wickedsick.com/solar-system-db/latest.json` | `https://download.publicuniverse.net/latest.json` |
| Public contact | `CONTACT_EMAIL`, default `hello@wickedsick.com` | Retain until a replacement mailbox is verified |
| Transactional sender | `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, provider configuration | Decide and verify separately from the web move |

Inventory actual deployed values rather than assuming defaults. In particular,
older handouts advertise `download.sol.wickedsick.com`: test and retain that
alias if it has been published. Do not change defaults to unprovisioned hosts.

## Preparation and rehearsal

- Record website/API/download origins, reverse proxy rules, CDN/cache policies,
  DNS zone and TTLs, certificate renewal ownership, mail provider and source
  repository commits. Back up the current configuration without committing
  secrets. Record a named cutover operator and rollback operator.
- Inventory links in web metadata, API docs/examples, MCP/plugin configurations,
  repositories, downloadable manifests, handouts, email templates and external
  integrations. Locate the plugin checkout before declaring client readiness.
- Back up the account/alert database and prove restoration in an isolated
  environment. Preserve `APP_KEY`, persisted account IDs and alert records.
  Follow [DEPLOYMENT.md](../DEPLOYMENT.md) for deploy/migration procedure; do not
  create a second independent account database for the new website.
- Provision approved web/API/download hostnames, DNS and TLS with renewals.
  Include `www.publicuniverse.net` only if serving or redirecting it, and cover
  that name with a certificate. Keep certificates and DNS for old hosts valid.
  Rehearse using staging or local hostname overrides before changing public DNS.
- Test the new hosts against the same intended data: REST responses, errors,
  pagination, CORS where applicable, rate-limit headers, OpenAPI and full MCP
  initialization/tool calls/streaming. Audit origin/host checks explicitly;
  allow the required hosts without widening them to arbitrary origins.
- Test downloads from the manifest through the final archive and licence links.
  Preserve historical archive names, checksums, snapshot identifiers and any
  absolute URLs already published. A new vanity host must serve a usable
  manifest, not merely a redirect to an HTML landing page.
- Review homepage, header at desktop/mobile widths, about page, metadata,
  emails, favicon and share cards for the new name. The committed default card
  is now `public/images/og-public-universe.png`, rendered locally and visually
  inspected at 1200×630 with Public Universe branding. Object-card disk paths
  and public image URLs include a bounded hash of `SITE_NAME`, the tagline and
  `OG_VERSION`; old immutable object images cannot occupy the new URL. Configure
  the CDN to include the `v` query parameter in its image cache key. The old
  `images/og-default.png` remains available for existing links.
- When changing the public brand or card design again, run
  `php artisan og:generate-default` with the intended local configuration and
  inspect the resulting PNG before committing it. The command uses the existing
  Imagick renderer/fonts and writes only the committed default image path.
  Name/tagline changes automatically version object cards; bump `OG_VERSION`
  for renderer/font/design changes. Regenerate the static default as well:
  versioning a URL cannot change the text already baked into its image.
  Confirm image content, not only the image URL. No production invalidation or
  CDN configuration has been performed by this development change.
- The handout template and generator now use Public Universe with escaped,
  configurable branding and public URLs. The website defaults to the canonical `publicuniverse.net` origin; API and
  download defaults remain unchanged. Set approved overrides when generating new
  material. Eleven offline tests cover generation and malformed-data handling.
  Actual wkhtmltopdf now renders both variants as two/three A4 pages; all five
  pages have been visually reviewed, with link and text-bound checks. This closes
  the default-layout renderer gap using a clearly labelled retained QA fixture;
  see [PDF acceptance evidence](qa/2026-10-01/handout-pdf.md). Before publishing
  current or newly branded material, regenerate and inspect every page again:
  verify links, no clipping, dated positions and source-specific attribution.
  See [handout instructions](../tools/handout/README.md).

## Website cutover

1. Complete the evidence checklist below, approve the production change window
   and communicate sign-in/preferences expectations through the site. Keep the
   broader information-architecture redesign separate from the domain switch.
2. Set `APP_URL` to the approved new origin; keep `API_BASE_URL` and download
   configuration on compatible working endpoints until their own checks pass.
   Set `SITE_NAME` independently. Rebuild Laravel configuration and relevant
   URL-bearing caches, restart long-lived workers, and ensure scheduled mail
   generates links for the new origin. Run only one alert scheduler.
3. Configure both serving hosts and their TLS certificates. `APP_URL` drives
   canonical tags, OG URLs, JSON-LD, sitemap entries and the robots sitemap URL
   through `Links::canonical()`. Navigation, assets and Livewire stay on the
   serving host. Verify both hosts and mail links after rebuilding URL caches.
4. Keep `sol.wickedsick.com` serving all existing paths indefinitely, including
   `/educators` and `/handouts/*.pdf`; printed QR codes depend on it. Do not
   redirect it to the canonical host. Only `www.publicuniverse.net` redirects
   to the apex, preserving paths and query strings.
5. Test login/logout/Livewire/form POSTs and open tabs on both hosts. Cookies
   and browser storage are origin-specific; canonical SEO does not transfer
   sessions or settings between them.
6. Publish the canonical sitemap and monitor indexing, errors and request
   volumes for both hosts. If the old alias is ever retired under a separate
   decision, preserve every path with a permanent redirect as documented in
   [DEPLOYMENT.md](../DEPLOYMENT.md#hostnames-canonical-and-alias).

## API, MCP and download compatibility

Do not introduce redirects for existing API or MCP traffic. Existing clients
may reject redirects, change methods, lose auth headers or fail streaming.
Prefer serving both API hostnames through the same compatible backend and
keeping old MCP endpoints available. Test complete old- and new-host client
sessions, including POSTs, error bodies and stream termination. Update example
configuration and plugin metadata after verification. Any deprecation needs a
separate measured client migration, published notice and retention decision.

For downloads, dual serving is safest for scripts and frozen manifests. If a
redirect is needed, test the actual downloader's redirect behaviour and every
referenced archive/checksum URL. Keep old immutable files available. Do not
rewrite manifests in a way that makes an existing snapshot irreproducible.
[HTTP 308 preserves the request method](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/308),
but it does not establish API/MCP client compatibility.

## Accounts, preferences and mail

- Account records and alerts remain in the existing application database. Keep
  `APP_KEY` and required mail/provider settings. Rehearse sign-in, logout,
  registration, alert management and scheduler duplicate prevention. Test any
  supported verification/reset/unsubscribe links, including already sent links;
  host-bound signatures may require old-origin handling or fresh links.
- Cookies cannot span `wickedsick.com` and `publicuniverse.net`. Expect users to
  sign in again. Use host-scoped cookies with appropriate secure settings; do
  not attempt to copy session tokens through URLs or weaken cookie boundaries.
- `localStorage` is origin-specific. `theme`, `observer_location` and
  `preferences` will not follow a redirect. The existing `/settings` share link
  can carry a user-selected import in its `#s=` fragment: before a blanket
  redirect, offer instructions to copy settings on the old origin, replace only
  the link origin with the new website, then review and accept import there.
  If no transition page is built, clearly state that preferences must be set
  again. Browser geolocation permission is also origin-specific.
- Never put observer coordinates or settings tokens in query parameters,
  redirect logs or analytics. Do not silently transfer consent: the
  `cookie_consent` choice is origin-specific too. Analytics must stay unloaded
  until consent is given on the new origin.
- Keep current contact/sender addresses until replacements work. Verify new
  sender-domain authentication and provider configuration before switching;
  test delivery, replies, spam classification, display name and embedded links
  with designated test mailboxes. Keep old contact addresses receiving mail.

## Evidence required before approval

Record date, operator, exact release commits and staging URLs with:

- DNS/TLS renewal results for every old/new host; tested host mappings and
  redirect matrix for pages, queries, missing pages, forms and open sessions.
- Rendered canonical/OG/JSON-LD/robots/sitemap examples; mobile/desktop branding
  screenshots and approved handout/share-card renders.
- Account backup restoration, sign-in/alerts and single scheduler checks;
  settings import/consent results without retaining private coordinates.
- Old/new REST and MCP client transcripts with secrets removed; manifest,
  archive and checksum checks; plugin configuration compatibility result.
- Verified mail delivery/link behaviour; exact cutover and rollback commands
  reviewed for the actual hosting platform. No example here authorizes them.

## Rollback

Rehearse rollback before cutover. If a defect appears, stop rollout and pause
new scheduler/mail work if it would generate broken links. Restore the recorded
serving configuration, `APP_URL`, backend/download endpoints and DNS routing as
needed; remove redirect loops; rebuild configuration and URL caches and restart
workers. Keep TLS and working routes on both domains because cached permanent
redirects and DNS may outlive the rollback. DNS reversal alone is insufficient.

Preserve all account/alert writes made during the transition. Do not restore an
older account database over new registrations or alert edits: prefer compatible
code/config rollback with the current database; handle any incompatible schema
through the reviewed release procedure. Re-run sign-in, alerts, API/MCP and
manifest checks and record what failed before a new cutover attempt.
