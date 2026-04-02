#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# Fail2Ban setup for BugsayMIS
# Run as: sudo bash scripts/fail2ban-setup.sh
# ─────────────────────────────────────────────────────────────────────────────

set -e

echo "[*] Installing Fail2Ban..."
apt-get install -y fail2ban

echo "[*] Writing Fail2Ban jail config..."

# Nginx rate-limit jail — bans IPs that get 429 Too Many Requests (brute force on login)
cat > /etc/fail2ban/jail.d/bugsaymis.conf << 'EOF'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 10
banaction = ufw

[nginx-req-limit]
enabled  = true
port     = http,https
filter   = nginx-req-limit
logpath  = /var/lib/docker/containers/*/*-json.log
maxretry = 5
findtime = 5m
bantime  = 30m

[sshd]
enabled  = true
port     = ssh
maxretry = 5
findtime = 5m
bantime  = 1h
EOF

# Create the nginx req-limit filter
cat > /etc/fail2ban/filter.d/nginx-req-limit.conf << 'EOF'
[Definition]
failregex = ^.*\[error\].*limiting requests.*client: <HOST>.*
            ^{"log":".*status\":429.*\"ClientIP\":\"<HOST>\".*
ignoreregex =
EOF

echo "[*] Enabling and starting Fail2Ban..."
systemctl enable fail2ban
systemctl restart fail2ban

echo "[*] Fail2Ban status:"
fail2ban-client status

echo ""
echo "✅ Fail2Ban configured."
echo "   - Bans IPs after 5 rate-limit violations (10 min window, 30 min ban)"
echo "   - SSH protected: 5 failed attempts = 1 hour ban"
echo "   - Unban an IP: sudo fail2ban-client set nginx-req-limit unbanip <IP>"
