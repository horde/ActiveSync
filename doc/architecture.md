# Architecture (ActiveSync developers)

How the library works internally. Read this before changing protocol logic.
Companion documents: [`protocol-versions.md`](protocol-versions.md) for
per-version behaviour, [`sync-streaming.md`](sync-streaming.md) for the
streamed `Sync` delivery design, and [`todo.md`](todo.md) for the Horde 6
refactor roadmap.

## Component map

| Component | Classes | Role |
|-----------|---------|------|
| Server front door | `Horde_ActiveSync` | Auth, version negotiation, device handling, provisioning enforcement, request dispatch; owns protocol constants |
| Request handlers | `Horde_ActiveSync_Request_*` | One class per EAS command (`Sync`, `FolderSync`, `Ping`, `ItemOperations`, …), all extending `Request_Base` |
| WBXML layer | `Horde_ActiveSync_Wbxml_Encoder` / `_Decoder` | Token-level WBXML I/O with the EAS code pages (`Wbxml.php`); protocol logging |
| Message model | `Horde_ActiveSync_Message_*` | Typed, version-aware property maps for every item type (mail, appointment, contact, task, note, attachments, …) |
| Collection state | `Horde_ActiveSync_Collections`, `Horde_ActiveSync_SyncCache`, `Horde_ActiveSync_Folder_*` | Per-request collection management and the persisted cross-request collection cache |
| Sync state | `Horde_ActiveSync_State_Base` (`_Sql`, `_Mongo`) | Devices, policy keys, sync keys, change maps, garbage collection |
| Backend API | `Horde_ActiveSync_Driver_Base` (+ `Driver_Mock`) | Abstract data access; all reads/writes to the actual store go through it |
| Import/export | `Horde_ActiveSync_Connector_Importer`, `Connector_Exporter_Sync` | Apply client changes to the backend; stream server changes to the encoder |
| Mail building | `Horde_ActiveSync_Imap_Adapter`, `Imap_EasMessageBuilder*`, `Imap_MessageBodyData`, `Imap_Strategy_*` | Turn IMAP messages into EAS `Message_Mail` objects; change detection strategies (modseq, plain, initial) |
| Device & policy | `Horde_ActiveSync_Device`, `Horde_ActiveSync_Policies` | Per-device metadata (type, policy key, wipe status, version); policy WBXML/XML generation |
| Search | `Horde_ActiveSync_Search_*`, `Horde_ActiveSync_Find_*` | `Search` and EAS 16 `Find` parameter/result objects, KQL parser |

## Request lifecycle

Every request enters through `Horde_ActiveSync::handleRequest($cmd, $devId)`:

1. **Logger setup** from GET parameters (per-device log files).
2. **`versionCallback()`** — if the driver implements it, it may adjust the
   advertised version ceiling for this request (per-user/per-device policy).
3. **Autodiscover** short-circuits here (it authenticates on its own).
4. **Authentication** via `Horde_ActiveSync_Credentials` and the driver. On
   failure the EAS headers are still sent (clients probe with bad
   credentials) and `Horde_Exception_AuthenticationFailure` is thrown.
5. **Command normalisation** — `FolderDelete`/`FolderUpdate` are handled by
   `Request_FolderCreate`; the deprecated EAS 1.x collection commands map to
   `Request_LegacyCollection`.
6. **Device handling** (`_handleDevice()`) — load or create the
   `Horde_ActiveSync_Device`, check block/allow status, remote wipe state.
7. **Provisioning support** is announced from `driver->getProvisioning()`.
8. **WBXML header** is read from the request body; multipart support
   (`MS-ASAcceptMultiPart`) is detected for `ItemOperations`.
9. **Response headers** (`MS-Server-ActiveSync`, content type) are sent
   *before* dispatch, because some handlers (`GetAttachment`) start streaming
   output immediately.
10. **Dispatch** to `Horde_ActiveSync_Request_<Cmd>::handle()`, which owns
    request parsing (decoder), business logic (driver), and response
    encoding (encoder).

`OPTIONS` requests bypass dispatch and only emit the version/commands
headers.

### Policy enforcement

`Request_Base::checkPolicyKey()` compares the client's `X-MS-PolicyKey`
against the device's stored key; commands that require provisioning respond
with the appropriate provisioning status when the key is stale, which makes
the client run a `Provision` cycle and retry.

## Anatomy of a Sync request

`Request_Sync` (with `Request_SyncBase` and `Horde_ActiveSync_Collections`)
is the heart of the library. A `Sync` request runs in two phases:

### Phase 1 — parse (decoder)

- Read the collection list: sync keys, options (`FilterType`, body
  preferences, conflict handling, `WindowSize`, EAS 16 `ClientUid` …).
- Read client-sent commands (`Add`/`Change`/`Delete`/`Fetch`) per
  collection. In buffered mode they are imported inline through
  `Connector_Importer` while parsing; in streaming mode they are *queued*
  and imported later during output (see
  [`sync-streaming.md`](sync-streaming.md)).
- For EAS ≥ 12.1 an **empty request body** is legal: the collection set is
  reconstructed from the persisted `SyncCache` (`Collections` +
  `SyncCache`), enabling cheap looping sync.
- `HeartbeatInterval` / `Wait` turn the request into a hanging sync: the
  handler polls for changes (`Collections::pollForChanges()`) until the
  heartbeat expires or changes arrive.

### Phase 2 — respond (encoder)

For each collection:

1. `initCollectionState()` loads the state for the client's sync key
   (throwing `StateGone` → sync-key error status when the key is unknown,
   which forces the client to resync).
2. Deferred client commands are imported here when streaming.
3. `getCollectionChangeCount()` asks the backend for pending server→client
   changes; the effective window (client `WindowSize`, global window, and
   the streaming count cap) decides truncation, announced via
   `MoreAvailable` *before* the `Commands` block (MS-ASCMD ordering rule).
4. `Connector_Exporter_Sync` streams each change: fetches the item from the
   driver, encodes it (`Message_*::encodeStream()`), and records it in the
   change map so the client's echo is not mirrored back.
5. Replies for client commands (`SyncReplies`: server ids for adds, status
   for failures, EAS 16 `modifiedids`) are encoded.
6. A **new sync key** is written with the resulting state. The client
   confirms it by using it in the next request; until then the previous
   state remains recoverable (sync keys are the transaction mechanism).

`Ping` is the lightweight sibling: no item transfer, only "did anything
change in these collections", with heartbeat bounds from configuration.

## Sync state and the SyncCache

State backends (`State_Sql`, `State_Mongo`) persist, per device + user:

- **Device records** (`horde_activesync_device`, `_device_users`): device
  info, policy key, wipe status, per-device version.
- **Sync state** (`horde_activesync_state`): serialized folder state per
  collection and sync key — for mail, the seen UID set and modseq
  (`Folder_Imap`); for other classes, timestamp state (`Folder_Collection`).
- **Change map** (`horde_activesync_map`, `_mailmap`): which changes were
  sent by / imported from the client, keyed by sync key, so client-origin
  changes are filtered out of the next export (loop avoidance).
- **SyncCache** (`horde_activesync_cache`): cross-request collection
  metadata (folder list, per-collection options, hierarchy sync key,
  pingable flags) enabling EAS ≥ 12.1 empty and looping Sync requests.

Two sync keys per collection are kept alive so a lost response can be
replayed; garbage collection trims older generations. When state and client
diverge irrecoverably, the handler answers with a sync-key error status and
the client restarts from sync key 0 (full resync of that collection).

## WBXML layer

`Wbxml_Decoder` and `Wbxml_Encoder` implement EAS WBXML directly (no
external XML lib): multi-byte integers, string tables, opaque data, and the
EAS code pages defined in `Wbxml.php`. Points worth knowing:

- The **encoder buffers start tags** and only writes them when actual
  content follows (`startTag(..., $output = false)` semantics), so empty
  containers never reach the wire.
- `Encoder::flushOutput()` pushes encoder bytes to the PHP output stream —
  the streaming hook. `Encoder::keepAlive()` emits a redundant
  `SWITCH_PAGE` to the already-active code page: a semantic no-op for any
  conforming parser, used as a keep-alive byte source during long
  server-side work (see [`sync-streaming.md`](sync-streaming.md)).
- The WBXML header is emitted exactly once (`outputWbxmlHeader()` is
  idempotent).
- Both classes log a pretty-printed protocol trace (`I:` / `O:` lines in the
  per-device logs) — the primary debugging tool for client issues.
- Large values (attachment data, bodies) are streamed through
  `Horde_Stream` objects, never concatenated into strings.

## Import and export connectors

- `Connector_Importer` applies client changes to the backend: conflict
  detection against the change map, `changeMessage()` / `deleteMessage()` /
  `moveMessage()` calls, folder operations, and recording of client-origin
  changes so they are not exported back.
- `Connector_Exporter_Sync` walks the change list produced by
  `getServerChanges()` and encodes each change into the response, updating
  state as it goes.

## The IMAP subsystem

Mail is the only class where the library itself contains substantial data
logic (other classes convert in the backend):

- `Imap_Adapter` wraps a `Horde_Imap_Client` factory and implements folder
  stat/changes/fetch for the driver.
- Change detection strategies in `Imap_Strategy_*`: `Modseq`
  (CONDSTORE/QRESYNC servers), `Plain` (fallback polling), `Initial` (first
  sync).
- `Imap_EasMessageBuilder` + `Imap_MessageBodyData` assemble
  `Message_Mail` objects honoring body preferences, truncation, MIME
  support, and protocol version differences.

## Tests

```
test/unit/          PHPUnit unit tests (WBXML, messages, requests, state);
                    WBXML and MIME fixtures under …/ActiveSync/fixtures/
test/integration/   End-to-end request tests against the mock driver
```

Run from the package directory:

```bash
cd vendor/horde/activesync
php ../../../vendor/bin/phpunit --bootstrap ../../../vendor/autoload.php
```

`Horde_ActiveSync_Driver_Mock` + `Driver_MockConnector` provide a full fake
backend, so request handlers can be exercised end-to-end (decoder in,
encoder out) without IMAP or a database. Streaming-specific tests live in
`test/unit/Horde/ActiveSync/Request/SyncStreamingTest.php`.

## Documentation conventions

Cross-cutting designs (anything spanning several classes or packages) are
documented here in `doc/`, not in docblocks — e.g.
[`sync-streaming.md`](sync-streaming.md). Code comments are reserved for
*local* invariants that must move with the code (ordering constraints,
protocol quirks at a specific call site) and may point to the relevant doc
with `@see doc/<file>.md`. Do not duplicate design prose into docblocks; it
drifts.
