ActiveSync TODO
===============

Last reviewed: 2026-06-16

This file tracks **remaining** work. For what the library already supports
(protocol versions, commands, EAS 16.0 behaviour, deployment setup), see the
package ``README.md`` at the repository root.

Items are grouped by intent. The **Horde 6** section is a breaking-change
roadmap — do not implement those entries piecemeal on the FRAMEWORK_6_0 /
3.x line without an explicit migration plan.


Near-term (actionable before Horde 6)
-------------------------------------

- **Recurring meeting requests in mail**

  ``Horde_ActiveSync_Message_MeetingRequest`` still defaults
  ``instancetype`` to ``0`` and does not export recurrence data embedded in
  meeting-invitation messages. Calendar recurrence sync is separate and works
  for EAS 16.0; this item is only about **recurring invitations carried inside
  email** (``MeetingRequest`` / ``MeetingRequestRecurrence``).


Deferred (low priority or no known client)
------------------------------------------

- **ItemOperations ``Schema`` requests**

  The decoder skips ``ItemOperations:Schema`` subtrees without acting on them.
  No client in active use is known to require schema-based fetches. Revisit if
  a device surfaces a concrete failure.

- **HTTP 503 throttling (``X-MS-Throttle``)**

  Exchange-style overload signalling is not implemented. Only relevant at high
  load or when deliberately rate-limiting devices.

  See `Microsoft throttling guidance <https://learn.microsoft.com/en-us/previous-versions/office/developer/exchange-server-interoperability-guidance/jj899829(v=exchg.140)>`_.

- **Task recurrence edge cases (``DEADOCUR`` / single-instance completion)**

  Basic task recurrence sync exists (Nag ↔ ``Horde_ActiveSync_Message_Task``).
  Completing or deleting a single instance of a recurring task series —
  especially client use of ``DEADOCUR`` — needs more end-to-end testing and
  may require Nag changes more than activesync library changes.

- **Find / KQL expansion**

  EAS 16.0 ``Find`` is implemented with a minimal KQL subset (``from:``,
  ``to:``, ``subject:``, quoted terms, ``OR``). Broader KQL coverage is
  incremental polish, not a blocker for 16.0.


Operations and monitoring (out of library scope)
------------------------------------------------

- **EAS usage dashboard / “top-like” monitor**

  A live view of active devices, error rates, and stuck sync keys would be
  valuable for operators but belongs in a separate admin tool or Horde UI
  module, not in the protocol library. Per-device protocol logging
  (``logging.type = perdevice`` in Horde config) is the supported debugging
  path today.


Horde 6 (breaking changes — planned refactor)
---------------------------------------------

Do not start these ad hoc. Each item touches public API surface, persisted
state, or both.

**Protocol and class layout**

- Consolidate non-protocol constants into a dedicated class.
- Rename WBXML tag constants to match MS-AS* document names (today many follow
  legacy Z-Push naming).
- Decouple WBXML codepage tables from ``Horde_ActiveSync_Wbxml_Encoder`` /
  ``Decoder`` into separate classes.
- Add field-definition metadata (e.g. maximum encoded size) on message maps.
- Introduce ``Horde_ActiveSync_Protocol_Exception`` and tighten the exception
  hierarchy.

**Request / response pipeline**

- Split request parsing from handling (``Request_Parser`` +
  ``Request_Handler``); stream large inbound bodies instead of buffering.
- Replace ``Horde_Controller_Request_Http`` with a library-local HTTP request
  object; add a matching response object and move header logic out of
  ``Horde_Rpc_ActiveSync``.
- Move non-server helpers out of ``Horde_ActiveSync`` (truncation helpers,
  version negotiation utilities, etc.).

**State, storage, and identity**

- Rename ``Horde_ActiveSync_State_*`` to ``Horde_ActiveSync_Storage_*`` (or
  extract a storage layer) — these classes already manage more than sync keys.
- Return ``Horde_ActiveSync_Device`` objects from ``listDevices()`` instead
  of SQL field hashes; accept device property names in filters.
- Eliminate static ``Horde_ActiveSync::$_*`` properties (logger, device,
  version).
- Unify ``serverid`` vs backend folder names: one map, server IDs everywhere
  inside the library; backend IDs only at the driver boundary.
- Fold ``SyncCache`` and per-collection state into the device object where
  practical.
- Implement ``Horde_ActiveSync_SyncKey`` as a first-class type.

**Driver and backend shape**

- Repository (or similar) per collection class instead of monolithic
  ``Horde_ActiveSync_Driver_Base`` / ``Horde_Core_ActiveSync_Connector``
  switch statements.
- Normalize folder create/edit/delete to accept and return
  ``Horde_ActiveSync_Message_Folder`` objects consistently. Multiplexed
  non-email folders (``Calendar:ID``, ``Tasks:ID``, …) already work, but
  method signatures and return shapes still vary.
- Store ``Horde_ActiveSync::CLASS_*`` and ``FOLDER_TYPE_*`` in persisted state
  to stop converting between them at runtime.
- Consolidate folder UID ↔ backend ID mapping (today split across
  ``Horde_ActiveSync_Collections`` and the driver).
- Pass ``FILTERTYPE_*`` to the driver by constant, not precomputed cutoff
  timestamps (supersedes the near-term ``INCOMPLETETASKS`` fix style).
- Split ``getMessage()`` into per-class methods with shared base logic.
- ``Horde_ActiveSync_Change_Filter`` (or equivalent) for client-specific
  workarounds. Some MOVEITEMS duplication issues were fixed in the past, but
  there is no general filter framework.
- Move mail send/forward/reply helpers from ``Horde_Core_ActiveSync_Mail`` into
  the activesync package behind injectable mailer/identity dependencies.

**Sync data structures**

- ``Horde_ActiveSync_Sync_Options`` (and similar) for collection options /
  body preferences instead of raw arrays.
- Collection object replacing the associative collection array in ``Sync.php``.
- Changes object (array, ``SplFixedArray``, or temp stream) to cap memory on
  large initial mailbox syncs and to unify the ``add`` / ``modify`` / ``delete``
  shapes between email and PIM collections.
- Sync-reply objects per collection type; move logic out of
  ``Horde_ActiveSync_Connector_Exporter_Sync``.
- Configuration builder for server/driver construction (Ping-related settings
  are no longer Ping-only).

**SMS**

- Today SMS is deliberately stubbed: imports return ``IGNORESMS_*`` phantom
  UIDs so broken clients do not break email sync. Horde 6 should pass SMS
  collections to the backend and let it opt in/out instead of hard-coding
  ignores in ``Horde_ActiveSync_Connector_Importer``.

**Miscellaneous**

- Single logger access point (``Horde_ActiveSync_Debug`` or similar) instead of
  injecting ``Horde_Log_Logger`` everywhere.
- ``Horde_ActiveSync_Message_Date`` (or shared date helper) for POOM date
  normalisation currently scattered in message classes.
- Device class hierarchy instead of one ``Horde_ActiveSync_Device`` with all
  properties.


Recently completed
------------------

Verified in the 3.x / FRAMEWORK_6_0 tree as of 2026-06. Removed from the
active backlog; kept here so this file does not resurrect settled work.

**Protocol versions and commands**

- EAS **16.0** and **16.1** as supported ceilings (``VERSION_SIXTEEN``,
  ``VERSION_SIXTEENONE``).
- EAS 16.0 **Find** command with mailbox/GAL search and minimal KQL
  (``Horde_ActiveSync_Request_Find``, ``Horde_ActiveSync_Find_Kql``).
- **Autodiscover**, **ItemOperations** (fetch/move/empty; not Schema),
  **Settings**, **Provision**, **Ping**, **Search**, **ValidateCert** — all
  present for supported versions (see ``README.md``).

**EAS 16.0 calendar** (library + ``horde/kronolith`` + ``horde/core``)

- Instance model: bound exceptions as top-level items with ``InstanceId``;
  modified instances omitted from master ``Exceptions`` at 16.0+.
- ``ClientUid`` import/export round-trip.
- ``AirSyncBase:Location`` import/export (display name + coordinates).
- Inbound appointment validation strips forbidden fields instead of rejecting.
- Initial calendar sync hides bound exceptions only for protocol versions
  below 16.0.

**EAS 16.0 mail** (``horde/core``)

- Draft folder content changes use ``CHANGE_TYPE_DRAFT``.
- Draft send via ``POOMMAIL2:Send`` (``toRfc822Stream()`` + SMTP).
- ``Forwardee`` objects on SmartForward/SmartReply.

**EAS 16.1** (library + ``horde/core`` + ``horde/kronolith`` + ``horde/itip`` + ``horde/imp``)

- ``MeetingResponse`` ``ProposedStartTime`` / ``ProposedEndTime`` with RFC5546
  ``METHOD=COUNTER`` / ``DECLINECOUNTER``.
- Attendee proposed times stored and exported on calendar sync.
- ``DisallowNewTimeProposal`` export from Kronolith (iCal ``DISALLOW-COUNTER``).
- ``Provision:AccountOnlyRemoteWipe`` with admin and prefs UI.

**Multi-folder PIM**

- Multiplexed calendar, contact, task, and note folders
  (``Class:backendId`` server IDs, device ``multiplex`` flag).

**Stability (3.0.0-RC1 and related)**

- ``FILTERTYPE_INCOMPLETETASKS``: pass FilterType to the driver; incomplete-only
  task sync in ``horde/core`` / ``horde/nag``; no longer encode filter ``8`` as a
  Unix cutoff (avoids mail/calendar misconfiguration).
- ``FOLDERSYNC_REQUIRED`` loop guard: per-device counter in sync cache stops
  returning status ``12`` (SYNC) / ``7`` (PING) after five ignored responses;
  escalates to KEYMISMATCH / server error so broken clients can recover.
- SQL/Mongo row locks for parallel state access.
- Reject and repair corrupt ``sync_data`` on load/save.
- Separate PING watermark from SYNC modseq (iOS mail loop fix).
- Hardened initial-sync ACK and KEYMISMATCH on corrupt state.
- Meeting invitation RSVP / ``MeetingResponse`` improvements.

**Older but still relevant**

- EAS 16 draft **sync** (create/edit drafts on device) since 2.36.0.
- MOVEITEMS duplicate-message fix.
- Multiple SYNC/PING/OPTIONS loop fixes for broken clients (see
  ``doc/changelog.yml``).
