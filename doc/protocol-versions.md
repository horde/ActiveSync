# EAS protocol versions

The library implements Exchange ActiveSync versions **2.5, 12.0, 12.1, 14.0,
14.1, 16.0 and 16.1** (constants `Horde_ActiveSync::VERSION_*`). All of them
are supported for production use; message classes, request handlers, and
policy encoding adjust automatically to the version negotiated per request.

This document covers how negotiation works and what each version adds *as
implemented in this library*. How to configure version ceilings in a Horde
deployment is in [`configuration.md`](configuration.md); the library API for
custom drivers is in [`integration.md`](integration.md).

## How version negotiation works

Two related values matter on each request:

1. **Server ceiling** (`Horde_ActiveSync::$_maxVersion`, set via
   `setSupportedVersion()`). Controls what the server **advertises** in
   `OPTIONS` / `MS-ASProtocolVersions` and `MS-Server-ActiveSync`, and which
   command sets are available.
2. **Session protocol version** (client header `MS-ASProtocolVersion`, or
   `ProtVer` in GET for very old clients). The level actually used for WBXML
   encoding/decoding on that request. The client must not exceed what it
   offered and what the server supports.

Clients normally negotiate down: a device that sends
`MS-ASProtocolVersion: 16.0` against a server ceiling of `14.1` will sync at
14.1.

The advertised version list is every supported version up to and including
the ceiling (`getSupportedVersions()`), so a ceiling of `14.1` advertises
`2.5,12.0,12.1,14.0,14.1`.

The library itself only exposes `setSupportedVersion()`. Per-user and
per-device policy is a driver concern: if the driver implements
`versionCallback(Horde_ActiveSync $server)`, it is invoked at the start of
every `handleRequest()` call, before authentication completes, and may lower
or raise the ceiling for that request. `Horde_Core_ActiveSync_Driver` uses
this to apply Horde permissions and the `activesync_device_version` hook
(see [`configuration.md`](configuration.md)).

## Commands per version

Advertised in `MS-ASProtocolCommands` (`getSupportedCommands()`):

- **All versions:** `Sync`, `SendMail`, `SmartForward`, `SmartReply`,
  `GetAttachment`, `GetHierarchy`, `CreateCollection`, `DeleteCollection`,
  `MoveCollection`, `FolderSync`, `FolderCreate`, `FolderDelete`,
  `FolderUpdate`, `MoveItems`, `GetItemEstimate`, `MeetingResponse`,
  `Search`, `Ping`, `Provision`, `ResolveRecipients`, `ValidateCert`
- **≥ 12.0 additionally:** `Settings`, `ItemOperations`, `Find`

`OPTIONS` and **Autodiscover** are handled outside the normal command loop
(in Horde, via `Horde_Rpc_ActiveSync`).

## Per-version deltas (as implemented)

### 2.5 — baseline

The oldest supported dialect, kept for legacy devices.

- Reduced command set (no `Settings`, `ItemOperations`, `Find`).
- Message bodies are sent through the legacy per-class body properties
  (truncation via `MIME` options), not `AirSyncBase`.
- Provisioning uses the XML policy format (`MS-WAP-Provisioning-XML`).
- Folder hierarchy via `GetHierarchy`/`CreateCollection`-style commands.

### 12.0

- **`AirSyncBase` namespace:** unified `Body`, `BodyPreferences`, attachment
  metadata across all item classes (`Horde_ActiveSync_Message_AirSyncBase*`).
- **Provisioning 2:** WBXML policy format with the extended policy-setting
  vocabulary (`Horde_ActiveSync_Policies`); XML policies are rejected for
  ≥ 12.0 devices and vice versa.
- `Settings` (device information, OOF) and `ItemOperations` (fetch,
  attachments) become available.
- Search across mailbox and GAL.

### 12.1

- **Empty/short Sync requests:** a client may send a `Sync` with no body, or
  omit per-collection options; the server completes the request from the
  persisted **`SyncCache`**. This is the basis of efficient looping sync.
- **Hanging Sync:** `HeartbeatInterval` / `Wait` on the `Sync` command itself
  (long-poll without `Ping`).
- Policy key handling and provisioning-status vocabulary extended
  (`Horde_ActiveSync_Policies` emits additional 12.1 policy settings).
- The folder class (`FolderType`) is no longer echoed per collection in Sync
  responses (clients track it from `FolderSync`).

### 14.0

- **Conversations:** `ConversationMode` on Sync collections and conversation
  ids on mail items.
- **Reply/forward state:** `LastVerbExecuted` / `LastVerbExecutionTime`
  exported on mail flag changes.
- Free/busy data in `ResolveRecipients`.
- `MeetingResponse` and meeting-request handling reworked (native WBXML
  encoding of meeting metadata).

### 14.1

- **Rights management:** `RightsManagementSupport` negotiated per collection
  during Sync parsing.
- **`BodyPartPreference` / `BodyPart`:** partial-body sync for
  conversation-style clients.
- **GAL photos:** picture options and photo data in `ResolveRecipients`
  responses (and later in `Find`).
- Extended device information in `Settings`.

### 16.0

Microsoft reworked several areas in 16.0. Implemented here:

| Area | Behaviour |
|------|-----------|
| **Calendar instances** | Exceptions are first-class sync items with top-level `InstanceId`, not only embedded in the series master; masters export only deleted-instance exceptions; bound exceptions visible in initial sync |
| **ClientUid** | Client-generated UID persisted on events and round-tripped on sync |
| **Location** | `AirSyncBase:Location` object (display name + coordinates) instead of a plain string |
| **Drafts** | Draft folder sync; content changes reported as `CHANGE_TYPE_DRAFT`; `POOMMAIL2:Send` sends via SMTP and removes the draft |
| **Find** | Mailbox/GAL search; KQL parser (`Horde_ActiveSync_Find_Kql`) maps boolean operators, property restrictions, dates, and sizes to backend (IMAP) search |
| **SmartForward/Reply** | `Forwardee` list support |
| **Appointment validation** | Forbidden inbound top-level fields (`uid`, `dtstamp`, `organizername`, `organizeremail`) are stripped per MS-ASCAL instead of rejecting the item |
| **All-day events** | Date-only handling without spurious timezone conversion |

Horde driver details (initial calendar UID list omitting bound exceptions,
`calendar_import()` unified return shape) live in `horde/core` and
`horde/kronolith`.

### 16.1

A small delta on top of 16.0. Implemented here:

| Area | Behaviour |
|------|-----------|
| **Propose new time** | `MeetingResponse` accepts `ProposedStartTime` / `ProposedEndTime`; outbound RFC 5546 `METHOD=COUNTER`; inbound storage and sync of attendee proposals |
| **DisallowNewTimeProposal** | Exported on calendar appointments (≥ 14.0) from iCal `DISALLOW-COUNTER`; inbound proposals ignored when set |
| **Account-only remote wipe** | `Provision:AccountOnlyRemoteWipe` status flow; admin and user device UI in Horde (devices must negotiate ≥ 16.1) |

Horde driver, Kronolith, iTip, and IMP details live in `horde/core`,
`horde/kronolith`, `horde/itip`, and `horde/imp`.

## Implemented feature set by item class

Independent of protocol version (availability of individual features follows
the deltas above):

### Mail (EAS `Email` class)

- Folder hierarchy sync, message sync, flags, categories
- Send, reply, forward (`SendMail`, `SmartReply`, `SmartForward`)
- Attachments (`GetAttachment`, `ItemOperations:Fetch`)
- Meeting requests embedded in mail
- Body preferences and truncation (`AirSyncBase:Body`, `BodyPart`)
- Draft folder sync and EAS 16 draft editing/sending
- GAL search (`Search`, `ResolveRecipients`), mailbox `Find`

### Calendar (EAS `Calendar` class)

Conversion logic is shared between this library's `Message/Appointment` /
`Message/Exception` classes and the calendar backend (in Horde:
`Kronolith_Event::fromASAppointment()` / `toASAppointment()`).

- Create, update, delete; recurrence and exceptions
- Attendees, reminders, categories, sensitivity, busy status
- Meeting responses, EAS 16.1 time proposals
- EAS 16 instance model (see above)

### Contacts (`Contacts`)

- Personal address books and GAL
- Photo support via `ResolveRecipients` / `Find` picture options
- Standard vCard-style field mapping

### Tasks (`Tasks`) and Notes (`Notes`)

- Full folder sync and item CRUD (in Horde: through Nag and Mnemo)
- Task recurrence (basic)

### Device management

- Provisioning and policy keys (`Provision`, `Settings`)
- Remote wipe (full; account-only with 16.1) with status tracking
- Per-device logging and block/allow hooks (Horde `hooks.php`)
