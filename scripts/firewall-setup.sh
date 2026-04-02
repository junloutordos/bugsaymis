#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# BugsayMIS Firewall Hardening Script
# Run as: sudo bash scripts/firewall-setup.sh
# ─────────────────────────────────────────────────────────────────────────────

set -e

echo "[*] Resetting UFW to defaults..."
ufw --force reset

echo "[*] Setting default policies: deny incoming, allow outgoing..."
ufw default deny incoming
ufw default allow outgoing

echo "[*] Allowing SSH (22) — adjust if you use a non-standard port..."
ufw allow 22/tcp comment 'SSH'

echo "[*] Allowing HTTP and HTTPS (public web)..."
ufw allow 80/tcp  comment 'HTTP → HTTPS redirect'
ufw allow 443/tcp comment 'HTTPS (Cloudflare/production)'

echo "[*] Allowing phpMyAdmin from local network ONLY (192.168.x.x / 10.x.x.x)..."
# Adjust these if your local network range is different
ufw allow from 10.10.0.0/16 to any port 8081 comment 'phpMyAdmin (LAN only)'
ufw allow from 192.168.0.0/16 to any port 8081 comment 'phpMyAdmin (LAN only)'

echo "[*] Blocking 3306 (MySQL) from public — internal Docker only..."
# 3306 is no longer exposed to the host in docker-compose.yml
# This rule is a belt-and-suspenders block in case it's accidentally re-exposed
ufw deny 3306/tcp comment 'MySQL - block external access'

echo "[*] Blocking 9601 (Soketi metrics) — internal Docker only..."
ufw deny 9601/tcp comment 'Soketi metrics - block external access'

echo "[*] Enabling UFW..."
ufw --force enable

echo "[*] Final UFW status:"
ufw status verbose

echo ""
echo "✅ Firewall hardening complete."
echo "   Allowed: 22 (SSH), 80 (HTTP), 443 (HTTPS), 8081 from LAN only"
echo "   Blocked: 3306, 9601, all others"
