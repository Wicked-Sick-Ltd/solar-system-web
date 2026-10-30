# Fair use

Public Universe stays free to read. Ordinary browsing and light API use are
anonymous. Bulk and automated use of the catalogue API sends a key.

The public site is this repository. The API that enforces key tiers is
[solar-system-db](https://github.com/Wicked-Sick-Ltd/solar-system-db)
(`api/fair_use.py`). Cloudflare rate limits at the edge are separate. Because
the edge exempts any request that merely carries `X-PU-Api-Key` from a
stricter anonymous rule, the API treats a missing, blank, unknown, or
revoked key as anonymous per-IP traffic and also limits invalid-key attempts
per IP. Cloudflare Pro still caps **all** traffic, including keyed calls, at
**300 requests per minute per IP**. `/api-access` can record egress CIDR
ranges so operators can allowlist a high-volume institution at the edge.

## Pages

| Path | What it does |
| --- | --- |
| `/fair-use` | The policy, linked from the footer. |
| `/api-access` | Request form. Stores a row in `api_access_requests`, including optional egress CIDRs. |

`public/robots.txt` and `RobotsController` carry a short comment and the
fair-use link. They still allow `/` and publish the sitemap. Nothing in this
change disallows robots.txt or the sitemap.

Academic email domains (`.edu`, `.edu.<cc>`, `.ac.<cc>` such as `.ac.uk`) are
flagged `institution_eligible`. That flag does not issue a key.

## Notifications

Leave these unset to store the request and send nothing:

| Variable | When set |
| --- | --- |
| `PU_SITES_SLACK_WEBHOOK_URL` | HTTPS Slack incoming webhook for `#sites-publicuniverse`. |
| `PU_ASANA_ACCESS_TOKEN` | Asana personal access token. |
| `PU_ASANA_PROJECT_ID` | Asana project. Use `1220017034322863` for the Public Universe project. |

Asana runs only when the token and the project id are both set.

The form rejects a filled honeypot (`website`) and allows 5 posts per hour
per IP (`throttle:5,60,api-access`).

## API budgets

Enforced in solar-system-db, not in this app. Defaults:

| Tier | Budget |
| --- | --- |
| Anonymous (also missing, invalid, revoked keys) | 60/minute and 1,000/day per IP |
| Free key | 300/minute and 10,000/day per key |
| Institution key | 5,000/minute and 100,000/day per key (`PU_INSTITUTION_PER_DAY`) |

Those application budgets sit behind the Cloudflare Pro edge cap of 300
requests per minute per IP, which applies to keyed traffic as well. A higher
burst needs an edge allowlist. The access form stores optional egress CIDRs
for that.

A 429 carries `Retry-After` and a link to `/api-access`. Responses on `/api/`
include `X-PU-Key-Tier` so the edge can tell keyed traffic from anonymous
traffic. See the API repository README for the full environment list,
including `PU_API_KEYS_PATH` and `PU_API_KEY_PEPPER`. Do not commit keys.
