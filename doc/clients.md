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
- Other quirks that affect server design or operator expectations

EAS is client-driven: the server cannot push the remainder of a truncated
body. A client that never re-fetches treats Sync truncation as permanent.

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

**Related:** Gmail can abort a long `Sync` if the server is silent for
~30 seconds — see [`sync-streaming.md`](sync-streaming.md). Occasional
client-side `SSLHandshakeException` retries before a successful Sync have
also been seen; those failures never reach the HTTP access log.

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

## Summary matrix

| Client | Default TruncationSize (approx.) | Re-fetches truncated body? |
|--------|----------------------------------|----------------------------|
| Gmail Android | 200000 (HTML) | no |
| Nine | 51200 (HTML) | yes (UI button → ItemOperations) |
| iOS Mail | small preview | yes (on open → ItemOperations) |

## Operator takeaway

- Prefer leaving Sync body size to the client unless you have a measured
  bandwidth reason and understand which devices you serve.
- Forcing a low Sync truncation size saves bandwidth for clients that
  re-fetch (Nine, iOS); forcing a high value can reduce permanent clipping
  on Gmail, which never re-fetches. See forcetruncationsize in Horde
  configuration.
- Full-body-on-demand paths (ItemOperations mailbox Fetch) should stay
  uncapped so well-behaved clients can complete truncated messages.
