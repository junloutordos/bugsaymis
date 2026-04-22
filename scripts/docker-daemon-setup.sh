#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# Docker daemon hardening — prevent Docker from bypassing UFW iptables rules
# Run as: sudo bash scripts/docker-daemon-setup.sh
# WARNING: This restarts Docker. All containers will restart (restart: unless-stopped handles it).
# ─────────────────────────────────────────────────────────────────────────────

set -e

# Create /etc/docker/daemon.json to:
# 1. Disable Docker's iptables manipulation (so UFW rules are respected)
# 2. Restrict logging to prevent disk fill
# 3. Set container log rotation

cat > /etc/docker/daemon.json << 'EOF'
{
  "iptables": false,
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}
EOF

echo "[*] Docker daemon.json written."
echo "[*] Restarting Docker..."
systemctl restart docker

echo "[*] Waiting for Docker to come back up..."
sleep 5

echo "[*] Starting BugsayMIS containers..."
cd /home/bugsaymis/bugsaymis
docker compose up -d

echo ""
echo "✅ Docker hardening complete."
echo "   - Docker no longer bypasses UFW iptables rules"
echo "   - Container logs capped at 10MB x 3 files per container"
echo ""
echo "⚠️  IMPORTANT: After running this script, also run:"
echo "   sudo bash scripts/firewall-setup.sh"
echo "   This ensures UFW rules take full effect now that Docker respects them."
