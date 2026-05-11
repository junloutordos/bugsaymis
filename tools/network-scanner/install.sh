#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# CRCMIS Network Scanner — Ubuntu VM Install Script
# Run as root: sudo bash install.sh
# ─────────────────────────────────────────────────────────────────────────────
set -e

INSTALL_DIR="/opt/crcmis-scanner"
SERVICE_NAME="crcmis-scanner"

echo ""
echo "========================================"
echo "  CRCMIS Network Scanner Installer"
echo "========================================"
echo ""

# 1. Install dependencies
echo "[1/5] Installing Python dependencies..."
apt-get update -qq
apt-get install -y python3 python3-pip python3-venv curl

# 2. Create install directory
echo "[2/5] Setting up install directory..."
mkdir -p "$INSTALL_DIR"
cp scanner.py "$INSTALL_DIR/"

# 3. Download OUI database (MAC vendor lookup)
echo "[3/5] Downloading OUI vendor database..."
curl -s "https://standards-oui.ieee.org/oui/oui.txt" -o "$INSTALL_DIR/oui_raw.txt" 2>/dev/null || \
  echo "  (OUI download failed — vendor names will be empty, scanner still works)"

# Parse OUI into tab-separated format: AABBCC\tVendor Name
if [ -f "$INSTALL_DIR/oui_raw.txt" ]; then
  grep "(hex)" "$INSTALL_DIR/oui_raw.txt" | \
    sed 's/\s*(hex)\s*/\t/' | \
    sed 's/-//g' | \
    awk '{print $1"\t"substr($0, index($0,$2))}' \
    > "$INSTALL_DIR/oui.txt"
  echo "  OUI database: $(wc -l < "$INSTALL_DIR/oui.txt") vendors loaded."
fi

# 4. Create Python venv and install packages
echo "[4/5] Installing Python packages..."
python3 -m venv "$INSTALL_DIR/venv"
"$INSTALL_DIR/venv/bin/pip" install --quiet pysnmp requests

# 5. Create config from example
if [ ! -f "$INSTALL_DIR/config.json" ]; then
  cp config.example.json "$INSTALL_DIR/config.json"
  echo ""
  echo "  *** IMPORTANT: Edit $INSTALL_DIR/config.json before starting the service ***"
  echo "  Set: snmp.host (WS6008 IP), snmp.community, crcmis.api_token"
  echo ""
fi

# 6. Create systemd service
echo "[5/5] Installing systemd service..."
cat > "/etc/systemd/system/${SERVICE_NAME}.service" << EOF
[Unit]
Description=CRCMIS Network Scanner
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=${INSTALL_DIR}/venv/bin/python3 ${INSTALL_DIR}/scanner.py
WorkingDirectory=${INSTALL_DIR}
User=root
StandardOutput=journal
StandardError=journal
EOF

# 7. Create systemd timer (runs every 10 minutes)
cat > "/etc/systemd/system/${SERVICE_NAME}.timer" << EOF
[Unit]
Description=CRCMIS Network Scanner Timer
Requires=${SERVICE_NAME}.service

[Timer]
OnBootSec=2min
OnUnitActiveSec=10min
Unit=${SERVICE_NAME}.service

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable "${SERVICE_NAME}.timer"

echo ""
echo "========================================"
echo "  Installation complete!"
echo "========================================"
echo ""
echo "Next steps:"
echo "  1. Edit /opt/crcmis-scanner/config.json"
echo "     - snmp.host:         IP address of Ruijie WS6008"
echo "     - snmp.community:    SNMP community string (configured in WS6008)"
echo "     - crcmis.api_token:  Bearer token from CRCMIS admin panel"
echo ""
echo "  2. Test the scanner manually:"
echo "     /opt/crcmis-scanner/venv/bin/python3 /opt/crcmis-scanner/scanner.py"
echo ""
echo "  3. Start the timer:"
echo "     sudo systemctl start crcmis-scanner.timer"
echo ""
echo "  4. Check status:"
echo "     sudo systemctl status crcmis-scanner.timer"
echo "     sudo journalctl -u crcmis-scanner.service -f"
echo ""
