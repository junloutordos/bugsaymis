#!/usr/bin/env bash
# =============================================================================
# BugsayMIS — Staging Network Access Fix
# Adds a persistent static route so the production server can route return
# traffic back to the 10.10.11.0/24 (staging) subnet, allowing MySQL
# connections from 10.10.11.35 to succeed.
#
# Run as root or with sudo:
#   sudo bash scripts/staging-network-fix.sh
# =============================================================================
set -euo pipefail

STAGING_SUBNET="10.10.11.0/24"
GATEWAY="10.10.12.1"
IFACE="ens33"
NM_CONN="netplan-ens33"

echo "[1/4] Adding immediate (non-persistent) route to ${STAGING_SUBNET}..."
if ip route show | grep -q "${STAGING_SUBNET}"; then
    echo "      Route already exists — skipping."
else
    ip route add "${STAGING_SUBNET}" via "${GATEWAY}" dev "${IFACE}"
    echo "      Done."
fi

echo "[2/4] Persisting route via NetworkManager..."
if nmcli connection show "${NM_CONN}" | grep -q "10.10.11.0"; then
    echo "      Persistent route already configured — skipping."
else
    nmcli connection modify "${NM_CONN}" \
        +ipv4.routes "${STAGING_SUBNET} ${GATEWAY}"
    echo "      Done."
fi

echo "[3/4] Allowing port 3306 from ${STAGING_SUBNET} in firewall..."
# Docker uses docker-proxy for specific-IP port bindings, so traffic hits
# the INPUT chain (not FORWARD/DOCKER-USER). Both chains need the rule.

# INPUT chain — covers docker-proxy connections
if iptables -C INPUT -s "${STAGING_SUBNET}" -p tcp --dport 3306 -j ACCEPT 2>/dev/null; then
    echo "      INPUT rule already exists — skipping."
else
    iptables -I INPUT 1 -s "${STAGING_SUBNET}" -p tcp --dport 3306 -j ACCEPT
    echo "      INPUT rule added."
fi

# DOCKER-USER chain — covers direct DNAT path (belt-and-suspenders)
if iptables -C DOCKER-USER -s "${STAGING_SUBNET}" -j ACCEPT 2>/dev/null; then
    echo "      DOCKER-USER rule already exists — skipping."
else
    iptables -I DOCKER-USER 1 -s "${STAGING_SUBNET}" -j ACCEPT
    echo "      DOCKER-USER rule added."
fi

echo "[4/4] Verifying connectivity to staging (10.10.11.35)..."
if ping -c 2 -W 2 10.10.11.35 &>/dev/null; then
    echo "      Ping OK — routing is working."
else
    echo "      Ping unreachable from this side — normal if gateway does one-way routing."
    echo "      TCP from staging to production should still work."
fi

echo ""
echo "Done. Test from the staging Windows machine:"
echo "  Test-NetConnection 10.10.12.10 -Port 3306"
echo ""
echo "NOTE: iptables rules are not persistent across reboots."
echo "      To persist them:"
echo "        sudo apt install -y iptables-persistent"
echo "        sudo netfilter-persistent save"
