# Horde ActiveSync

PHP library implementing the Microsoft Exchange ActiveSync (EAS) protocol. It
decodes WBXML requests from mobile clients, drives synchronization through a
pluggable backend driver, and encodes WBXML responses.

In a typical Horde deployment, this package is the **protocol engine**. The
**data backend** lives in `horde/core` as `Horde_Core_ActiveSync_Driver`, which
talks to IMAP (mail), Kronolith (calendar), Turba (contacts), Nag (tasks), and
Mnemo (notes).

Open work and the Horde 6 roadmap are tracked in
[`doc/Horde/ActiveSync/TODO.rst`](doc/Horde/ActiveSync/TODO.rst).

## How it fits together

```
Mobile client (Outlook, iOS Mail, …)
        │  HTTPS POST, WBXML body
        ▼
Web server  →  /Microsoft-Server-ActiveSync  (rewritten to Horde rpc.php)
        │
        ▼
Horde_Rpc_ActiveSync
        │
        ▼
Horde_ActiveSync                 ← this library
  ├── Request handlers (Sync, FolderSync, Ping, …)
  ├── Message classes (Appointment, Mail, Contact, …)
  ├── WBXML encoder/decoder
  └── Horde_ActiveSync_Driver_Base  (abstract backend API)
        │
        ▼
Horde_Core_ActiveSync_Driver     ← horde/core (Horde deployment)
  └── Horde_Core_ActiveSync_Connector → Horde apps / IMAP
```

### Main classes

| Class | Role |
|-------|------|
| `Horde_ActiveSync` | Server entry point: auth, version negotiation, request dispatch |
| `Horde_ActiveSync_Request_*` | One class per EAS command (`Sync`, `FolderSync`, `Find`, …) |
| `Horde_ActiveSync_Message_*` | Typed WBXML property maps for each item type |
| `Horde_ActiveSync_Driver_Base` | Abstract backend all data access goes through |
| `Horde_ActiveSync_State_Sql` / `_Mongo` | Device state, sync keys, change maps |
| `Horde_ActiveSync_Device` | Per-device metadata (type, policy key, remote wipe) |
| `Horde_ActiveSync_Wbxml_*` | Low-level WBXML encode/decode and protocol logging |

Message objects are version-aware: constructors accept
`protocolversion` and adjust their property maps for the negotiated EAS level.

## Protocol versions

The library defines constants for EAS **2.5**, **12.0**, **12.1**, **14.0**,
**14.1**, **16.0**, and **16.1**.

| Version | Status in this tree |
|---------|---------------------|
| 2.5 – 14.1 | Mature; long-standing Horde support |
| **16.0** | Supported end-to-end for production use (see below) |
| **16.1** | Supported; extends 16.0 with meeting proposals and account-only wipe (see below) |

### How version negotiation works

Two related values matter on each request:

1. **Server ceiling** (`Horde_ActiveSync::$_maxVersion`, set via
   `setSupportedVersion()`). Controls what the server **advertises** in
   `OPTIONS` / `MS-ASProtocolVersions` and `MS-Server-ActiveSync`, and which
   command sets are available.
2. **Session protocol version** (client header `MS-ASProtocolVersion`, or
   `ProtVer` in GET for very old clients). The level actually used for WBXML
   encoding/decoding on that request. The client must not exceed what it
   offered and what the server supports.

Clients normally negotiate down: a device that sends `MS-ASProtocolVersion: 16.0`
against a server ceiling of `14.1` will sync at 14.1.

In a Horde deployment the ceiling is applied in **layers** (see next section).
The library itself only exposes `setSupportedVersion()`; per-user and
per-device policy is implemented in `Horde_Core_ActiveSync_Driver::versionCallback()`
and invoked at the start of every `Horde_ActiveSync::handleRequest()` call,
before authentication completes.

### Protocol version configuration (Horde)

Three mechanisms stack together. None of them are personal **preferences**
(users cannot change their own EAS version under Preferences); per-user limits
are **administrator permissions**.

#### 1. Global ceiling (all users, default)

Set in Horde administration → ActiveSync → *What is the highest version of EAS
that Horde should support?*, or in `conf.php`:

```php
$conf['activesync']['version'] = '16.1';
```

`Horde_Core_Factory_ActiveSyncServer` calls `setSupportedVersion()` with this
value when the server object is created. This is the baseline for every request.

#### 2. Per-user ceiling (permissions)

Default mode: `version_mode` is **`user`** when unset.

Administrators can assign **Maximum ActiveSync protocol version**
(`horde:activesync:version`) per user or group under Horde administration →
Permissions → ActiveSync. Allowed values: `2.5`, `12.0`, `12.1`, `14.0`,
`14.1`, `16.0`, and `16.1`.

On each request, `versionCallback()` resolves the authenticated Horde username
(from HTTP Basic credentials, the `User` GET parameter, or the registry) and
reads that permission. If set, it calls `setSupportedVersion()` again for this
request only.

| Situation | Effective ceiling for this request |
|-----------|-------------------------------------|
| Permission empty / permission tree not defined | Global `conf['activesync']['version']` only |
| User permission **lower** than global (e.g. user `14.1`, global `16.0`) | User value — caps that user below the site default |
| User permission **higher** than global (e.g. user `16.0`, global `14.1`) | User value — can raise the advertised ceiling above the admin default for that user |
| User in multiple groups with different values | **Lowest** (most restrictive) allowed version |

The last row matters for group-based permissions: if one group allows `16.0` and
another `14.1`, the user syncs at `14.1`.

#### 3. Per-device ceiling (hook)

For device-specific policy (pilot devices, problematic clients, lab handsets),
set in `conf.php`:

```php
$conf['activesync']['version_mode'] = 'device';
```

Then implement `activesync_device_version()` in `config/hooks.php` (see
`vendor/horde/horde/config/hooks.php.dist`):

```php
public function activesync_device_version($deviceId, $user)
{
    // $deviceId is normalised to uppercase.
    $map = [
        'OLD-OUTLOOK-DEVICE-ID' => '14.1',
        'TEST-IPHONE-ID'        => '16.0',
    ];

    return $map[$deviceId] ?? null;
}
```

Hook return values:

| Return | Meaning |
|--------|---------|
| String, e.g. `'16.0'` | Use this ceiling for the device |
| Array of version strings | **Lowest** (most restrictive) entry is used |
| `null`, `false`, `''`, or `-1` | No override; fall back to global / user permission behaviour |

`DeviceId` must be present in the request (standard on all sync commands). If
the hook is not defined or returns no override, behaviour depends on
`version_mode`: in **`device`** mode with no hook result, no permission override
is applied; switch back to **`user`** mode to use group permissions as the
primary per-principal control.

#### Choosing `user` vs `device` mode

| `version_mode` | Source of per-request override |
|----------------|-------------------------------|
| `user` (default) | `horde:activesync:version` permission |
| `device` | `activesync_device_version` hook |

Only one mode is active per installation. Use **permissions** for
account/class-of-user policy; use the **hook** when the device ID is the right
key (e.g. force an old Outlook build to `14.1` while everyone else stays on
`16.0`).

#### Custom / non-Horde drivers

Any backend driver may implement `versionCallback(Horde_ActiveSync $server)` the
same way as `Horde_Core_ActiveSync_Driver`. The library checks
`is_callable([$driver, 'versionCallback'])` on every request. Integrators
without Horde permissions can set policy entirely inside that method.

## EAS commands

Commands advertised for EAS ≥ 12.0 (including 16.0):

`Sync`, `SendMail`, `SmartForward`, `SmartReply`, `GetAttachment`,
`GetHierarchy`, `CreateCollection`, `DeleteCollection`, `MoveCollection`,
`FolderSync`, `FolderCreate`, `FolderDelete`, `FolderUpdate`, `MoveItems`,
`GetItemEstimate`, `MeetingResponse`, `Search`, `Settings`, `Ping`,
`ItemOperations`, `Provision`, `ResolveRecipients`, `ValidateCert`, **`Find`**

EAS 2.5 omits `Settings`, `ItemOperations`, and `Find`.

`OPTIONS` and **Autodiscover** are handled outside the normal command loop via
`Horde_Rpc_ActiveSync`.

## Implemented feature set

### Mail (EAS `Email` class)

- Folder hierarchy sync, message sync, flags, categories
- Send, reply, forward (`SendMail`, `SmartReply`, `SmartForward`)
- Attachments (`GetAttachment`, `ItemOperations:Fetch`)
- Meeting requests embedded in mail
- Body preferences and truncation (`AirSyncBase:Body`)
- Draft folder sync; **EAS 16.0** draft edit detection (`CHANGE_TYPE_DRAFT`)
  and draft send via `POOMMAIL2:Send`
- **EAS 16.0** `Forwardee` objects on forward/reply
- GAL search (`Search`, `ResolveRecipients`)
- **EAS 16.0** mailbox `Find` with KQL parser (`Horde_ActiveSync_Find_Kql`)
  supporting boolean operators, property restrictions, dates, and size

### Calendar (EAS `Calendar` class)

Handled in `horde/kronolith` (`Kronolith_Event::fromASAppointment()` /
`toASAppointment()`) with logic in this library's `Message/Appointment` and
`Message/Exception` classes.

- Create, update, delete appointments; recurrence and exceptions
- Attendees, reminders, categories, sensitivity, busy status
- Meeting responses (`MeetingResponse`)
- **EAS 16.0 instance model**: modified recurrence instances sync as separate
  items with top-level `InstanceId`; masters export only deleted-instance
  exceptions; `ClientUid` round-trip; bound exceptions visible in initial sync
- **EAS 16.0** `AirSyncBase:Location` (display name + coordinates)
- **EAS 16.0** inbound validation strips forbidden top-level fields (`uid`,
  `dtstamp`, `organizername`, `organizeremail`) instead of rejecting the item
- All-day event rules for 16.0 (date-only, no spurious timezone conversion)

### Contacts (`Contacts`)

- Personal address books and GAL
- Photo support via `ResolveRecipients` / Find picture options
- Standard vCard-style field mapping

### Tasks (`Tasks`) and Notes (`Notes`)

- Full folder sync and item CRUD through Nag and Mnemo
- Task recurrence (basic)

### Device management

- Provisioning and policy keys (`Provision`, `Settings`)
- Remote wipe status tracking
- Per-device logging (`perdevice` log type in Horde config)
- Device block/allow hooks (Horde `hooks.php`)

### State and performance

- SQL (default) or MongoDB state backends
- Sync key / modseq change tracking
- `Ping` long-poll with configurable heartbeat bounds
- `SyncCache` for in-request collection state
- WBXML protocol logging at configurable verbosity

## EAS 16.0 — what changed

Microsoft reworked several areas in 16.0. The following are implemented in this
codebase:

| Area | Behaviour |
|------|-----------|
| **Calendar instances** | Exceptions are first-class sync items, not only embedded in the series master |
| **ClientUid** | Persisted on events and exported on sync |
| **Location** | `AirSyncBase:Location` instead of plain string for 16.0+ |
| **Drafts** | Content changes reported as `CHANGE_TYPE_DRAFT`; `send=true` sends via SMTP and removes draft |
| **Find** | Mailbox/GAL search; KQL parser maps common Outlook restrictions to IMAP search |
| **SmartForward/Reply** | `Forwardee` list support |
| **Appointment validation** | Forbidden inbound fields stripped per MS-ASCAL spec |

Horde driver details (initial calendar UID list omits bound exceptions at 16.0+,
`calendar_import()` unified return shape) live in `horde/core` and `horde/kronolith`.

## EAS 16.1 — what changed

EAS 16.1 is a small delta on top of 16.0. The following are implemented in this
codebase:

| Area | Behaviour |
|------|-----------|
| **Propose new time** | `MeetingResponse` accepts `ProposedStartTime` / `ProposedEndTime`; outbound RFC5546 `METHOD=COUNTER`; inbound storage and sync of attendee proposals |
| **DisallowNewTimeProposal** | Exported on calendar appointments (≥14.0) from iCal `DISALLOW-COUNTER`; inbound proposals ignored when set |
| **Account-only remote wipe** | `Provision:AccountOnlyRemoteWipe` status flow; admin and user prefs UI (devices must negotiate ≥16.1) |

Horde driver, Kronolith, iTip, and IMP details live in `horde/core`, `horde/kronolith`,
`horde/itip`, and `horde/imp`.

## Using ActiveSync in a Horde deployment

### 1. Enable and configure

In Horde administration → ActiveSync (or `var/config/horde/conf.php`):

```php
$conf['activesync']['enabled'] = true;
$conf['activesync']['version'] = '16.1';   // global protocol ceiling (see above)
$conf['activesync']['storage'] = 'Sql';    // or 'Nosql' (Mongo)
$conf['activesync']['emailsync'] = true;
$conf['activesync']['auth']['type'] = 'basic';
// Optional: per-device version policy instead of per-user permissions
// $conf['activesync']['version_mode'] = 'device';
```

Per-user version limits are **not** preference keys — configure them under
Permissions → ActiveSync → *Maximum ActiveSync protocol version*. See
[Protocol version configuration](#protocol-version-configuration-horde).

Also configure IMAP/SMTP host hints for Autodiscover, logging path/level, and
Ping heartbeat bounds. Full option descriptions are in
`vendor/horde/horde/config/conf.xml` under the `activesync` tab.

History (`$conf['history']['enabled']`) must be enabled — ActiveSync relies on
it for change timestamps.

### 2. Web server URL

Clients expect `/Microsoft-Server-ActiveSync`. Rewrite that path to Horde's RPC
endpoint, for example:

```
/Microsoft-Server-ActiveSync  →  /horde/rpc.php
```

The RPC layer selects the ActiveSync backend when `server=ActiveSync` is passed
(Apache/nginx configs usually add this; see
[Horde ActiveSync wiki](http://wiki.horde.org/ActiveSync)).

Autodiscover is served from the same endpoint when the request URI contains
`autodiscover/autodiscover`.

### 3. Client setup

Point the device at your mail domain. With Autodiscover enabled
(`autodiscovery` in config), iOS and Outlook discover the ActiveSync URL
automatically. Otherwise configure the ActiveSync server URL manually.

Authentication is HTTP Basic against Horde by default (`auth.type = basic`).

### 4. Per-user access

Users need the **ActiveSync** permission in Horde. They can manage enrolled
devices under Personal Preferences → ActiveSync (device list and wipe — not
protocol version).

Administrators assign the maximum EAS version per user or group via
**Permissions**, and optionally per device via `hooks.php` when
`version_mode = device`.
## For integrators — custom backends

To use this library outside Horde (or with a minimal test stack):

1. Subclass `Horde_ActiveSync_Driver_Base` and implement the abstract methods for
   each collection class you support.
2. Provide a `Horde_ActiveSync_State_*` implementation (or use SQL/Mongo drivers
   from this package).
3. Instantiate the server:

```php
$server = new Horde_ActiveSync(
    $driver,
    new Horde_ActiveSync_Wbxml_Decoder(fopen('php://input', 'r')),
    new Horde_ActiveSync_Wbxml_Encoder(fopen('php://output', 'w+')),
    $state,
    $httpRequest
);
$server->setSupportedVersion(Horde_ActiveSync::VERSION_SIXTEEN);
$server->setLogger($logger);
// Optional: implement versionCallback() on your driver for dynamic ceilings
$server->handleRequest($cmd, $device);
```

`Horde_ActiveSync_Driver_Mock` plus `Horde_ActiveSync_Driver_MockConnector` in
this package provide a reference stack for unit and integration tests.

Import/export of calendar data is normally done by converting between
`Horde_ActiveSync_Message_Appointment` and your domain model (in Horde,
`Kronolith_Event`).

## Development and tests

**Requirements:** PHP `^7.4 || ^8`, plus Horde packages listed in `composer.json`.
Suggested packages for a full stack: `horde/imap_client`, `horde/db`, `horde/mail`.

Run unit tests from the package directory:

```bash
cd vendor/horde/activesync
php ../../../vendor/bin/phpunit --bootstrap ../../../vendor/autoload.php
```

Calendar EAS 16.0 import/export tests live in `horde/kronolith`:

```bash
php vendor/bin/phpunit \
  --bootstrap vendor/horde/kronolith/test/bootstrap.php \
  vendor/horde/kronolith/test/Kronolith/Unit/EventActiveSyncTest.php
```

WBXML fixtures and protocol-level tests are under `test/unit/` and
`test/integration/`.

Enable protocol logging (`logging.level` / per-device log files) when debugging
client issues — the logger records command names, collection IDs, and decoded
metadata without dumping full message bodies at low levels.

## Package layout

```
lib/Horde/ActiveSync.php          Server class and protocol constants
lib/Horde/ActiveSync/
  Request/                        EAS command handlers
  Message/                        Item type WBXML mappings
  State/                          Sync state persistence (SQL, Mongo)
  Wbxml/                          Encoder, decoder, code pages
  Find/                           EAS 16.0 Find command helpers
  Driver/                         Base, Mock backends
migration/                        SQL schema for state tables
test/unit/                        PHPUnit tests
doc/Horde/ActiveSync/TODO.rst     Open work and Horde 6 refactor notes
```

## License

GPL-2.0-only. See [LICENSE](LICENSE).

## References

- [Microsoft Exchange ActiveSync protocol docs](https://learn.microsoft.com/en-us/openspecs/exchange_server_protocols/ms-ascntc30)
- [Horde ActiveSync wiki](http://wiki.horde.org/ActiveSync)
