# ActiveSync TODO

Last reviewed: 2026-07-14

This file tracks **remaining** work. For what the library already supports,
see the doc index in the package `README.md` — in particular
[`protocol-versions.md`](protocol-versions.md) (versions, commands, per-version
behaviour) and [`configuration.md`](configuration.md) (deployment setup).

Items are grouped by intent. The **Horde 6** section is a breaking-change
roadmap — do not implement those entries piecemeal on the FRAMEWORK_6_0 /
3.x line without an explicit migration plan.


## Deferred (low priority or no known client)

- **ItemOperations `Schema` requests**

  The decoder skips `ItemOperations:Schema` subtrees without acting on them.
  No client in active use is known to require schema-based fetches. Revisit if
  a device surfaces a concrete failure.

- **HTTP 503 throttling (`X-MS-Throttle`)**

  Exchange-style overload signalling is not implemented. Only relevant at high
  load or when deliberately rate-limiting devices.

  See [Microsoft throttling guidance](https://learn.microsoft.com/en-us/previous-versions/office/developer/exchange-server-interoperability-guidance/jj899829(v=exchg.140)).

- **Task `Regenerate=1` (Outlook regenerating tasks)**

  Not supported — Nag uses fixed RRULE + `completions[]`, not post-completion
  regenerated due dates. Phase 0 (2026-06-23) saw `Regenerate=0` only on
  Outlook weekly-series traffic. Documented in `doc/protocol-versions.md`
  (Tasks section).

## Near-term reliability (FRAMEWORK_6_0)

- **Sync response streaming ([#83](https://github.com/horde/ActiveSync/issues/83))
  — implemented 2026-07-14, awaiting client validation**

  Streams Sync WBXML incrementally (chunked HTTP for `Cmd=Sync` only) so
  clients with ~30s read timeouts (Gmail Android) receive body bytes while
  work continues. Covers both directions: per-message flush on export, and
  — since the 2026-07-14 reporter test exposed a 55.7s silent
  `FullDraftsUpSync` import phase — deferred import of client-sent commands
  during response output with WBXML keep-alive tokens
  (`Encoder::keepAlive()`) flushed between imports. Behaviour, config keys
  (`streaming`, `maxmessagesperresponse`, `maxmessagetime`,
  `maxrequestduration`, legacy `maxresponsetime`), error model, and operator
  notes are documented in [`sync-streaming.md`](sync-streaming.md). Companion
  changes live in `horde/rpc` (`Horde_Rpc_ActiveSync` streaming path) and
  `horde/horde` (`rpc.php` config passthrough, `conf.xml` keys). Motivated
  by [#77](https://github.com/horde/ActiveSync/issues/77).

  **Remaining before #83 can close:**

  - [x] Author deployment soak (streaming on): iOS Mail account re-add /
    fresh mail sync clean (2026-07-14).
  - [x] Reporter test round 1 (2026-07-14): export-path streaming confirmed
    working (stalled Inbox catch-up completed immediately); found the
    up-sync gap (Drafts import before first byte), fixed via deferred
    import + keep-alives.
  - [ ] Gmail Android re-validation of the up-sync path (reporter, feature
    branch) — includes keep-alive token tolerance of Gmail's WBXML parser.
  - [ ] Flip `streaming` default to `true` in `conf.xml` after validation.
  - [ ] Move this entry to **Recently completed** when #83 closes.

  Partial step toward “Request / response pipeline” and “Changes object”
  below; not a substitute for the full Horde 6 response-object refactor.

## Operations and monitoring (out of library scope)

- **EAS usage dashboard / “top-like” monitor**

  A live view of active devices, error rates, and stuck sync keys would be
  valuable for operators but belongs in a separate admin tool or Horde UI
  module, not in the protocol library. Per-device protocol logging
  (`logging.type = perdevice` in Horde config) is the supported debugging
  path today.

## Horde 6 (breaking changes — planned refactor)

Do not start these ad hoc. Each item touches public API surface, persisted
state, or both.

### Architectural direction (review note, 2026-06-24)

Larger refactor work should land together with these baseline changes rather
than as isolated class extractions:

- Replace `horde/controller` (`Horde_Controller_Request_Http`) with
  `horde/http` request and response objects.
- PSR-4 class names throughout (`Horde\ActiveSync\...`) instead of
  underscore-separated `Horde_ActiveSync_*`.
- PSR-3 logging via `Horde\Log\Logger` instead of `Horde_Log_Logger`.
- Move from inheritance-based extension (subclass `Driver_Base`,
  `Connector`, etc.) to pluggable subsystem implementations behind
  interfaces, so backends compose collaborators instead of overriding
  protected methods.

The existing entries below predate this framing and should be folded into it
when a migration plan is written.

- Horde\ActiveSync owns the backend/repository interfaces and null implementations.
- On a case-by-case basis, Horde\ActiveSync also owns default implementations as makes sense. These are marked final and reusable code is exposed as traits.
- SQL, Mongo or other backends are owned by Horde\Core
- Library owns a generic AuthBackendInterface, Core owns an implementation which ties into Horde Auth.
- Certificate validation becomes a validator interface owned by
  Horde\ActiveSync; Core owns the implementation and its configuration.
  Today `Request_ValidateCert` performs the OpenSSL purpose/trust checks
  (and the CRL/chain TODOs) inline in the request handler, which is the
  wrong layer for that concern (review note on
  [horde/ActiveSync#74](https://github.com/horde/ActiveSync/pull/74),
  2026-07-02).
- Interaction with orthogonal subsystems happens via PSR Events.

### Protocol and class layout

- Consolidate non-protocol constants into a dedicated class.
- Rename WBXML tag constants to match MS-AS* document names (today many follow
  legacy Z-Push naming).
- Decouple WBXML codepage tables from `Horde_ActiveSync_Wbxml_Encoder` /
  `Decoder` into separate classes.
- Add field-definition metadata (e.g. maximum encoded size) on message maps.
- Introduce `Horde_ActiveSync_Protocol_Exception` and tighten the exception
  hierarchy.

### Request / response pipeline

- Split request parsing from handling (`Request_Parser` +
  `Request_Handler`); stream large inbound bodies instead of buffering.
- Replace `Horde_Controller_Request_Http` with a library-local HTTP request
  object; add a matching response object and move header logic out of
  `Horde_Rpc_ActiveSync`.
- **Interim (FRAMEWORK_6_0):** Sync-only chunked streaming in
  `Horde_Rpc_ActiveSync` ([#83](https://github.com/horde/ActiveSync/issues/83));
  full `horde/http` response object remains Horde 6.
- Move non-server helpers out of `Horde_ActiveSync` (truncation helpers,
  version negotiation utilities, etc.).
- Leverage horde/version

### State, storage, and identity

- Rename `Horde_ActiveSync_State_*` to `Horde_ActiveSync_Storage_*` (or
  extract a storage layer) — these classes already manage more than sync keys.
- Return `Horde_ActiveSync_Device` objects from `listDevices()` instead
  of SQL field hashes; accept device property names in filters.
- Eliminate static `Horde_ActiveSync::$_*` properties (logger, device,
  version).
- Unify `serverid` vs backend folder names: one map, server IDs everywhere
  inside the library; backend IDs only at the driver boundary.
- Fold `SyncCache` and per-collection state into the device object where
  practical.
- Implement `Horde_ActiveSync_SyncKey` as a first-class type.

### Driver and backend shape

- Repository (or similar) per collection class instead of monolithic
  `Horde_ActiveSync_Driver_Base` / `Horde_Core_ActiveSync_Connector`
  switch statements.
- Normalize folder create/edit/delete to accept and return
  `Horde_ActiveSync_Message_Folder` objects consistently. Multiplexed
  non-email folders (`Calendar:ID`, `Tasks:ID`, …) already work, but
  method signatures and return shapes still vary.
- Store `Horde_ActiveSync::CLASS_*` and `FOLDER_TYPE_*` in persisted state
  to stop converting between them at runtime.
- Consolidate folder UID ↔ backend ID mapping (today split across
  `Horde_ActiveSync_Collections` and the driver).
- **Folder UID map vs volatile folder cache (long-term)**

  When the folder hierarchy changes (e.g. multiplexed Turba address books
  added/removed from ActiveSync), clients may issue concurrent EAS sessions.
  `FolderSync` with `synckey=0` clears the per-device folder cache
  (`State/Base.php` → `_resetDeviceState()` → `SyncCache::clearFolders()`).
  The backend-id → EAS-folder-UID map lives in that cache
  (`getFolderUidToBackendIdMap()`), so concurrent rebuilds can race on an
  empty map while `FolderSync` is still exporting.

  #81 (2026-07-09) mitigates this with deterministic UID generation when the
  map is empty; see **Recently completed**. The structural issue remains:
  folder identity should not depend on a cache that is wiped mid-rebuild.

  **Long-term direction:**

  1. **Persist UID assignments separately** — e.g. `(device_id, user,
     backend_serverid)` → `eas_folder_uid`, updated on first assignment and
     rename (`old_id` in `_getFolderUidForBackendId()`), not cleared by
     `clearFolders()`. Hierarchy diff drives FolderSync Add/Update/Remove;
     drop map entries only when the backend folder is gone.

  2. **Or: atomic folder-cache rebuild** — build the new hierarchy in a
     staging structure, swap in one `SyncCache::save()`; no empty-map window
     visible to parallel SYNC/PING/FolderSync requests.

  3. **Fold into Horde 6 storage refactor** — overlaps “Unify serverid vs
     backend folder names” and “Fold SyncCache into device object” above.

  **Key code paths:** `Driver/Base.php` (`_getFolderUidForBackendId`,
  `_tempMap`), `State/Base.php` (`getFolderUidToBackendIdMap`, `loadState`
  synckey `0`), `SyncCache.php` (`clearFolders`, `updateFolder`),
  `Collections.php` (`getBackendIdForFolderUid`, `initHierarchySync`, `save`),
  `Request/FolderSync.php`.

  **Done when:** concurrent `FolderSync` `synckey=0` after a hierarchy
  change yields one consistent saved UID map; devices converge without account
  removal; renames preserve UID. Do not drop #81 deterministic generation
  until (1) or (2) is in place.

  **After structural fix:** Prefer switching back to opaque (e.g. random)
  UIDs for *new* map entries. Deterministic derivation was only needed so
  parallel rebuilds agreed while the map was empty; a persisted map assigns
  each backend folder once and removes that race. Opaque IDs also avoid
  leaking backend folder identity via a predictable `crc32(prefix:id)` scheme.
- Pass `FILTERTYPE_*` to the driver by constant, not precomputed cutoff
  timestamps (supersedes the near-term `INCOMPLETETASKS` fix style).
- Split `getMessage()` into per-class methods with shared base logic.
- `Horde_ActiveSync_Change_Filter` (or equivalent) for client-specific
  workarounds. Some MOVEITEMS duplication issues were fixed in the past, but
  there is no general filter framework.
- Move mail send/forward/reply helpers from `Horde_Core_ActiveSync_Mail` into
  the activesync package behind injectable mailer/identity dependencies.

### Sync data structures

- `Horde_ActiveSync_Sync_Options` (and similar) for collection options /
  body preferences instead of raw arrays.
- Collection object replacing the associative collection array in `Sync.php`.
- Changes object (array, `SplFixedArray`, or temp stream) to cap memory on
  large initial mailbox syncs and to unify the `add` / `modify` / `delete`
  shapes between email and PIM collections. Streaming v1 ([#83](https://github.com/horde/ActiveSync/issues/83))
  uses count-based `maxmessagesperresponse` and per-message flush instead; a
  proper Changes object can follow in Horde 6.
- Sync-reply objects per collection type; move logic out of
  `Horde_ActiveSync_Connector_Exporter_Sync`.
- Configuration builder for server/driver construction (Ping-related settings
  are no longer Ping-only).
- **Unify batch-size vectors:** `maximumwindowsize` (client WindowSize
  override, ping settings) and `maxmessagesperresponse` (streaming batch
  cap, #83) both feed the same export-loop bound via `min()`. They stay
  separate on FRAMEWORK_6_0 for rollback semantics (streaming cap must not
  leak into buffered mode), but the Sync options / configuration-builder
  refactor should collapse them into one batching policy.

### SMS

- Today SMS is deliberately stubbed: imports return `IGNORESMS_*` phantom
  UIDs so broken clients do not break email sync. Horde 6 should pass SMS
  collections to the backend and let it opt in/out instead of hard-coding
  ignores in `Horde_ActiveSync_Connector_Importer`.

### Miscellaneous

- Single logger access point (`Horde_ActiveSync_Debug` or similar) instead of
  injecting `Horde_Log_Logger` everywhere.
- `Horde_ActiveSync_Message_Date` (or shared date helper) for POOM date
  normalisation currently scattered in message classes.
- Device class hierarchy instead of one `Horde_ActiveSync_Device` with all
  properties.

## Recently completed

Verified in the 3.x / FRAMEWORK_6_0 tree as of 2026-06. Removed from the
active backlog; kept here so this file does not resurrect settled work.

### Protocol versions and commands

- EAS **16.0** and **16.1** as supported ceilings (`VERSION_SIXTEEN`,
  `VERSION_SIXTEENONE`).
- EAS 16.0 **Find** command with mailbox/GAL search
  (`Horde_ActiveSync_Request_Find`).
- **Find KQL parser** (`Horde_ActiveSync_Find_Kql`) — boolean operators,
  parentheses, implicit `AND`, and common Outlook property restrictions
  (`from`/`to`/`cc`/`bcc`/`subject`/`body`/`participants`,
  `category`, `hasattachment`, `isread`/`isflagged`, `importance`,
  `received`/`sent` dates, `size`) mapped to IMAP search. Full Exchange
  KQL (`NEAR`/proximity, wildcards, folder/conversation scoping, uncommon
  MS-ASCMD properties) is out of scope for the IMAP-backed implementation.
- **Autodiscover**, **ItemOperations** (fetch/move/empty; not Schema),
  **Settings**, **Provision**, **Ping**, **Search**, **ValidateCert** — all
  present for supported versions (see `doc/protocol-versions.md`).

### EAS 16.0 calendar (library + `horde/kronolith` + `horde/core`)

- Instance model: bound exceptions as top-level items with `InstanceId`;
  modified instances omitted from master `Exceptions` at 16.0+.
- `ClientUid` import/export round-trip.
- `AirSyncBase:Location` import/export (display name + coordinates).
- Inbound appointment validation strips forbidden fields instead of rejecting.
- Initial calendar sync hides bound exceptions only for protocol versions
  below 16.0.

### EAS 16.0 mail (`horde/core`)

- Draft folder content changes use `CHANGE_TYPE_DRAFT`.
- Draft send via `POOMMAIL2:Send` (`toRfc822Stream()` + SMTP).
- `Forwardee` objects on SmartForward/SmartReply.

### EAS 16.1 (library + `horde/core` + `horde/kronolith` + `horde/itip` + `horde/imp`)

- `MeetingResponse` `ProposedStartTime` / `ProposedEndTime` with RFC5546
  `METHOD=COUNTER` / `DECLINECOUNTER`.
- Attendee proposed times stored and exported on calendar sync.
- `DisallowNewTimeProposal` export from Kronolith (iCal `DISALLOW-COUNTER`).
- `Provision:AccountOnlyRemoteWipe` with admin and prefs UI.

### Calendar invitations (iTIP mail + ActiveSync) (`horde/activesync` +
`horde/kronolith` + `horde/itip` + `horde/imp`)

- Recurring meeting requests in mail: `MeetingRequest` exports
  `instancetype` and `MeetingRequestRecurrence` from embedded iCal RRULE /
  `RECURRENCE-ID` data.
- Outbound invitation MIME simplified to `multipart/alternative` (plain,
  HTML, inline `text/calendar`) via `Horde\Itip\Generator\MimeEnvelopeBuilder`.
- IMP shows iTip RSVP UI above the HTML notification body for invitation mail.

### Multi-folder PIM

- Multiplexed calendar, contact, task, and note folders
  (`Class:backendId` server IDs, device `multiplex` flag).

### Task recurrence (`horde/nag` + ActiveSync)

- Single-instance completion: `DEADOCUR` / `Complete` / master due advance →
  Nag `completions[]` (`fromASTask` / `import` merge).
- Export: next due via `getNextDue()`; `seriesIsFullyComplete()` for
  `complete` flag.
- PING `StateGone` recovery for stale collection synckeys during long poll.
- **Not supported:** `POOMTASKS:Regenerate=1` (documented limitation).

### Stability (3.0.0-RC1 and related)

- Deterministic EAS folder UIDs on cache rebuild (#81, 2026-07-09); see
  “Folder UID map vs volatile folder cache” for the remaining structural work.
- `FILTERTYPE_INCOMPLETETASKS`: pass FilterType to the driver; incomplete-only
  task sync in `horde/core` / `horde/nag`; no longer encode filter `8` as a
  Unix cutoff (avoids mail/calendar misconfiguration).
- `FOLDERSYNC_REQUIRED` loop guard: per-device counter in sync cache stops
  returning status `12` (SYNC) / `7` (PING) after five ignored responses;
  escalates to KEYMISMATCH / server error so broken clients can recover.
- SQL/Mongo row locks for parallel state access.
- Reject and repair corrupt `sync_data` on load/save.
- Separate PING watermark from SYNC modseq (iOS mail loop fix).
- Hardened initial-sync ACK and KEYMISMATCH on corrupt state.
- Meeting invitation RSVP / `MeetingResponse` improvements.

### Older but still relevant

- EAS 16 draft **sync** (create/edit drafts on device) since 2.36.0.
- MOVEITEMS duplicate-message fix.
- Multiple SYNC/PING/OPTIONS loop fixes for broken clients (see
  `doc/changelog.yml`).
