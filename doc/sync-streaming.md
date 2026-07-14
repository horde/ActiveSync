# Sync response streaming

Design document for streamed `Sync` response delivery. Operator-facing
configuration is summarized in [`configuration.md`](configuration.md);
internals context is in [`architecture.md`](architecture.md).

Tracking issues: problem report
[horde/ActiveSync#77](https://github.com/horde/ActiveSync/issues/77),
implementation [horde/ActiveSync#83](https://github.com/horde/ActiveSync/issues/83).

## The problem

Historically the whole `Sync` WBXML response was buffered:
`Horde_Rpc_ActiveSync` wrapped the handler in a 1 MiB output buffer and sent
the result with a `Content-Length` header. A client therefore received **no
response body bytes** until the server had fetched and encoded the entire
batch.

Some clients — notably Gmail on Android — abort the connection after ~30
seconds without body bytes (`SocketTimeout`), retry with the old sync key,
and can end up in a broken state that only an account re-add or a
server-side state reset resolves.

Two distinct server-side silent periods can exceed that budget:

1. **Export phase:** fetching and encoding a large server→client batch
   (big mailboxes, large `FilterType` windows, slow IMAP).
2. **Import phase:** applying a large client→server batch *while parsing
   the request*, before a single response byte exists. Example: Gmail's
   `FullDraftsUpSync` re-sends hundreds of drafts as `Change` commands in
   one request; the IMAP side effects took ~55 s of total silence.

The client timeout is on *time to first/next byte*, not on total request
duration: a Sync may take minutes as long as data keeps arriving. That is
the invariant this design establishes: **once a Sync request is accepted,
response bytes keep flowing until the response is complete.**

## Architecture

Streaming spans three packages:

| Layer | Behaviour when streaming is enabled |
|-------|-------------------------------------|
| `horde/horde` `rpc.php` | Passes `$conf['activesync']['sync']['streaming']` to the RPC layer |
| `horde/rpc` `Horde_Rpc_ActiveSync` | For `Cmd=Sync` POST only: skips the full-response output buffer, disables zlib compression, sends no `Content-Length` (the web server applies chunked transfer-encoding). All other commands (`GetAttachment`, `ItemOperations`, `Ping`, …) keep the buffered `Content-Length` response |
| `horde/activesync` `Request_Sync` + `Wbxml_Encoder` | Flushes WBXML incrementally (details below) |

The feature is **opt-in** (`streaming = false` by default) and fully
reversible: disabling it restores the buffered `Content-Length` path
including the legacy `maxresponsetime` budget, with no other changes.

### Export phase: incremental flushing

`Request_Sync` flushes the encoder (`Encoder::flushOutput()`):

- after the response envelope/status preamble,
- after **every exported message**,
- at each folder close.

So even when the backend is slow per message, the client sees bytes at
message granularity instead of one buffer at the end.

### `MoreAvailable` ordering without buffering

MS-ASCMD requires `MoreAvailable` *before* the `Commands` block in the
folder response. The legacy time budget (`maxresponsetime`) solved this by
buffering the whole `Commands` section and deciding truncation afterwards —
which defeats streaming.

Instead, the count cap `maxmessagesperresponse` is folded into the
**effective window size before the `Commands` section starts**, so
truncation is always known up front and `MoreAvailable` is emitted through
the existing window-exceeded path. The `Commands` buffer is never used when
streaming, and `maxresponsetime` is ignored (a log notice is emitted if
both are configured). Unsent changes stay in `sync_pending` and are
delivered on the follow-up request the client issues in response to
`MoreAvailable`.

### Import phase: deferred commands with keep-alives

Export-phase flushing does not help when the *incoming* side is slow, since
imports historically ran during request **parsing** — before any response
byte. When streaming is enabled:

- During parsing, incoming `Add`/`Change`/`Delete` (and EAS 16 instance
  delete) commands are **queued** per collection instead of imported
  inline. Parsing then completes in a fraction of a second and the response
  preamble is flushed early.
- The queued commands are imported during response output, immediately
  after `initCollectionState()` — the same logical position relative to
  change detection and sync-key generation that inline imports occupied, so
  the state semantics are unchanged (`importedchanges`, `SyncReplies`,
  conflict detection against the change map all behave as before).
- Between imports the encoder emits a **WBXML keep-alive**
  (`Encoder::keepAlive()`) and flushes, so bytes flow for the whole import
  phase (one token per command; for a 200-command batch over ~55 s that is
  a byte every ~0.3 s).

#### The keep-alive token

`keepAlive()` writes a `SWITCH_PAGE` token to the *already active* code
page (2 bytes: `0x00` + current page). Per the WBXML specification a
`SWITCH_PAGE` to the current page is a semantic no-op; any conforming
parser skips it without state change. This makes it a safe filler byte that
can be injected at arbitrary token boundaries inside the response. The
encoder guarantees the WBXML document header precedes the first keep-alive
(`outputWbxmlHeader()` is idempotent and called from `keepAlive()`).

Verified transparent against this package's own `Wbxml_Decoder`
(`SyncStreamingTest::testKeepAliveTokensAreTransparentToDecoder`) and in
practice against iOS and Gmail clients.

## Error model: pre-commit vs post-commit

Once the first body byte is flushed, the HTTP status line can no longer be
changed — HTTP 400/500 responses are only possible **before** streaming
starts. The design therefore splits errors at the *commit point* (first
flushed byte):

| Phase | Error channel |
|-------|---------------|
| Pre-commit: auth, policy check, request decode, change poll | HTTP 400/500 and header-level errors — unchanged from buffered mode |
| Post-commit: export, deferred import | In-protocol only: folder `Status` elements, per-command `SyncReplies` statuses, `MoreAvailable`. A streaming abort handler catches exporter exceptions, logs them, and closes a valid WBXML envelope. Unsent changes stay in `sync_pending` |

Deferred import errors are recorded per command (failed adds get a failure
status in `SyncReplies`, failed changes are counted as import failures) —
they never abort the response. This is strictly better client-visible
behaviour than the buffered path, where a late exception produced an
HTTP 500 and the client discarded the entire batch.

The RPC error paths guard `header()` calls with `headers_sent()`, so a
post-commit failure degrades to a truncated (but prefix-valid) response the
client re-requests — never a mid-stream protocol violation.

## Configuration

All keys under `$conf['activesync']['sync']` (for library embedders: the
`sync` array in the driver parameters, exposed via
`Driver_Base::getSyncConfig()`):

| Key | Default | Meaning |
|-----|---------|---------|
| `streaming` | `false` | Master switch for streaming Sync delivery |
| `maxmessagesperresponse` | `10` | Count cap per response when streaming; more changes are announced via `MoreAvailable`. `0` = window size only |
| `maxmessagetime` | `0` | Soft cap (seconds) for assembling a single message; stops the batch after a slow message. Streaming only. `0` = off |
| `maxrequestduration` | `0` | Whole-request wall clock cap (seconds), measured from request start (includes the deferred import phase). Streaming only. `0` = off |
| `maxresponsetime` | `25` | **Legacy** export-phase time budget; only honored when `streaming` is `false` |

`maxmessagesperresponse` is folded into the effective window size, so it can
only *lower* the client's `WindowSize`, never raise it.

## Observability

INFO-level log lines to verify and measure streaming:

- `SYNC: starting response output N.Ns after request start (streaming on|off)`
  — time to first byte; the primary health signal.
- `Queued N incoming changes for deferred import (streaming).` — up-sync
  batch detected during parsing.
- `SYNC: imported N deferred incoming change(s) for collection F… in N.Ns`
  — duration of the deferred import phase.

## Deployment prerequisites

PHP can disable its own buffering and zlib compression, but **not** the web
server's. Response buffering or compression on
`/Microsoft-Server-ActiveSync` (lighttpd `mod_deflate`, nginx `gzip` /
`proxy_buffering`, reverse proxies) re-introduces the timeout even with
streaming enabled — exclude the ActiveSync path from such filters.

A device already stuck from earlier timeouts may still need one account
re-add (or server-side device state removal): streaming prevents the
breakage, it does not repair already-broken client state.

## Testing

- `test/unit/Horde/ActiveSync/Request/SyncStreamingTest.php` — keep-alive
  transparency, WBXML header idempotence, deferred-import execution and
  bookkeeping, streaming config plumbing.
- Manual validation: iOS Mail (regression, buffered semantics preserved),
  Gmail on Android (the originally failing client, large export batches and
  `FullDraftsUpSync` up-sync batches).

## Non-goals

- Client pacing of `MoreAvailable` follow-up requests (client behaviour).
- Client state self-repair after an already-broken sync relationship.
- The full Horde 6 request/response pipeline refactor
  ([`todo.md`](todo.md)) — streaming is a tactical subset; the
  Changes-object and response-object work remains on the roadmap.
