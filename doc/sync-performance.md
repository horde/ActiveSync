# Email sync and polling performance

Design document for the heartbeat/PING polling and export performance work.
Internals context is in [`architecture.md`](architecture.md); the streamed
`Sync` delivery design is a separate document
([`sync-streaming.md`](sync-streaming.md)).

Tracking issue:
[horde/ActiveSync#88](https://github.com/horde/ActiveSync/issues/88).

## The problem

EAS clients keep one hanging PING (or looping SYNC) request per account
open; the server polls all pinged collections in a loop until the heartbeat
expires or a change is found. Before this work, **every poll iteration**
performed, **for every pinged collection**:

- a full sync-state load: collection lock (its own transaction),
  state garbage collection, a `SELECT … FOR UPDATE` row lock, and blob
  unserialization — followed by lock release;
- one IMAP `STATUS` round trip per email folder;
- a `SELECT count(*)` plus a full-blob `UPDATE` whenever the SyncCache was
  saved, with the entire serialized cache written to the debug log.

A device pinging a few dozen mail folders therefore generated hundreds of
SQL statements and IMAP round trips per minute in the steady state — with
**zero changes to report**. A plain IMAP client covers the same "anything
new?" question with one round trip per poll. Message export added an
`N+1` pattern on top: one IMAP fetch sequence per exported message.

## Design principles

Robustness and architectural clarity take precedence over raw speed:

1. **Freshness invariant.** Every poll iteration compares against fresh
   server data. Caches hold *our own state watermarks*, never the server's
   answer: changes made by other clients (webmail, other devices, direct
   IMAP) are always detected. Prefetched IMAP status is consumed exactly
   once per iteration.
2. **Optimizations are hints with fallbacks.** Every new backend method is
   optional and defaults to a no-op / empty result; every prefetch miss or
   bulk failure falls back to the previous per-item code path with its
   exact error semantics. Disabling any single optimization restores the
   old behaviour.
3. **Explicit read-only vs. mutating paths.** State loads that only peek
   (change polling) are now declared as such, instead of taking the same
   locks as loads that precede a persisted mutation.

## Optimization 1 — batched IMAP STATUS prefetch

`Collections::pollForChanges()` collects the backend folder ids of all
pinged email collections once per iteration and calls
`Driver_Base::prefetchFolderStatus($folders)` — a **no-op by default**.
`Horde_Core_ActiveSync_Driver` implements it via
`Imap_Adapter::prefetchStatus()`, which issues **one** `STATUS` request for
all mailboxes (a single LIST-STATUS round trip, RFC 5819, on servers that
advertise it) with the same `FORCE_REFRESH` semantics as the per-mailbox
call.

`Imap_Adapter::ping()` consumes the prefetched entry for its folder —
**consume-once**: the entry is removed on use, so the next iteration must
prefetch again and can never act on stale data. Missing or incomplete
entries (folder vanished, prefetch failure, no LIST-STATUS support) fall
back to the existing per-mailbox `STATUS` call, including its
`FolderGone` detection.

Effect: N STATUS round trips per iteration become 1.

## Optimization 2 — read-only state loads in the poll loop

`State_Base::loadState()` accepts `['readonly' => true]`;
`Collections::initCollectionState()` passes it from the poll loop (and only
from there). A read-only load:

- takes **no collection lock** — a polling PING can no longer block or be
  blocked by a parallel SYNC of the same folder;
- runs **no state garbage collection** (GC belongs to mutating SYNC
  loads);
- in `State_Sql`, replaces the `SELECT … FOR UPDATE` row lock with a plain
  `SELECT`, and **memoizes** the decoded row per folder, keyed by synckey.

Repeat iterations with an unchanged synckey are served from the memo with
**zero SQL**. The folder object is rehydrated from the raw serialized blob
on every load, so each iteration still starts from a pristine copy —
in-memory mutations of a previous iteration cannot leak (same semantics as
a database re-read).

Memo invalidation:

- any **mutating** load of the folder (a real SYNC in the same request),
- any state save for the folder (including the PING-positive
  `save(['preservePending' => true])` checkpoint),
- a **synckey change** — a parallel SYNC advancing the state causes a memo
  miss and a fresh database read (`Collections::updateCollectionsFromCache()`
  refreshes synckeys every iteration, so the new key is seen promptly).

Writes on the poll path remain safe without locks: the PING watermark
checkpoint runs in its own transaction (`_saveSyncStateRow()`), and
`updateSyncStamp()` uses an optimistic `WHERE sync_mod = <old>` update.
The worst interleaving with a parallel writer is a lost watermark advance,
which produces one redundant positive PING (an extra empty SYNC round
trip) — never a missed or duplicated change.

`State_Mongo` inherits the base behaviour (no collection lock, no GC on
read-only loads) but does not add memoization; its plain document reads
were already lock-free.

Effect: ~6–8 SQL statements per collection per iteration become 0 after
the first iteration.

## Optimization 3 — dirty-field SyncCache saves (SQL)

`State_Sql::saveSyncCache()` previously ignored the `$dirty` parameter,
ran `SELECT count(*)` before every save, overwrote the whole blob with the
caller's in-memory copy, and logged the entire serialized cache. Now it:

- **honors `$dirty`** with the same semantics as the Mongo backend: only
  dirty properties are merged into the *currently stored* cache
  (`_mergeDirtySyncCache()`), with per-collection granularity for the
  `collections` property (including removals). A PING heartbeat updating
  `lasthbsyncstarted` no longer clobbers collection entries a parallel
  SYNC wrote in the meantime — the SQL backend's last-write-wins race is
  narrowed to genuinely conflicting fields;
- reads the stored blob in the same query that previously only counted
  rows (no extra query), inserts the full cache when no row exists, and
  **skips the write entirely** when nothing is dirty;
- logs the dirty property names and sizes instead of the full blob;
- falls back to a full replace when the stored blob is corrupt
  (self-healing, as before).

Dirty tracking in `SyncCache` is the contract this relies on; a missing
`bodypartprefs` dirty mark was fixed as part of this work.

## Optimization 4 — batched message export

`Connector_Exporter_Sync` previously fetched every exported message
individually via `Driver::getMessage()` — for mail, one IMAP
envelope/structure fetch sequence per message. Now it prefetches up to
`PREFETCH_BATCH_SIZE` (10) upcoming `CHANGE`/`DRAFT` ids (including the
bare-uid change lists of an initial sync) through
`Driver_Base::getMessagesBulk()`:

- the default implementation returns an **empty array** (no bulk
  support) — such backends keep the exact previous per-message behaviour;
- `Horde_Core_ActiveSync_Driver` implements it for email folders with a
  single `Imap_Adapter::getMessages()` call (one combined
  envelope/structure fetch for the batch; body fetches remain per message,
  see *Deferred work*), applying the same post-processing as
  `getMessage()` (`_postProcessMailMessage()`: last-verb lookup, draft
  flag, iTip response import);
- any id missing from the bulk result — expunged messages, bulk errors,
  unsupported backends — **falls back to a single `getMessage()` call**,
  preserving the per-message error semantics the exporter's error handling
  depends on (`Horde_Exception_NotFound` → drop and continue;
  `TemporaryFailure` → abort request; other errors → keep batch in
  `sync_pending`). Each prefetch window is attempted once; misses within a
  window do not re-trigger the bulk call.

The batch is bounded (10 messages, bodies truncated per the client's body
preferences), so peak memory stays small.

## Backend contract summary

Two optional driver methods were added to `Horde_ActiveSync_Driver_Base`;
both are pure optimization hints and both default to "not supported":

| Method | Default | Purpose |
|--------|---------|---------|
| `prefetchFolderStatus(array $folders)` | no-op | About-to-poll hint; batch folder status in one backend round trip |
| `getMessagesBulk($folderid, array $ids, array $collection)` | `[]` | Fetch many messages at once; callers fall back per id |

`State_Base::loadState()` gained the optional `$options` parameter
(`readonly`). `Imap_Adapter::getMessages()` gained the `uid_keys` option
(return array keyed by IMAP uid).

## Observability

In the per-device logs — meta (debug) level unless marked otherwise:

- `Prefetched status for N of M mailboxes.` — batched STATUS worked;
  N < M means some folders fell back per mailbox.
- `Unable to prefetch mailbox status, falling back to per-mailbox STATUS: …`
  (notice) — LIST-STATUS/batch failure; polling continues on the old path.
- `STATE: Updating SYNC_CACHE fields [timestamp,collections] for user …` —
  dirty-field save (replaces the former full-blob dump).
- `STATE: No dirty SYNC_CACHE fields …, skipping save.` — write avoided.
- `Bulk message prefetch failed, falling back to single fetches: …`
  (notice) — exporter degraded to per-message fetches.

## Testing

- `test/unit/Horde/ActiveSync/ImapAdapterTest.php` — prefetch consumed by
  `ping()` in one round trip, consume-once semantics, per-mailbox fallback
  on batch failure.
- `test/unit/Horde/ActiveSync/StateTest/Sql/ReadonlyLoadStateTest.php` —
  read-only loads take no locks, memo hit needs no SQL, mutating loads and
  synckey changes invalidate the memo (SQLite-backed).
- `test/unit/Horde/ActiveSync/StateTest/Sql/SyncCacheSaveTest.php` —
  dirty-field merge preserves concurrent writers' changes, per-collection
  removal, whole-property replace, skip-when-clean (SQLite-backed).
- `test/unit/Horde/ActiveSync/Connector/ExporterPrefetchTest.php` — bulk
  consumption, per-id fallback, no-bulk-support equivalence, initial-sync
  bare-uid lists.

## Deferred work

Deliberately **not** done on FRAMEWORK_6_0; candidates for the Horde 6
storage refactor ([`todo.md`](todo.md)):

- Replacing the PHP `serialize()` blobs in `sync_data` / `cache_data` with
  a versioned, cheaper format.
- Dedicated columns (or tables) for hot SyncCache fields (`timestamp`,
  `lasthbsyncstarted`, per-collection synckeys) to make partial reads and
  compare-and-swap writes natural instead of blob merges.
- Merging the per-message envelope/structure and body FETCHes in
  `Imap_EasMessageBuilder` / `Imap_MessageBodyData` into one IMAP command
  per batch — high risk in the message-building hot path, low benefit
  until the fetches above dominate again.
