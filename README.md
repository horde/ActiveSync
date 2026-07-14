# Horde ActiveSync

PHP library implementing the Microsoft Exchange ActiveSync (EAS) protocol,
versions **2.5 through 16.1**. It decodes WBXML requests from mobile clients,
drives synchronization through a pluggable backend driver, and encodes WBXML
responses — including streamed `Sync` response delivery for clients with hard
read timeouts.

In a typical Horde deployment, this package is the **protocol engine**. The
**data backend** lives in `horde/core` as `Horde_Core_ActiveSync_Driver`, which
talks to IMAP (mail), Kronolith (calendar), Turba (contacts), Nag (tasks), and
Mnemo (notes). The library itself has no Horde application dependencies at
runtime and can be embedded with a custom driver.

## Documentation

The documentation is split by audience:

| You are… | You want to… | Read |
|----------|--------------|------|
| **Administrator / end user** | Enable ActiveSync, configure protocol versions, streaming, logging, the web server endpoint, and per-user policy | [`doc/configuration.md`](doc/configuration.md) |
| **Library user / integrator** | Embed the library in your own product: server object, driver API, state backends, custom backends | [`doc/integration.md`](doc/integration.md) |
| **ActiveSync developer** | Understand the internals: components, request lifecycle, Sync anatomy, state machine, tests | [`doc/architecture.md`](doc/architecture.md) |

Cross-cutting references, useful to all three:

| Topic | Read |
|-------|------|
| Supported EAS protocol versions, negotiation, and what each version adds | [`doc/protocol-versions.md`](doc/protocol-versions.md) |
| Streamed `Sync` response delivery — design, error model, tuning | [`doc/sync-streaming.md`](doc/sync-streaming.md) |
| Open work and the Horde 6 roadmap | [`doc/todo.md`](doc/todo.md) |

## At a glance

```
Mobile client (Outlook, iOS Mail, Gmail, …)
        │  HTTPS POST, WBXML body
        ▼
Web server  →  /Microsoft-Server-ActiveSync  (rewritten to Horde rpc.php)
        │
        ▼
Horde_Rpc_ActiveSync                (horde/rpc)
        │
        ▼
Horde_ActiveSync                    ← this library
  ├── Request handlers (Sync, FolderSync, Ping, …)
  ├── Message classes (Appointment, Mail, Contact, …)
  ├── WBXML encoder/decoder
  └── Horde_ActiveSync_Driver_Base  (abstract backend API)
        │
        ▼
Horde_Core_ActiveSync_Driver        (horde/core, in a Horde deployment)
  └── Horde_Core_ActiveSync_Connector → Horde apps / IMAP
```

### Protocol versions

All EAS versions from 2.5 to 16.1 are implemented and production-supported.
Clients negotiate down to the server's advertised ceiling; the ceiling is
configurable globally, per user, and per device. Details, the version
negotiation mechanics, and a per-version feature delta are in
[`doc/protocol-versions.md`](doc/protocol-versions.md).

| Version | Notes |
|---------|-------|
| 2.5 | Baseline; reduced command set (no `Settings`, `ItemOperations`, `Find`) |
| 12.0 / 12.1 | `AirSyncBase` bodies, provisioning 2, empty/hanging Sync, SyncCache |
| 14.0 / 14.1 | Conversations, reply/forward state, rights management, body parts |
| 16.0 | Calendar instance model, drafts sync, `Find`, `AirSyncBase:Location` |
| 16.1 | Meeting time proposals, account-only remote wipe |

### Feature highlights

- **Mail:** folder & message sync, flags, drafts (incl. EAS 16 draft
  editing/sending), send/reply/forward, attachments, meeting requests,
  mailbox `Find` with KQL parsing, GAL search
- **Calendar:** full recurrence + exceptions, attendees, meeting responses,
  EAS 16 instance model, meeting time proposals (16.1)
- **Contacts / Tasks / Notes:** full CRUD sync incl. task recurrence
- **Device management:** provisioning & policies, remote wipe (full and
  account-only), per-device protocol ceilings, per-device logging
- **State:** SQL (default) or MongoDB backends, sync-key/modseq change
  tracking, `Ping` long-poll, `SyncCache`
- **Delivery:** optional streamed `Sync` responses (chunked HTTP) with
  deferred up-sync import and WBXML keep-alives, so clients with ~30 s read
  timeouts survive large batches — see
  [`doc/sync-streaming.md`](doc/sync-streaming.md)

## Package layout

```
lib/Horde/ActiveSync.php          Server class and protocol constants
lib/Horde/ActiveSync/
  Request/                        EAS command handlers
  Message/                        Item type WBXML mappings
  State/                          Sync state persistence (SQL, Mongo)
  Wbxml/                          Encoder, decoder, code pages
  Imap/                           IMAP-to-EAS message building
  Find/                           EAS 16.0 Find command helpers
  Driver/                         Base, Mock backends
migration/                        SQL schema for state tables
doc/                              Documentation (see index above)
test/unit/                        PHPUnit tests
```

## License

GPL-2.0-only. See [LICENSE](LICENSE).

## References

- [Microsoft Exchange ActiveSync protocol docs](https://learn.microsoft.com/en-us/openspecs/exchange_server_protocols/ms-ascntc30)
- [Horde ActiveSync wiki](http://wiki.horde.org/ActiveSync)
