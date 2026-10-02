# Explicit private observing synchronization

Guest equipment, sites, lists and journals continue to live in browser storage.
Signing in does not upload or download them. The server API provides an optional
private copy through explicit operations at `/account/observing-backup`.
No background synchronization or automatic merging is implemented.

## Account and request boundary

The session-authenticated endpoint is `/account/observing-workspace` with named
routes `observing.sync.show`, `observing.sync.update` and
`observing.sync.destroy`. Every request must send `X-Observing-Account` equal to
`ObservingAccountScope::forUser($user)` from the authenticated page. This opaque
purpose-specific HMAC binds a preview/action to the account that rendered it;
it is not authentication or a replacement for CSRF. A session account switch,
missing scope or mismatched scope returns `409 {"error":"account_changed"}`.
The client must discard private previews and reload the account page.

PUT and DELETE require `Content-Type: application/json` and the normal session
`X-CSRF-TOKEN`. Query parameters are rejected. All success, authentication,
validation, CSRF and method-error responses are JSON with `Cache-Control:
private, no-store` and `Referrer-Policy: no-referrer`. Read/write throttles are
30/10 per minute. Payloads and validation errors are never flashed into session
input or deliberately logged by this feature.

The path-specific global middleware precedes Laravel's TrimStrings and
ConvertEmptyStringsToNull. It reads at most 1.5 MiB plus one byte before decoding,
with a JSON depth limit of 32. This bounds application reading/decoding; the
web server or PHP may already have buffered transport bytes. Configure upstream
request-size limits as well when deploying. Ordinary form bodies are rejected.
Private JSON bypasses Laravel's string/null transforms, which would otherwise
change journal text and optional values.

## Contract and conflicts

GET returns `{revision, state, payload, accountScope}`. A never-synced account
has revision 0, state `empty`, and null payload. A saved copy has state `saved`
and the complete payload `{equipmentWorkspace, journal}`. Equipment workspace
must use schema 2; journal uses schema 1. Historical schema-1 setup snapshots
inside observations normalize to schema 2 with an unknown horizon.

PUT accepts `{expectedRevision, payload}` and returns
`{revision: expectedRevision + 1, state: "saved"}`. DELETE accepts only
`{expectedRevision}` and returns the next revision with state `deleted`.
Every mutation requires the revision obtained for the explicitly previewed
account copy. Stale revisions return `409 {"error":"revision_conflict"}`;
no private payload is included in that error. The client must offer a fresh
explicit preview, not retry an upload against a new revision automatically.

Deletion erases the encrypted payload and retains a small owner/revision
tombstone. Deleting an empty account creates revision 1. An older tab's initial
revision-0 upload therefore cannot resurrect deleted data. A later intentional
upload is allowed only with the current tombstone revision. Account deletion
cascades to remove both saved data and tombstones.

The database uses a unique owner key and a transactional conditional update
(`WHERE user_id = owner AND revision = expected`) to increment the revision.
There is no separate unguarded read-then-save. SQLite is exercised locally;
the migration/query use Laravel's portable primitives for supported production
databases, which have not been exercised against a live service here.

Generic error codes are `invalid_document` (422), `request_too_large` (413),
`json_required` (415), `authentication_required` (401), `session_expired` (419),
`too_many_requests` (429), `storage_unavailable` (503), and `request_failed`
for other framework failures. Invalid uploads never replace an existing copy.

## Schema, encryption and recovery

Server validation checks complete field sets, actual JSON numbers, UUIDs,
referential integrity, entry counts, real UTC instants, recognized IANA zones,
horizon/camera bounds and journal choices. It mirrors JavaScript UTF-16 text
lengths and ECMAScript trimming, including preserving U+0085. Per-document
canonical limits remain 256 KiB for workspace and 1 MiB for journal. Shared
fixtures run through both languages, covering emoji limits, whitespace, legacy
snapshots, aliases, malformed dates, camera dimensions and horizon seam data.
PHP preserves recognized IANA aliases rather than inventing a new timezone;
the browser may normalize an equivalent alias through its ICU version.

Payloads use Laravel's encrypted array cast in a long-text column; plaintext
does not appear in the database payload column or model serialization. This is
server-side encryption, not end-to-end encryption: the running application can
decrypt authenticated users' copies. Keep `APP_KEY` and encrypted backups
recoverable together. Do not rotate/discard keys without a tested migration.
Decryption or stored-schema failure returns generic `storage_unavailable`
without destroying the stored ciphertext. Export recovery and key restoration
must precede any repair. The app has not run this migration on production.

Guest-browser data remains independent of account deletion or server-copy
deletion. Restoring server data to a browser should explicitly warn that the
shared guest workspace will be replaced, perform local stale checks, and clear
all account previews on navigation/logout/account changes. Do not persist
private account previews or account payloads in unscoped browser storage.

## Browser transfer workflow

The authenticated page initially loads no account payload and reads no guest
records. Users explicitly preview each side, then select a consent checkbox for
the desired transfer. Uploads compare the captured guest keys and use the account
revision. Restores recheck the authenticated account/revision before replacing
guest keys and clear active-site selection without changing `observer_location`.
Account payloads exist only in page memory; navigation/pagehide clears them and
aborts pending requests. Restored back/forward pages reload their authenticated
document. A new account cannot reuse the prior page's opaque scope token.

Local storage has no multi-key transaction or atomic compare-and-swap. The restore
checks both captured keys before starting, checks each again before writing, and
conditionally rolls back earlier writes after failure. It preserves detected
interleaved changes and reports incomplete recovery. Another process can still
write between a check and a write; export a backup and avoid concurrent editing
during replacement. Server revisions provide the stronger database-side guard.

Responses are read through a decoded stream capped at 1.5 MiB, with a 20-second
client timeout. Uncertain network/mutation outcomes discard the revision and
require an explicit fresh account check; they are never retried automatically.
The API emits unescaped Unicode so valid canonical payloads fit the transport
bound. Private fields are rendered as counts rather than inserted HTML.
