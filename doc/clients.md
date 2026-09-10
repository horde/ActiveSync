# EAS client behaviour notes

Observed behaviour of real ActiveSync clients against Horde. Notes are
**versioned and dated** so they can go stale visibly; re-verify before relying
on them for design decisions.

Companion documents: [`sync-performance.md`](sync-performance.md) (export /
PING work), [`sync-streaming.md`](sync-streaming.md) (Gmail read-timeout
streaming), [`configuration.md`](configuration.md) (operator settings).

Tracking / source discussion:
[horde/ActiveSync#88](https://github.com/horde/ActiveSync/issues/88).

## How to read this document

Each client section records:

- **App / OS version** and negotiated **EAS protocol version** when observed
- Default Sync `BodyPreference` (`Type`, `TruncationSize`)
- Whether the client acts on `AirSyncBase:Truncated` (or legacy
  `BodyTruncated`) by fetching the remainder later
- The mechanism used for a full-body fetch, if any
- Mailbox `Search` / `Find` request shape and timeouts, when observed
- Other quirks that affect server design or operator expectations

EAS is client-driven: the server cannot push the remainder of a truncated
body. A client that never re-fetches treats Sync truncation as permanent.

Client-specific **code** lives in `Horde_ActiveSync_Device::hasQuirk()`
(`QUIRK_*` constants). This document records what was observed; handlers
must not sniff User-Agent strings themselves.

## Gmail for Android

| Field | Value |
|-------|-------|
| Observed | 2026-07 |
| App | Gmail `2026.06.15.936324202.Release` |
| Device type | `Android` |
| Protocol | 16.0 |

**Default Sync body preference:** HTML (`Type=2`),
`TruncationSize=200000`.

**Truncated body:** Gmail does **not** re-fetch the remainder — not on open,
not via a UI control, and not when the truncation was caused by its *own*
200000-byte request (body larger than 200 KB still stays clipped). No
`ItemOperations` item fetch and no Sync `<Fetch>` for the message body was
observed after open. Attachments (including inline images) are fetched
separately via `ItemOperations` with `FileReference` when the message is
opened.

**Implication:** any server-side Sync truncation below Gmail’s requested size
permanently cuts the displayed body. Gmail’s performance model is to
front-load a large body during folder Sync; there is no later repair path.

**Mailbox Search** (observed 2026-09-09, Gmail
`Android-Mail/2026.08.17`, EAS 16.0,
[horde/ActiveSync#104](https://github.com/horde/ActiveSync/issues/104)):

| Field | Value |
|-------|-------|
| Command | `Cmd=Search`, `Store=Mailbox` |
| Query | `FolderType=Email`, FreeText only (no collection id, no `DateReceived`) |
| Options | `RebuildResults`, `DeepTraversal`, `Range=0-9` |
| Search body preference | HTML (`Type=2`), `TruncationSize=20000` (distinct from Sync’s 200000) |

Two independent timeouts:

1. **Time to first byte (~30s).** If the HTTP response body is silent,
   Gmail logs `SocketTimeout from network when sending request with
   timeout 30000ms` and shows “Problem syncing”. Streaming keep-alives
   address this for every client.
2. **Complete document (~40s).** Gmail does not treat an open Search
   stream as success. After `Status 1` with no `Result` / `Range` /
   `Total` / closing tags it logs `No result returned in searchMessages`
   (~44s in the follow-up log) and discards the request. Keep-alives
   alone are not enough.

Gmail therefore has
`Horde_ActiveSync_Device::QUIRK_SEARCH_NEEDS_COMPLETE_DOCUMENT_FAST`.
That quirk (not inline User-Agent checks) enables a time-bounded,
header-first IMAP search so Horde can close a valid Search envelope in
time. Other clients keep a full IMAP scan. See
[`sync-streaming.md`](sync-streaming.md).

Occasional client-side `SSLHandshakeException` retries before a successful
Sync have also been seen; those failures never reach the HTTP access log.

External report of clipped messages without “View entire”:
[Reddit thread](https://www.reddit.com/r/GMail/comments/1gaz7z1/messages_are_clipped_without_a_view_entire/).

## Nine (9Folders)

| Field | Value |
|-------|-------|
| Observed | 2026-07-30 |
| App | `Nine-WP35_Pro_EEA/UP1A.231005.007` (User-Agent) |
| Device id pattern | `Nine…` (ActiveSync device log may be hex-encoded ASCII of that id) |
| Protocol | 16.0 |

**Default Sync body preference:** HTML (`Type=2`),
`TruncationSize=51200` (50 KB). Messages smaller than that Sync complete;
larger ones arrive with `Truncated=1`.

**Truncated body:** Nine **does** fetch the remainder. After open, a button
at the bottom of the message requests the rest. That issues
`ItemOperations:Fetch` with `Store=Mailbox`, collection id + `ServerEntryId`,
and a `BodyPreference` **without** `TruncationSize` (full body). Horde
responds with the complete body and no `Truncated` flag.

**Implication:** Nine already limits Sync payload itself and can repair
truncation on demand. A server-side forced Sync truncation size is
unnecessary for Nine when left at 0; when set, Nine can still recover via
ItemOperations Fetch (which remains uncapped).

**Mailbox Search:** not captured against this deployment. Nine does not
have `QUIRK_SEARCH_NEEDS_COMPLETE_DOCUMENT_FAST`; mailbox Search keeps a
full IMAP scan, with streaming keep-alives if streaming is enabled.

## iOS Mail

| Field | Value |
|-------|-------|
| Observed | ongoing / author soak (see streaming validation notes) |
| Typical behaviour | small initial Sync body (historically ~500 bytes plain / Type 1) |
| Protocol | commonly 14.1 / 16.x depending on device and server ceiling |

**Truncated body:** iOS Mail re-fetches the full body on open via
ItemOperations (mailbox item fetch). Server-side Sync truncation is therefore
safe for body completeness on iOS, as long as ItemOperations Fetch is not
capped.

Exact default `TruncationSize` can vary by iOS / Mail version; treat the
“small Sync preview + full fetch on open” pattern as the important invariant
rather than a single byte count.

**Mailbox Search:** not captured as `Cmd=Search` in the logs used here.
iOS 16.0 unified search uses the `Find` command (including an All
Mailboxes virtual folder id). iOS does not have
`QUIRK_SEARCH_NEEDS_COMPLETE_DOCUMENT_FAST`; mailbox scans stay
complete. Re-verify `Search` vs `Find` on a current iOS Mail soak
before treating this as settled.

## Summary matrix

| Client | Default TruncationSize (approx.) | Re-fetches truncated body? | Mailbox Search |
|--------|----------------------------------|-----------------------------|----------------|
| Gmail Android | 200000 (HTML) | no | `Cmd=Search`, DeepTraversal, FreeText, Range 0-9; needs a finished document in ~40s |
| Nine | 51200 (HTML) | yes (UI button → ItemOperations) | not observed; full IMAP scan |
| iOS Mail | small preview | yes (on open → ItemOperations) | typically `Find` (EAS 16); full IMAP scan |

## Operator takeaway

- Prefer leaving Sync body size to the client unless you have a measured
  bandwidth reason and understand which devices you serve.
- Forcing a low Sync truncation size saves bandwidth for clients that
  re-fetch (Nine, iOS); forcing a high value can reduce permanent clipping
  on Gmail, which never re-fetches. See forcetruncationsize in Horde
  configuration.
- Full-body-on-demand paths (ItemOperations mailbox Fetch) should stay
  uncapped so well-behaved clients can complete truncated messages.
- Mailbox Search time-budget (`maxsearchtime`) applies only to devices
  with `QUIRK_SEARCH_NEEDS_COMPLETE_DOCUMENT_FAST` (Gmail Android). Other
  clients are not truncated by that cap.
