# Security policy

## Supported versions

Only the `main` branch is supported. It is what runs at
<https://sol.wickedsick.com>; there are no tagged releases.

## Reporting a vulnerability

Please report security issues **privately** by email to
**hello@wickedsick.com** (this will move to `hello@sol.wickedsick.com` once
that mailbox is live; both will keep working). Do not open a public issue.

Include what you found, how to reproduce it, and what you think the impact is.
You will get an acknowledgement within **5 working days**, and we'll keep you
updated as we fix it. We're happy to credit you in the fix commit if you'd
like.

There is no bug bounty programme.

## A few requests

- Please don't run load, fuzzing or scanning tools against the live site or
  its API. Run this repository locally instead — it takes a few minutes to set
  up (see [CONTRIBUTING.md](CONTRIBUTING.md)).
- The website stores account names, email addresses, password hashes and saved
  visibility alerts (including approximate observer coordinates) in its own
  database. Treat account/session compromise, access to another user's alerts,
  location disclosure and accidental caching of private responses as security
  issues. Do not include real account records or credentials in reports.
- We also care about backend API abuse through this front end, cache poisoning,
  SSRF via the API client, XSS through catalogue data, and the Open Graph image
  renderer. Astronomical catalogue data comes from a separate read-only API.
- Backups contain personal data. Keep them outside the public directory and
  repository, restrict access, encrypt off-site copies, and test restoration.
  See [DEPLOYMENT.md](DEPLOYMENT.md) for release and recovery requirements.
