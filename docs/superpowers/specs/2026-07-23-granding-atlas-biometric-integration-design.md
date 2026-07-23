# Real-Time Granding GT200 → DTR Integration via Atlas Sentinel

**Date:** 2026-07-23
**Status:** Approved (design phase) — pending implementation plan

## Problem

The guardhouse Granding GT200 biometric fingerprint terminal is the physical
punch device for employee time-in/time-out. Today, getting those punches
into DTR is fully manual: someone plugs a USB drive into the device, exports
an attendance log, walks it to a PC, and uploads it through the existing
`HR/Biometric` web page (`BiometricLogController::upload` →
`BiometricImportService::parse`). This means DTR records lag physical
punches by however long it takes for that manual export/upload cycle to
happen (often hours, sometimes a full day), and HR/Administrator/OCD have no
visibility into gate activity as it happens.

## Goal

When an employee punches at the guardhouse device, the punch should land in
their DTR record automatically, within seconds, with no manual file
handling — and HR/Administrator/OCD should be able to watch punches arrive
live.

## Why reuse Atlas Sentinel

Atlas Sentinel (the Windows agent in `~/bugsaymis-ict-agent`, deployed
fleet-wide for ICT equipment monitoring) already solves every infrastructure
problem this integration needs:

- Device enrollment → long-lived Sanctum `device_token`, scoped so a device
  can only act as itself (`EnsureAtlasSentinelDevice`).
- Outbound-only HTTPS from the campus LAN to the backend — no inbound
  exposure, no VPN, and it sidesteps Cloudflare/WAF entirely (the biometric
  payload will be small JSON, not a file upload, so the WAF's
  `multipart/form-data` block never comes into play).
- A precedent for the backend telling an enrolled device to activate a
  capability via the checkin response (`BackupInstruction` already does
  this for the Google Drive document-backup feature) — the same pattern
  will activate the biometric bridge.
- Resilience, retry, and self-update machinery already hardened in
  production.

Building a separate bridge (e.g. a dedicated Raspberry Pi service) would
duplicate all of this for no benefit, and contradicts the existing
convention of extending Atlas Sentinel for new campus-edge capabilities.

## Device capability (confirmed)

Model: **Granding GT200**. Per manufacturer specs, it supports RS232/485,
USB, and **TCP/IP** communication, ships with a free SDK, and has built-in
**"web-based server functionality with ADMS function"** — i.e. native
push-to-server support. This means no reverse-engineered protocol is
required; ADMS is a first-class, documented device feature.

Sources:
- https://www.granding.com/gt200
- https://www.grandingteco.com/web-based-biometric-fingerprint-time-attendance-system-with-3g-network-gt200-product/

## Architecture

```
Guardhouse GT200 (LAN, Cloud/ADMS Server = guardhouse PC's IP:port)
   → pushes attlog-format punch data over HTTP (device-native ADMS push)
   → Atlas Sentinel agent on guardhouse PC (new AdmsReceiverWorker, LAN-bound listener)
   → relays parsed punch as JSON over the agent's existing outbound HTTPS channel
   → POST /api/ict-agent/biometric-punches (new endpoint, existing auth:sanctum + ict-agent middleware)
   → inserts into biometric_logs (source='api', dedup via existing unique index)
   → DTRService::generate() runs immediately for that user/day
   → BiometricPunchRecorded event broadcasts on a private channel via existing Echo/Soketi
   → HR/Administrator/OCD live feed page updates instantly
```

### Approach chosen: device pushes (ADMS), agent receives

Two approaches were considered; both share the identical backend/frontend
design, differing only in how the agent obtains punches from the device:

- **A — Agent pulls via TCP/IP SDK** (fallback): agent polls the device
  every ~15–20s using Granding's free SDK. Requires implementing/porting a
  vendor SDK client and a watermark/offset tracking loop.
- **B — Device pushes via ADMS (chosen)**: device is configured with a
  Cloud Server target; agent runs a small local HTTP receiver implementing
  the standard ADMS push endpoint. Lower latency, no persistent
  socket/reconnect state machine, and uses a device feature that's already
  first-class rather than an unofficial polling protocol.

If on-site testing reveals the device's ADMS implementation is incompatible
with the receiver, Approach A is the documented fallback — no backend or
frontend rework needed, only the agent's collection mechanism changes.

## Phase 0 — on-site setup (non-code, prerequisite)

1. Connect the GT200 to the guardhouse LAN (Ethernet, or WiFi if no port).
2. Assign it a static/reserved LAN IP.
3. In the device's Comm/Network menu, set Cloud Server Setting (ADMS) to
   the guardhouse PC's LAN IP + a chosen port (e.g. 8090).
4. Ensure the guardhouse PC has Atlas Sentinel installed and enrolled as
   ICT equipment (same enrollment flow as any other campus machine).

## Component design

### Atlas Sentinel agent (`~/bugsaymis-ict-agent`)

- **`BiometricBridgeInstruction`** — new optional field on the existing
  `CheckinResponse`, following the same pattern as `BackupInstruction`.
  From the ICT Equipments admin page, marking a piece of enrolled equipment
  as "Biometric Bridge" (with a device name/location label, e.g. "Main Gate
  Guardhouse") is what activates this on the agent — no manual per-machine
  config file.
- **`AdmsReceiverWorker`** — new background worker that, when a bridge
  instruction is active, opens a small local HTTP listener bound to the LAN
  interface implementing the device's ADMS push endpoint. Parses incoming
  attlog lines using a parser shared with the backend's existing format
  knowledge (see below), tracks a local watermark/dedup file (same idea as
  the existing `UpdateMarkerStore.cs`) so a device retry or an agent
  restart can't double-post, and relays parsed records to the backend via
  `IctAgentApiClient` using the same device-token bearer auth already used
  for checkin/enroll/backup.
- Bridge health (last successful relay timestamp) rides along on the
  normal 20-minute checkin, so the ICT Equipments page can show the bridge
  as online/stale the same way every other monitored capability does today.

### Backend (Laravel)

- New route: `POST /api/ict-agent/biometric-punches`, inside the existing
  `Route::middleware(['auth:sanctum', 'ict-agent'])` group in
  `routes/api.php` — reuses the exact auth already protecting
  checkin/inventory-checkin/backup endpoints.
- **`AttlogLineParser`** extracted from `BiometricImportService` so the
  manual file-upload path and the new live-push path share one
  implementation of the Granding attlog format — no duplicated format
  knowledge, no drift between the two ingestion paths.
- Inserts follow the existing dedup-safe pattern already used by
  `BiometricImportService::batchInsert()` — the `bio_log_unique` index
  (`device_employee_id`, `log_datetime`, `device_id`) prevents duplicates
  even if the agent retries a push.
- On each newly-resolved punch, calls `DTRService::generate($userId, today,
  today)` — identical to what the manual upload flow already does.
- New `BiometricPunchRecorded` event (`ShouldBroadcast`) on a private
  channel `private-hr.biometric-feed`.
- New permission `hr.biometric.monitor` (view-only live feed), separate
  from the existing `hr.biometric.manage` (resolve/import), so
  OCD/Administrator can watch the feed without gaining resolve/import
  rights. Follows the project's `module.submodule.action` permission
  convention.
- Optional additive migration: a small `biometric_devices` table (id,
  label, device_id key, linked ICT equipment, last_seen_at) purely for
  human-readable naming on the live feed and the bridge-health badge.
  Nullable/additive only — no changes to `biometric_logs`, which already
  has `source='api'` and `device_id` ready for this.

### Frontend (Vue/Inertia)

- New live feed view (tab on `HR/Biometric/Index.vue`, or a small
  dedicated page) subscribing via
  `Echo.private('hr.biometric-feed').listen(...)`, prepending new punches
  as they arrive: employee name/photo if resolved, badge ID + "unresolved"
  flag if not, timestamp in `en-PH` locale, device/location label.
- Gated by `hr.biometric.monitor` for viewing; existing
  `hr.biometric.manage` continues to gate resolve/import actions,
  unchanged.

## Edge cases

- **Unresolved badge** (no matching `badge_id`) still appears live, flagged
  the same way the manual-upload path already flags it; existing resolve
  workflow (`BiometricLogController::resolve`) is unchanged and applies
  equally to API-sourced logs.
- **Device/agent offline** — the GT200 has local storage and a built-in
  battery, so punches queue on the device itself and push once
  connectivity resumes. No data loss; worst case is a delay, which is
  still strictly better than the current manual-USB cadence.
- **Duplicate punches** (e.g. double policy tap) — already deduped by the
  existing unique constraint, reinforced by the agent's own watermark file.
- **Agent restart mid-relay** — watermark file plus the DB unique
  constraint together prevent double-insertion.

## Testing

- **Backend**: feature tests for the new endpoint — auth (device-token
  required, rejects non-biometric-bridge devices), validation, dedup
  behavior, DTR generation trigger, and broadcast dispatch — following the
  existing `tests/Feature/FacultyLoading/FacultyLoadingHttpTest.php`-style
  conventions already used in this codebase. Unit test for the extracted
  `AttlogLineParser` covering the existing known formats plus edge cases
  (BOM, tab vs space delimiting, letter vs numeric check-type codes).
- **Agent**: unit tests for the ADMS payload parser reuse and the
  watermark/dedup logic, in the agent's own test project.

## Out of scope for this design

- Multiple guardhouses/devices — the `device_id` column and
  `biometric_devices` table already generalize to more than one terminal,
  but wiring up a second physical device is not part of this work.
- "Who's currently on/off campus" roster view and device-health alerting
  beyond the basic online/stale badge — deferred; the live punch feed was
  the explicitly requested scope.
- Replacing the manual file-upload path — it remains as a fallback/backfill
  tool (e.g. for historical data or if the bridge is temporarily down).
