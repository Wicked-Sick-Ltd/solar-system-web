# Correcting a guest observing journal

List renaming and observation correction are local, explicit actions. They use
journal schema 1 and its existing bounds; no account/API request or automatic
backup upload occurs. A correction is an update to the existing record, not an
additional observation or an inferred observation from a plan.

The observation editor changes only `observedAtUtc`, `timezone`, `outcome` and
`notes`. The record UUID, original catalogue reference/label and recorded
`equipmentAndSite` snapshot are retained. Current workspace profiles are not read
while correcting history: even deleted or unreadable current profiles cannot
silently replace the saved historical setup. This slice intentionally offers no
snapshot or target replacement operation.

Times use the existing exact `YYYY-MM-DDTHH:MM:SSZ` UTC format and real-calendar
roundtrip validation. The IANA timezone label does not convert the UTC value.
Text, allowed outcomes, document size and all other schema limits are checked by
the same complete-document validator used for creation/import/synchronization.

Opening an editor writes nothing. Save or cancel it before mutating another
record, importing or reloading, so a render cannot silently discard entered
corrections. Opening it invalidates an earlier pending file read. Failed quota,
validation and stale-tab saves leave the original storage and pending inputs
intact. Save first persists successfully, then closes the editor and returns
focus to the updated record's correction button. Cancel changes no saved data.
The existing one-level undo restores the prior record after a successful
correction; subsequent mutations or reload follow the existing undo lifetime.

JSON/CSV exports and printing describe saved records, excluding the open editor.
Navigation does not autosave a pending correction. The editor is hidden when
printing, and fields remain disabled before initialization and after disposal.
Private journal fields are not Livewire properties or native POST form data.

## Original-storage recovery copy

The existing normal JSON/CSV export requires a readable supported journal. For
corrupt or unsupported data, **Download original journal storage** now copies the
current journal key without validation, repair, replacement or deletion. Its
filename is `public-universe-journal-recovery.json`, containing exactly:

```json
{"storageKey":"public_universe_journal_v1","originalValue":"the original stored string"}
```

The original value is wrapped as a JSON string so even unpaired UTF-16 code units
survive a UTF-8 file roundtrip. This envelope is deliberately not accepted as a
normal journal import. It preserves data for manual recovery; it neither claims
that recovery is possible nor provides an automatic repair/reset workflow.

Unlike ordinary exports, recovery copies are **not redacted**. They can contain
all private site names, coordinates, notes and unknown fields. The interface
states this before the action. No content is uploaded or displayed as HTML.
If browser storage cannot be read, the action reports failure and writes nothing.
The separate equipment workspace and observer-location key are untouched.

Regression coverage includes stable identities/snapshots, cancellation/undo,
invalid UTC dates and zones, oversized notes, denied writes, stale tabs, pending
import races, literal output and exact corrupt-storage recovery. Real browser
acceptance is reported separately; no physical-touch result follows from these
isolated DOM tests.
