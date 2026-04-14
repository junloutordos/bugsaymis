#!/usr/bin/env bash
# =============================================================================
# BugsayMIS — Persistent Network Rules for Staging Access
# Runs as a systemd service AFTER Docker, so rules survive both VM reboots
# and Docker restarts (which re-write iptables chains).
# =============================================================================

STAGING_SUBNET="10.10.11.0/24"
GATEWAY="10.10.12.1"
IFACE="ens33"

logger -t bugsaymis-network "Starting network rules for staging access..."

# ── Static route to staging subnet ───────────────────────────────────────────
if ! ip route show | grep -q "${STAGING_SUBNET}"; then
    ip route add "${STAGING_SUBNET}" via "${GATEWAY}" dev "${IFACE}"
    logger -t bugsaymis-network "Route ${STAGING_SUBNET} via ${GATEWAY} added."
else
    logger -t bugsaymis-network "Route ${STAGING_SUBNET} already present."
fi

# ── Allow MySQL (3306) from staging subnet in INPUT chain ────────────────────
# (docker-proxy listens on INPUT, not FORWARD)
if ! iptables -C INPUT -s "${STAGING_SUBNET}" -p tcp --dport 3306 -j ACCEPT 2>/dev/null; then
    iptables -I INPUT 1 -s "${STAGING_SUBNET}" -p tcp --dport 3306 -j ACCEPT
    logger -t bugsaymis-network "iptables INPUT rule for 3306 added."
fi

# ── Allow all traffic from staging in DOCKER-USER chain ──────────────────────
# Wait up to 10s for Docker to create its iptables chains before checking
for i in $(seq 1 10); do
    iptables -L DOCKER-USER -n &>/dev/null && break
    sleep 1
done

if ! iptables -C DOCKER-USER -s "${STAGING_SUBNET}" -j ACCEPT 2>/dev/null; then
    iptables -I DOCKER-USER 1 -s "${STAGING_SUBNET}" -j ACCEPT
    logger -t bugsaymis-network "iptables DOCKER-USER rule added."
else
    logger -t bugsaymis-network "iptables DOCKER-USER rule already present."
fi

logger -t bugsaymis-network "Network rules applied successfully."
