# Public Universe domain migration

Status: preparation only, updated 2 October 2026. No DNS, infrastructure, mail
or live service changes are authorized by this development document. Obtain
approval for the concrete production cutover after completing the rehearsal
below.

**Domain decision (Craig, 2 October 2026).** `publicuniverse.net` is the
canonical website host, set through `APP_URL`. `sol.wickedsick.com` is a
**permanent alias of the same site with no redirect**: printed handouts and QR
codes point at it, and paper cannot be recalled. There is no host-redirect
middleware and none should be added. Canonical tags, OG URLs, JSON-LD, sitemap
`<loc>` entries and the `robots.txt` `Sitemap:` line are rewritten onto
`APP_URL` whichever host served the request (`App\Support\Links::canonical()`,
covered by `tests/Feature/CanonicalHostTest.php`); navigation, assets and
Livewire round-trips stay on the serving host. Only `www.publicuniverse.net`
redirects (301 to the apex at the edge). If the alias is ever retired, every
path must 301 permanently, path for path and query-preserving, to the same
path on `publicuniverse.net`, as an edge rule rather than application code.
[DEPLOYMENT.md](../DEPLOYMENT.md) (*Hostnames: canonical and alias*) is the
operational record of this policy; keep the two documents in agreement.

## Names and compatibility

The public name is **Public Universe** (`SITE_NAME`). Keep the repository names,
API schemas, object IDs, routes and MCP server identifier `solar-system-db`
compatible. This release changes editorial branding, not service addresses.
`APP_NAME` is an operational identifier too: changing it can change default
session-cookie names and cache prefixes. Leave existing values unchanged.

Proposed addresses, subject to provisioning and production approval:

| Surface | Current configuration/address | Proposed address |
| --- | --- | --- |
| Website | `APP_URL`, currently `https://sol.wickedsick.com` | `https://publicuniverse.net` (canonical); `sol.wickedsick.com` stays a permanent alias, no redirect |
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
  Cover `www.publicuniverse.net` (301 to the apex) and the `sol.wickedsick.com`
  alias with certificates. Keep certificates and DNS for old hosts valid.
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
  configurable branding and public URLs. Legacy website/API/download origins
  remain defaults; set the approved values explicitly when generating new
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
3. Serve both hostnames from the same site. Add `sol.wickedsick.com` (and
   `www.publicuniverse.net`) as aliases of the `publicuniverse.net` site with a
   certificate covering every name. `APP_URL` alone does not change which host
   a request arrives on: request-time URL generation deliberately follows the
   serving host, and only the SEO surfaces are rewritten onto the canonical
   one. Verify canonical tags, OG URLs, JSON-LD, sitemap entries, the robots
   sitemap URL and mail links using requests to both hosts; both must answer
   `200` and both must name `publicuniverse.net` in those surfaces.
4. Do **not** redirect `sol.wickedsick.com` to the new host. The alias stays
   live indefinitely because printed material points at it. Preserve existing
   `/objects/{id}`, `/exoplanets/{id}` and `/systems/{id}` paths on both hosts;
   upstream exoplanet renames are a separate ID issue. The only website
   redirect is `www.publicuniverse.net` → apex (301, query preserved) at the
   edge. Should the alias ever be retired, apply the permanent path-for-path
   301 policy recorded above and in DEPLOYMENT.md; rehearse it first and avoid
   home-page catch-all redirects and redirect chains.
5. Because there is no cross-origin redirect, open sessions, Livewire requests
   and form POSTs are never moved between origins by the server. Visitors who
   switch hosts themselves start a new session; see *Accounts, preferences and
   mail*. If a redirect is ever introduced, do not blindly redirect
   login/logout/Livewire/form POST requests across origins: their session/CSRF
   context does not transfer, and a method-preserving 308 alone does not solve
   session migration.
6. Publish the sitemap (every `<loc>` already names the canonical host) and
   verify ownership/search indexing configuration for both website properties.
   Search engines should treat the alias as a duplicate of the canonical host;
   monitor old/new indexing, errors and request volumes on both hosts. See
   [Google's site-move guidance](https://developers.google.com/search/docs/crawling-indexing/site-move-with-url-changes)
   for the canonical-consolidation case.

## API, MCP and download compatibility

Do not redirect API or MCP traffic between hosts either. Existing clients
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
- Cookies cannot span `wickedsick.com` and `publicuniverse.net`. A visitor who
  moves from the alias to the canonical host signs in again. Use host-scoped
  cookies with appropriate secure settings; do not attempt to copy session
  tokens through URLs or weaken cookie boundaries.
- `localStorage` is origin-specific. `theme`, `observer_location` and
  `preferences` do not follow a visitor between hosts. The existing `/settings`
  share link can carry a user-selected import in its `#s=` fragment: offer
  instructions to copy settings on one origin, replace only the link origin,
  then review and accept the import on the other. If no transition page is
  built, clearly state that preferences must be set again. Browser geolocation
  permission is also origin-specific.
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

- DNS/TLS renewal results for every old/new host; tested host mappings (both
  hosts `200`, canonical surfaces naming `publicuniverse.net`, `www` → apex
  301) for pages, queries, missing pages, forms and open sessions.
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
needed; remove any redirect loops; rebuild configuration and URL caches and
restart workers. Keep TLS and working routes on both domains: the alias stays
in service either way, and cached `www` redirects and DNS may outlive the
rollback. DNS reversal alone is insufficient.

Preserve all account/alert writes made during the transition. Do not restore an
older account database over new registrations or alert edits: prefer compatible
code/config rollback with the current database; handle any incompatible schema
through the reviewed release procedure. Re-run sign-in, alerts, API/MCP and
manifest checks and record what failed before a new cutover attempt.
