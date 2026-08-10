# Atlas Sentinel — Threat Detection & Network Containment

**Date:** 2026-08-10
**Status:** Approved (design), pending implementation plan

## Problem

Atlas Sentinel (Windows Service + Tray agent, ~9 devices currently enrolled) monitors fleet health (CPU/RAM/disk/printers/services/network) and self-remediates a fixed set of low-risk issues (service restart, DNS flush, temp cleanup, etc.) via a server-driven remediation dispatcher. It has no visibility into security incidents — a compromised device (malware, participating in a SYN flood/DDoS, or being scanned/flooded itself) currently looks the same as a healthy one on the dashboard, and there is no way to isolate a compromised device from the network without physically walking to it.

Goal: detect malware activity and SYN-flood/DDoS-shaped network behavior on enrolled devices, and automatically isolate a device from the network when a high-confidence threat is found, while keeping IT able to see and release it remotely.

## Non-goals

- **Not building a malware scanning/signature engine.** Enrolled devices already run real AV (Windows Defender; Kaspersky on some laptops). This feature coordinates with existing AV signals rather than duplicating them.
- **Not a kernel-level packet-capture IDS.** True line-rate SYN flood detection needs a WFP driver or raw capture — out of scope for this agent's architecture (user-mode Windows Service, self-contained, self-updating via the existing pipeline). Detection here is connection-table heuristics, which catches loud/damaging attacks, not stealthy low-rate ones.
- **Not solving Kaspersky (KES) integration.** AV-signal ingestion targets Windows Defender only in this phase. KES-managed machines are a known blind spot for the AV-signal source (network-anomaly detection still applies to them). This mirrors the still-deferred KES friction already tracked for this module.
- **No new permission.** Reuses `it.equipment.manage` for all new UI/notifications, per decision below.

## Key architectural decision: containment must be agent-local, not server-round-tripped

Atlas Sentinel's existing remediation flow is: agent reports on checkin (≈20 min cadence, ±2 min jitter) → server evaluates → next checkin picks up instructions → agent executes. That loop is fine for "disk is getting full," not for "this device is mid-flood right now." A high-confidence local signal must be acted on immediately, on the device, without waiting for a server round trip.

Consequently:
- **Detection and the containment decision run entirely on the agent**, evaluated against thresholds/exemption flags the server pushes down periodically (piggybacked on the existing checkin-response config channel — no new delivery mechanism needed for config).
- **The server is the audit/override/visibility layer**, not the real-time decision-maker: it records incidents, lets IT confirm/release, manages the exempt list, and is notified immediately (new lightweight endpoint, not the 20-min checkin) the moment the agent acts.

## Detection sources (agent-side)

### 1. AV-signal ingestion (extends existing code)
`InventoryInfo.cs` already opens `root\SecurityCenter2` via WMI for `AntivirusEnabled`/`AntivirusUpToDate`. Add a second poll against `root\Microsoft\Windows\Defender` → `MSFT_MpThreatDetection` for active detections, plus tail Event Log `Microsoft-Windows-Windows Defender/Operational` IDs 1116 (threat detected) / 1117 (action taken) as a fallback/cross-check. A confirmed active-threat detection from Defender is treated as a high-confidence trigger.

### 2. Network anomaly heuristic (new `NetworkAnomalyDetector.cs`)
Samples the TCP connection table every 30-60s. `.NET`'s `IPGlobalProperties.GetActiveTcpConnections()` doesn't expose connection *state* (SYN_SENT/SYN_RECEIVED vs ESTABLISHED), so this needs a small P/Invoke wrapper around the Win32 IP Helper API (`GetExtendedTcpTable`). Flags:
- High half-open connection count (SYN_SENT/SYN_RECEIVED) from a single process in a short window — covers the device being the *source* of a flood/scan.
- High distinct-remote-IP fan-out in a short window from one process — covers scanning/beaconing behavior.
- High inbound SYN_RECEIVED volume on listening ports — covers the device being the *target* of a flood (less common for a desktop fleet with few listening ports, but covered by the same sampler).

Thresholds are server-configured with conservative defaults, delivered via the checkin response (same shape/pattern as today's config), tunable without a redeploy.

## Containment mechanism (agent-side, new `NetworkContainmentService.cs`)

- Uses `netsh advfirewall firewall add rule` (same shell-out pattern already used in this agent for DNS flush, `sc` config, etc.) to add a rule set named `AtlasSentinelContainment-*`: **block all inbound/outbound except loopback and HTTPS to the Atlas Sentinel server** (resolved server IP + the DNS resolution needed to keep reaching it). This is isolation, not a full NIC kill — the device stays visible and remotely releasable.
- State persisted to `%ProgramData%\BugSaymisIctAgent\containment.json` (`status`, `reason`, `triggered_at`, `expires_at`) so containment **survives reboot** — a compromised device restarting (by the malware or the user) must not silently drop containment. On service start, if `status == contained`, rules are re-applied before anything else runs.
- **While contained, the agent polls the server every ~60s** (it still has its management channel) purely to pick up confirm/release instructions quickly. Normal ~20-min cadence resumes once released.
- **On-screen warning**: a toast via the Tray's existing balloon-notification plumbing, ~30s before lock. **Informational only — no cancel button.** A cancelable warning would let malware (or a bad actor at the keyboard) block its own containment, which defeats the purpose. (Flagged during design review; user did not request a cancel option.)

## Safety net

- **Auto-release timer**: containment auto-releases after a configurable default (30 min) unless an IT staffer confirms the incident first via the dashboard — confirming clears the timer and containment stays until an explicit manual release.
- **Exempt list**: new `containment_exempt` boolean on `ict_equipment_devices`, settable from the dashboard. Exempt devices never auto-contain — they only alert and wait for a manual isolate command, which reuses the existing `IctEquipmentManualRemediationRequest` ("Fix Now") delivery flow already used for manual remediation, so no new command-delivery mechanism is needed.
- **Alerting**: reuses the existing `it.equipment.manage` notification path (bell/push/Soketi via `NotificationService::notifyUser()`), the same one used for stale-device alerts and IT Job Requests. No new permission.

## Server-side additions

- **Migration**: `containment_exempt` boolean on `ict_equipment_devices` (default false).
- **New table** `ict_equipment_containment_incidents`: `device_id`, `reason` (`av_signal` | `network_anomaly`), `detail` (json — e.g. process name, connection counts, Defender threat name), `triggered_at`, `confirmed_by` (nullable user_id), `confirmed_at`, `released_at`, `released_by` (nullable user_id, null = auto-release). Full audit trail; supports multiple incidents per device over time. Chosen over adding columns to `ict_equipment_devices` because a device can have repeat incidents and the history matters.
- **New endpoint** `POST /api/ict-agent/security-incident`: immediate, out-of-band report fired the instant the agent acts (not the 20-min checkin), same device-token (`auth:sanctum` + `ict-agent`) auth as the existing endpoints. Creates the incident row + an `ict_equipment_alerts` row + fires the existing notification path.
- **New alert codes** on the existing `ict_equipment_alerts` table: `malware_detected`, `network_anomaly`, `device_contained`, `device_released`.
- **Checkin-response config**: extend the existing config delivery (same channel `remediations`/config already rides on) with detection thresholds + this device's `containment_exempt` flag, so tuning doesn't require an agent redeploy.
- **Dashboard**: new "Security" panel per device (same UI pattern as the existing Device Backups page) — current containment status, incident history, manual Isolate/Release buttons, exempt toggle. Fleet summary gets a "contained devices" count.
- **Global kill switch**: a config value (default OFF) gating whether auto-containment can actually apply firewall rules fleet-wide, independent of per-device exempt flags — alerts always fire regardless of this switch; only the isolation action is gated. Lets the feature ship in one release while still requiring an explicit flip-on after piloting on 1-2 devices, consistent with how every prior Atlas Sentinel feature (e.g. document backup) was piloted on OCD-OFFICE before wider enablement.

## Data flow summary

1. Agent detectors sample locally (AV WMI/event log poll; TCP connection table poll).
2. On a high-confidence trigger, agent checks its cached `containment_exempt` flag and the (locally cached) global kill-switch state:
   - Exempt or kill switch off → record locally + report on next regular checkin as an alert only, no isolation.
   - Not exempt and kill switch on → apply firewall containment rules immediately, write `containment.json`, show the on-screen toast, then immediately `POST /api/ict-agent/security-incident`.
3. Server creates the incident + alert rows, notifies `it.equipment.manage` holders in real time.
4. While contained, agent polls every ~60s for a confirm/release instruction.
5. IT reviews the Security panel → confirms (stops the timer) or manually releases. If neither happens, auto-release fires at the configured timeout.
6. Agent removes the `AtlasSentinelContainment-*` firewall rules, clears `containment.json`, resumes normal checkin cadence, reports `device_released`.

## Testing

Firewall lockdown behavior cannot be safely exercised on the macOS dev machine (same constraint already documented for the WPF dashboard work) — needs a Windows VM/CI environment where a stuck lockdown doesn't strand a real device. Plan: simulate flood behavior with a local script to trigger the heuristic; verify local detection → firewall rules applied → management channel still reachable → dashboard reflects the incident in near-real-time → auto-release timer fires correctly → manual confirm/release both work. Server-side pieces (migration, new endpoint, dispatcher config, notification) are testable the normal way (PHPUnit, dev container) independent of the agent.

## Rollout

Ships as one release (agent + server together), but the global kill switch defaults OFF — alerting-only until verified on 1-2 pilot devices, then flipped on fleet-wide.
