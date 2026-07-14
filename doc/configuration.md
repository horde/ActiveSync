# Configuring ActiveSync (administrators)

How to enable and operate ActiveSync in a Horde deployment. For embedding the
library in a non-Horde product see [`integration.md`](integration.md); for
internals see [`architecture.md`](architecture.md).

## 1. Enable and configure

In Horde administration → ActiveSync (or `var/config/horde/conf.php`):

```php
$conf['activesync']['enabled'] = true;
$conf['activesync']['version'] = '16.1';   // global protocol ceiling (see below)
$conf['activesync']['storage'] = 'Sql';    // or 'Nosql' (Mongo)
$conf['activesync']['emailsync'] = true;
$conf['activesync']['auth']['type'] = 'basic';
// Optional: per-device version policy instead of per-user permissions
// $conf['activesync']['version_mode'] = 'device';
```

Also configure IMAP/SMTP host hints for Autodiscover, logging path/level, and
Ping heartbeat bounds. Full option descriptions are in
`vendor/horde/horde/config/conf.xml` under the `activesync` tab.

History (`$conf['history']['enabled']`) must be enabled — ActiveSync relies on
it for change timestamps.

## 2. Web server URL

Clients expect `/Microsoft-Server-ActiveSync`. Rewrite that path to Horde's RPC
endpoint, for example:

```
/Microsoft-Server-ActiveSync  →  /horde/rpc.php
```

The RPC layer selects the ActiveSync backend when `server=ActiveSync` is passed
(Apache/nginx configs usually add this; see the
[Horde ActiveSync wiki](http://wiki.horde.org/ActiveSync)).

Autodiscover is served from the same endpoint when the request URI contains
`autodiscover/autodiscover`.

**When Sync response streaming is enabled** (see below), the web server must
not buffer or compress responses on this path — lighttpd `mod_deflate`, nginx
`gzip` / `proxy_buffering`, and similar filters would hold the streamed bytes
back and re-introduce the client timeouts streaming is meant to prevent.

## 3. Client setup

Point the device at your mail domain. With Autodiscover enabled
(`autodiscovery` in config), iOS and Outlook discover the ActiveSync URL
automatically. Otherwise configure the ActiveSync server URL manually.

Authentication is HTTP Basic against Horde by default (`auth.type = basic`).

## 4. Per-user access

Users need the **ActiveSync** permission in Horde. They can manage enrolled
devices under Personal Preferences → ActiveSync (device list and wipe — not
protocol version).

Administrators assign the maximum EAS version per user or group via
**Permissions**, and optionally per device via `hooks.php` when
`version_mode = device` (both described next).

## Protocol version policy

The library supports EAS 2.5 – 16.1 (see
[`protocol-versions.md`](protocol-versions.md) for what each version adds).
Which version a device actually syncs at is bounded by a **ceiling** that
stacks in three layers. None of these are personal *preferences* — users
cannot change their own EAS version; per-user limits are **administrator
permissions**.

### 1. Global ceiling (all users, default)

Set in Horde administration → ActiveSync → *What is the highest version of EAS
that Horde should support?*, or in `conf.php`:

```php
$conf['activesync']['version'] = '16.1';
```

`Horde_Core_Factory_ActiveSyncServer` calls `setSupportedVersion()` with this
value when the server object is created. This is the baseline for every
request.

### 2. Per-user ceiling (permissions)

Default mode: `version_mode` is **`user`** when unset.

Administrators can assign **Maximum ActiveSync protocol version**
(`horde:activesync:version`) per user or group under Horde administration →
Permissions → ActiveSync. Allowed values: `2.5`, `12.0`, `12.1`, `14.0`,
`14.1`, `16.0`, and `16.1`.

On each request, the driver's `versionCallback()` resolves the authenticated
Horde username (from HTTP Basic credentials, the `User` GET parameter, or the
registry) and reads that permission. If set, it calls `setSupportedVersion()`
again for this request only.

| Situation | Effective ceiling for this request |
|-----------|-------------------------------------|
| Permission empty / permission tree not defined | Global `conf['activesync']['version']` only |
| User permission **lower** than global (e.g. user `14.1`, global `16.0`) | User value — caps that user below the site default |
| User permission **higher** than global (e.g. user `16.0`, global `14.1`) | User value — can raise the advertised ceiling above the admin default for that user |
| User in multiple groups with different values | **Lowest** (most restrictive) allowed version |

The last row matters for group-based permissions: if one group allows `16.0`
and another `14.1`, the user syncs at `14.1`.

### 3. Per-device ceiling (hook)

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
`version_mode`: in **`device`** mode with no hook result, no permission
override is applied; switch back to **`user`** mode to use group permissions
as the primary per-principal control.

### Choosing `user` vs `device` mode

| `version_mode` | Source of per-request override |
|----------------|-------------------------------|
| `user` (default) | `horde:activesync:version` permission |
| `device` | `activesync_device_version` hook |

Only one mode is active per installation. Use **permissions** for
account/class-of-user policy; use the **hook** when the device ID is the right
key (e.g. force an old Outlook build to `14.1` while everyone else stays on
`16.0`).

## Sync response delivery (streaming)

Some clients — notably Gmail on Android — abort a `Sync` connection after
~30 seconds without response body bytes, and can end up in a broken sync
state. Streaming delivery sends WBXML incrementally so bytes keep flowing
while the server works, in both directions (message export *and* import of
client-sent changes). The complete design, error model, and rationale are in
[`sync-streaming.md`](sync-streaming.md); this section covers the operator
view.

All keys live under `$conf['activesync']['sync']` (Horde administration →
ActiveSync → *Sync Response Delivery*):

| Key | Default | Meaning |
|-----|---------|---------|
| `streaming` | `false` | Master switch for streaming Sync delivery |
| `maxmessagesperresponse` | `10` | Count cap per response when streaming; more changes are announced via `MoreAvailable`. `0` = window size only |
| `maxmessagetime` | `0` | Soft cap (seconds) for assembling a single message; stops the batch after a slow message. Streaming only. `0` = off |
| `maxrequestduration` | `0` | Whole-request wall clock cap (seconds), measured from request start (includes import of client changes). Streaming only. `0` = off |
| `keepaliveinterval` | `15` | Minimum seconds between WBXML keep-alive tokens during import of client changes. `0` = one token per imported command |
| `maxresponsetime` | `25` | **Legacy** export-phase time budget; only honored when `streaming` is `false` |

Rollback: set `streaming = false` to restore the buffered `Content-Length`
behaviour (including the `maxresponsetime` budget) with no other changes.

### Operator notes

- The Sync handler logs
  `SYNC: starting response output N.Ns after request start (streaming on|off)`
  at INFO level — use it to verify streaming is active and to measure time
  to first byte. Up-sync batches additionally log
  `Queued N incoming changes for deferred import (streaming).` and
  `SYNC: imported N deferred incoming change(s) for collection F… in N.Ns,
  N keep-alive(s) emitted`.
- Web-server-level buffering or compression on
  `/Microsoft-Server-ActiveSync` can re-introduce the timeout even with
  streaming enabled — PHP cannot disable it from inside the request. Exclude
  the ActiveSync path from response buffering/compression (see section 2).
- Streaming trades slightly more HTTP round-trips (smaller batches with
  `MoreAvailable`) for reliability and lower peak memory.
- A device already stuck from earlier timeouts may still need one account
  re-add (or server-side device state removal) — streaming prevents the
  breakage, it does not repair broken client state.

## Logging and troubleshooting

- Enable protocol logging (`logging.level`, and the `perdevice` log type for
  one file per device) when debugging client issues. The logger records
  command names, collection IDs, decoded WBXML structure, and metadata
  without dumping full message bodies at low levels.
- Per-device logs are the primary debugging tool: they contain the decoded
  incoming request (`I:` lines), the outgoing response (`O:` lines), and
  state/collection diagnostics in between.
- Device block/allow decisions can be made via Horde `hooks.php`.
- Remote wipe (full, and account-only for EAS 16.1 devices) is managed from
  the admin and user device lists.
