# ActiveSync operations monitor

See the [operations monitor epic](https://github.com/horde/ActiveSync/issues/99).

## Purpose and scope

Version 1 provides a shared, read-only health model for answering:

1. Which devices are active right now?
2. Which sync keys or collections look stuck?
3. Where are loop-guard or heartbeat problems concentrating?
4. How can an operator jump from a bad device to its per-device log and
   existing admin actions?

The monitor does not provide historical metrics, alerting, or webhooks. It
does not add HTTP 503 responses or `X-MS-Throttle`, replace
`logging.type = perdevice`, mutate ActiveSync protocol behaviour, or expose
wipe and block operations through the CLI.

## Architecture

| Piece | Package and location |
|-------|----------------------|
| Health heuristics and DTOs | `horde/activesync`, `src/Ops` |
| Snapshot service | `horde/core` |
| CLI `horde-activesync summary\|show\|top` | `horde/horde` (the `horde/base` repository) |
| Admin health badges | `horde/horde` (the `horde/base` repository) |

The evaluator consumes plain facts and has no storage dependency.
`DeviceHealthFactory` is the compatibility boundary that converts legacy
ActiveSync device, cache, and state rows into those facts. The snapshot
service loads fleet state, and both operator interfaces consume its shared
results.

## Health model

Device and collection status is one of `ok`, `warn`, or `critical`. The worst
signal determines collection status, and the worst device or collection
status determines device status.

A device is active when a heartbeat is inferred to be in flight, or when its
latest SyncCache or last-sync timestamp is no older than `activeWithin`.
A device is stuck when it has `hb_stuck`, `hb_missing_end`, `fsr_critical`, or
`backlog_stuck` on either the device or one of its collections.

| Tuning option | Default | Meaning |
|---------------|---------|---------|
| `now` | current time | Evaluation timestamp; fixed values make tests and snapshots deterministic |
| `activeWithin` | 300 seconds | Maximum state age counted as active |
| `hbStuckAfter` | derived | Explicit heartbeat-stuck threshold, when set |
| `hbDefaultInterval` | 900 seconds | Heartbeat interval used when the cache has none |
| `hbSlack` | 60 seconds | Allowance added to the heartbeat interval |
| `hbAbandonedAfter` | 2 × heartbeat-stuck threshold | Age after which an unfinished heartbeat is treated as abandoned rather than stuck |
| `fsrWarnAt` | 3 | FolderSync-required warning threshold |
| `fsrCriticalAt` | 5 | FolderSync-required critical threshold |
| `backlogGrace` | 60 seconds | Minimum backlog age before a pending warning |
| `backlogTriggerMax` | 3 | Backlog recovery count considered stuck |
| `backlogAbandonedAfter` | 3600 seconds | Age after which a capped backlog is treated as abandoned rather than stuck |

When `hbStuckAfter` is unset, the threshold is the cached heartbeat interval
(or `hbDefaultInterval`) plus `hbSlack`.

## Reason codes

| Code | Condition | Severity | What to do |
|------|-----------|----------|------------|
| `hb_in_flight` | Heartbeat start is newer than its end, or no end is recorded, and its age is at most the stuck threshold | `ok` | No action; this is activity context |
| `hb_stuck` | Heartbeat start is newer than a recorded end and its age is between the stuck threshold and the abandoned threshold | `warn` | Check whether the client recently disconnected, suspended radio activity, rebooted, or updated; inspect the device log if the signal persists |
| `hb_missing_end` | A heartbeat start has no recorded end and its age is between the stuck threshold and the abandoned threshold | `warn` | Check for a transient client disconnect; inspect the device log if the signal persists |
| `hb_abandoned` | An unfinished heartbeat is older than the abandoned threshold | `ok` | Nothing — common after client disconnects; if it repeats for an otherwise active device, check the device log |
| `fsr_warn` | FolderSync-required count is at least 3 and below 5 by default | `warn` | Check whether the client completes a FolderSync cycle |
| `fsr_critical` | FolderSync-required count is at least 5 by default | `critical` | Inspect hierarchy state and the device log. The counter clears on FolderSync, or on a later successful PING/SYNC that does not request FolderSync (after ghost-collection heal) |
| `blocked` | Device properties mark the device blocked | `warn` | Confirm the block is intentional in the admin device page |
| `wipe_pending` | Full or account-only remote wipe is pending | `warn` | Confirm provisioning reaches the device |
| `wipe_complete` | Full or account-only remote wipe is recorded complete | `warn` | Confirm the completed state is expected |
| `backlog_pending` | Backlog age reached the grace period and recovery count remains below the trigger maximum | `warn` | Check whether the client drains subsequent Sync windows |
| `backlog_stuck` | A backlog exists, recovery count reached the trigger maximum, and the MOREAVAILABLE is still newer than `backlogAbandonedAfter` | `critical` | Inspect collection sync keys and the per-device log |

The numeric conditions use the configured thresholds rather than fixed
defaults when options are overridden.

## Honest limits

Version 1 is derived from currently persisted device and SyncCache state; it
cannot prove that a PHP request is still running. An unfinished heartbeat is
therefore a warning rather than critical: a radio gap, client update, reboot,
or suspended app can leave the same persisted timestamps as a server-side
problem. An old unfinished heartbeat is treated as abandoned, not stuck,
because a PHP request cannot plausibly remain active beyond that window.
The initial fleet snapshot costs one
SyncCache load per device; it is not a constant-cost aggregate query.
Per-device protocol logs remain the deep-dive source.

## Cookbook (CLI + admin)

### Triage active devices

Start with active devices ordered by health and keep the display refreshed:

```sh
horde-activesync top --watch --active-within=300 --sort=health
```

Narrow a large fleet with `--user`, `--device`, `--health`, or
`--stuck-only`, and cap output with `--limit`. Use `--format` when another
tool consumes the result. The snapshot performs one SyncCache read per device
considered after filtering; it is not a constant-cost fleet aggregate.

### Run a cron or Nagios check

```sh
horde-activesync summary --format=json
```

The command exits with `0` when no critical device is present, `1` when at
least one critical device is present, and `2` when monitoring data is
unavailable. Cron and Nagios wrappers should preserve that exit status and
retain the JSON output for diagnosis.

### Inspect one device

Use the exact user and device identifiers shown by `top` or `summary`:

```sh
horde-activesync show alice@example.com DEVICE1
```

When `logging.type = perdevice`, the protocol log is:

```text
{configured logging path}/{UPPERCASE DEVICE ID}.txt
```

For example, device `DEVICE1` maps to `DEVICE1.txt` beneath the configured
logging path. The path comes from the deployment configuration; do not embed
credentials or deployment-specific paths in scripts or reports.

### Confirm and act in the admin page

Open **Administration → ActiveSync Devices**, find the same user and device,
and verify the health signal against the device details and per-device log.
Only then use the existing wipe, block, remove, or reset actions, with care:
they change device state and can disrupt or erase client data. The
`horde-activesync` CLI has no mutation commands.

### Interpret common signals

- `hb_stuck` and `hb_missing_end` are warnings for an unfinished heartbeat
  still inside the plausible stuck window. Transient client disconnects,
  radio gaps, reboots, and updates commonly produce this state; inspect the
  request lifecycle and device log only when it persists or repeats.
  `hb_abandoned` is older than that window and is intentionally `ok`
  because the original request can no longer plausibly be running. Repeated
  abandoned heartbeats on an otherwise active device still merit log review.
- `backlog_stuck` means a collection has repeatedly failed to drain pending
  changes *and* that MOREAVAILABLE is still recent. After
  `backlogAbandonedAfter` (or after PING/looping-SYNC recovery hits its
  trigger cap and clears the flag), an undrained folder is no longer
  fleet-critical: leftover `sync_pending` waits for the next real SYNC, which
  some clients never send for folders they only PING (for example iOS Mail
  and Trash). Inspect sync keys and the log only while the signal is present.
  `fsr_warn` / `fsr_critical` instead mean repeated FolderSync-required
  recovery (FSR). After ghost collections are healed, a later successful
  PING/SYNC clears that counter even if the client never FolderSyncs again.
