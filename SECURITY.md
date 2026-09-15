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
- This site has no accounts, no database and stores no user content, so most
  classic web vulnerabilities don't apply. Things we *do* care about: anything
  that lets the backend API be abused through this front end, cache poisoning,
  SSRF via the API client, XSS through catalogue data, and anything in the
  Open Graph image renderer.
