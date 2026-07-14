# Using the library (integrators)

How to embed `horde/activesync` in your own product — outside Horde, or with
a minimal test stack. For Horde deployment configuration see
[`configuration.md`](configuration.md); for internals see
[`architecture.md`](architecture.md).

## The three things you provide

The library handles the protocol (WBXML, command dispatch, sync state
machine, device management). You provide:

1. **A backend driver** — subclass of `Horde_ActiveSync_Driver_Base` that
   maps EAS operations onto your data store (folders, messages, search,
   send-mail, policies).
2. **A state backend** — `Horde_ActiveSync_State_Sql` (any
   `Horde_Db_Adapter`) or `Horde_ActiveSync_State_Mongo`, or your own
   subclass of `Horde_ActiveSync_State_Base`. Persists device records, sync
   keys, and change maps. SQL schema ships in `migration/`.
3. **The HTTP plumbing** — routing `/Microsoft-Server-ActiveSync` to a
   script that instantiates the server and hands it the request. In Horde
   this is `horde/rpc`'s `Horde_Rpc_ActiveSync`; any framework will do.

## Minimal server setup

```php
$state = new Horde_ActiveSync_State_Sql(['db' => $hordeDbAdapter]);

$driver = new My_ActiveSync_Driver([
    'state' => $state,          // required
    'logger' => $logger,        // optional Horde_Log_Logger
    'ping' => [                 // Ping/heartbeat bounds
        'heartbeatmin' => 60,
        'heartbeatmax' => 2700,
        'heartbeatdefault' => 480,
        'deviceping' => true,
        'waitinterval' => 15,
    ],
    'sync' => [                 // Sync response delivery, all optional
        'streaming' => true,    // see doc/sync-streaming.md
        'maxmessagesperresponse' => 10,
    ],
]);

$server = new Horde_ActiveSync(
    $driver,
    new Horde_ActiveSync_Wbxml_Decoder(fopen('php://input', 'r')),
    new Horde_ActiveSync_Wbxml_Encoder(fopen('php://output', 'w+')),
    $state,
    $httpRequest                // Horde_Controller_Request_Http
);
$server->setSupportedVersion(Horde_ActiveSync::VERSION_SIXTEENONE);
$server->setLogger($logger);
$server->handleRequest($cmd, $deviceId);   // from GET: Cmd, DeviceId
```

`handleRequest()` performs authentication (via your driver), version
negotiation, device handling, provisioning enforcement, and dispatches to the
matching `Horde_ActiveSync_Request_*` handler, which reads the request body
from the decoder and writes the response through the encoder.

**Response delivery** is your caller's responsibility: either buffer the
encoder output and send it with `Content-Length` (classic), or — for the
`Sync` command — let the handler stream it and send no `Content-Length`
(chunked). `Horde_Rpc_ActiveSync` in `horde/rpc` is the reference
implementation of both paths, including the streaming rules (no output
buffering, no zlib compression, `headers_sent()`-guarded error headers). If
you enable `sync => streaming`, replicate that behaviour; details in
[`sync-streaming.md`](sync-streaming.md).

## Implementing a driver

Subclass `Horde_ActiveSync_Driver_Base` and implement its abstract methods.
They group into:

| Group | Methods (selection) | Purpose |
|-------|--------------------|---------|
| Authentication | `authenticate()`, `setup()`, `clearAuthentication()` | Map HTTP credentials to your user model |
| Folder hierarchy | `getFolders()`, `getFolderList()`, `getFolder()`, `statFolder()`, `changeFolder()`, `deleteFolder()` | Expose your folder tree as EAS collections |
| Item sync | `getServerChanges()`, `getMessage()`, `statMessage()`, `changeMessage()`, `deleteMessage()`, `moveMessage()` | Change detection and item CRUD |
| Mail specifics | `sendMail()`, `getAttachment()`, `getWasteBasket()`, `setReadFlag()`, `statMailMessage()` | SMTP send, attachments, trash semantics |
| Search | `getSearchResults()`, `getFindResults()`, `resolveRecipient()` | Mailbox/GAL search (`Search`, `Find`, `ResolveRecipients`) |
| ItemOperations | `itemOperationsGetAttachmentData()`, `itemOperationsFetchMailbox()`, `itemOperationsGetDocumentLibraryLink()` | Fetch operations |
| Device / policy | `getCurrentPolicy()`, `getProvisioning()`, `getSettings()`, `setSettings()`, `autoDiscover()`, `getUsernameFromEmail()` | Provisioning, OOF, autodiscover |
| Meetings | `meetingResponse()`, `getFreebusy()` | Meeting accept/decline/counter |

The authoritative list with full signatures and docblocks is
`lib/Horde/ActiveSync/Driver/Base.php`. `Horde_Core_ActiveSync_Driver` in
`horde/core` is the production reference implementation;
`Horde_ActiveSync_Driver_Mock` (+ `MockConnector`) in this package is a
minimal reference stack used by the unit and integration tests.

### Item conversion

Sync items travel as `Horde_ActiveSync_Message_*` objects (typed,
version-aware WBXML property maps). Your driver converts between them and
your domain model:

- `getMessage()` returns a populated message object
  (e.g. `Horde_ActiveSync_Message_Appointment`) for export to the client.
- `changeMessage()` receives a message object decoded from the client and
  applies it to your store.

Message constructors accept `protocolversion` and adjust their property maps
to the negotiated EAS level, so the same driver code serves all protocol
versions. In Horde, calendar conversion lives in
`Kronolith_Event::fromASAppointment()` / `toASAppointment()`.

### Dynamic protocol ceilings

Optionally implement `versionCallback(Horde_ActiveSync $server)` on your
driver. The library checks `is_callable([$driver, 'versionCallback'])` at the
start of every request, before authentication completes — call
`$server->setSupportedVersion()` there to enforce per-user or per-device
ceilings (this is how Horde's permission- and hook-based version policy is
implemented). See [`protocol-versions.md`](protocol-versions.md) for the
negotiation mechanics.

## State backends

| Class | Storage | Notes |
|-------|---------|-------|
| `Horde_ActiveSync_State_Sql` | Any `Horde_Db_Adapter` | Default; schema in `migration/` (`horde_activesync_*` tables) |
| `Horde_ActiveSync_State_Mongo` | MongoDB | Same responsibilities, document storage |

The state backend persists: device records and per-user device pairings,
policy keys, sync state per collection + sync key, incoming-change maps (to
avoid mirroring client changes back), and the `SyncCache`. If you implement
your own, subclass `Horde_ActiveSync_State_Base` and keep its locking and
garbage-collection semantics — see [`architecture.md`](architecture.md).

## Requirements and tests

PHP `^7.4 || ^8`, plus the Horde packages listed in `composer.json`.
Suggested for a full stack: `horde/imap_client`, `horde/db`, `horde/mail`.

Run the package tests from the package directory:

```bash
cd vendor/horde/activesync
php ../../../vendor/bin/phpunit --bootstrap ../../../vendor/autoload.php
```

WBXML fixtures and protocol-level tests are under `test/unit/` and
`test/integration/`; the mock driver stack lets you test request handling
end-to-end without a real backend. See [`architecture.md`](architecture.md)
for the test layout.
