# Education, plugin and feedback acceptance

Development branch: `codex/education-plugin-feedback`, isolated from other agents' worktrees. Local preview uses synthetic credentials, file sessions, a loopback-only unavailable catalogue and a non-delivering mail transport. No production data, email, domain or WAF changes.

## Automated checks

- Full PHP regression suite: 1,391 tests passed. The feedback suite passes 25 tests / 151 assertions.
- JavaScript: 249 tests passed.
- Pint and Larastan pass (local Larastan requires `--memory-limit=512M`).
- Vite production build passes; existing optional galaxy-renderer size warning remains.
- Release draft renders successfully. New notes remain unpublished.
- Feedback coverage includes every category, optional reply address, fixed recipient, validation, bounded drafts, honeypot, CSRF, private error responses, rate limiting independent of observing routes, delivery failures and effective mail transport URL/legacy overrides.

## Browser review

Chrome on 2026-10-04, normal desktop viewport and a temporary 390 × 844 mobile viewport (reset afterwards):

- Plugin, school resources, university activities and feedback render correctly. Mobile document width equals viewport width (390 px) on all four pages.
- Plugin subsection navigation reaches `#codex`, with its heading at 96 px beneath the sticky header.
- University activity opens its dedicated worksheet, with student instructions, writing space, teacher notes and references visible. Native print shortcut did not expose a print preview through automation; actual browser pagination/physical print remains unverified. Print CSS and the teacher-page break are covered by tests.
- Feedback correctly shows its email alternative and disabled submit button for the intentionally non-delivering local mail setup. Sending, success and failure behavior are exercised with isolated mail fakes, not live messages.
- Poster section shows an honest preparation message while the owner locates the original vector assets. No placeholder download URLs are published.

Screenshots are the adjacent JPEG files. Local endpoint URLs in screenshots are intentional isolated-preview settings.

## Independent review

Two agents reviewed source accuracy and integration. Fixed effective MAIL_URL/legacy transport overrides that could have selected logging, and preserved literal bug-report snippets in the plain-text mail body. Root also isolated the feedback throttle key from other route limits. No unresolved code blockers reported.

## Release follow-up

Provide the poster originals and credits, confirm the configured mail transport reaches hello@publicuniverse.net in an authorized deployment test, and verify native worksheet print preview. Keep `PLUGIN_CONNECTION_READY=false` until the separately planned domain/WAF work and client connection checks are complete. Domain cutover and deployment are outside this PR.
