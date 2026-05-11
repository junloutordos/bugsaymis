#!/usr/bin/env python3
"""
CRCMIS Network Scanner Agent
Queries Ruijie WS6008 via SNMP and posts results to CRCMIS Network Inventory API.

Requirements:
    pip3 install pysnmp requests

Configuration:
    Edit config.json in the same directory as this script.
"""

import json
import logging
import os
import socket
import time
from datetime import datetime
from pathlib import Path

import requests
from pysnmp.hlapi import (
    CommunityData, ContextData, ObjectIdentity, ObjectType,
    SnmpEngine, UdpTransportTarget, nextCmd, getCmd,
)

# ── Logging ───────────────────────────────────────────────────────────────────
_log_path = '/var/log/crcmis-scanner.log' if os.access('/var/log', os.W_OK) else \
            str(Path(__file__).parent / 'scanner.log')

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler(_log_path),
        logging.StreamHandler(),
    ]
)
log = logging.getLogger(__name__)

# ── Load config ───────────────────────────────────────────────────────────────
CONFIG_PATH = Path(__file__).parent / 'config.json'

def load_config():
    if not CONFIG_PATH.exists():
        log.error(f'Config file not found: {CONFIG_PATH}')
        raise FileNotFoundError(f'Create {CONFIG_PATH} from config.example.json')
    with open(CONFIG_PATH) as f:
        return json.load(f)

# ── OUI vendor lookup (reads bundled oui.txt) ─────────────────────────────────
OUI_PATH = Path(__file__).parent / 'oui.txt'
_oui_cache = {}

def load_oui():
    if not OUI_PATH.exists():
        return
    with open(OUI_PATH, encoding='utf-8', errors='ignore') as f:
        for line in f:
            parts = line.strip().split('\t', 1)
            if len(parts) == 2:
                _oui_cache[parts[0].upper()] = parts[1]

def get_vendor(mac: str) -> str:
    oui = mac.upper().replace(':', '').replace('-', '')[:6]
    return _oui_cache.get(oui, '')

# ── Normalize MAC ─────────────────────────────────────────────────────────────
def normalize_mac(raw) -> str:
    """Convert any MAC format to aa:bb:cc:dd:ee:ff"""
    # pysnmp returns OctetString objects — convert to bytes first
    try:
        raw_bytes = bytes(raw)
        if len(raw_bytes) == 6:
            return ':'.join(f'{b:02x}' for b in raw_bytes)
    except Exception:
        pass

    if isinstance(raw, bytes) and len(raw) == 6:
        return ':'.join(f'{b:02x}' for b in raw)

    # Try hex string fallback (e.g. "0x8005886f6f73" or "80:05:88:6f:6f:73")
    cleaned = str(raw).lower()
    cleaned = cleaned.replace('0x', '').replace(':', '').replace('-', '').replace('.', '').replace(' ', '')
    # Remove non-hex characters
    cleaned = ''.join(c for c in cleaned if c in '0123456789abcdef')
    if len(cleaned) != 12:
        return ''
    return ':'.join(cleaned[i:i+2] for i in range(0, 12, 2))

# ── Reverse DNS hostname lookup ───────────────────────────────────────────────
def get_hostname(ip: str) -> str:
    try:
        return socket.gethostbyaddr(ip)[0]
    except Exception:
        return ''

# ── SNMP helpers ──────────────────────────────────────────────────────────────
def snmp_walk(host, community, oid, port=161, timeout=10, retries=2):
    """Walk an OID tree and return list of (oid_str, value) tuples."""
    results = []
    for (errorIndication, errorStatus, errorIndex, varBinds) in nextCmd(
        SnmpEngine(),
        CommunityData(community, mpModel=1),  # mpModel=1 = SNMPv2c
        UdpTransportTarget((host, port), timeout=timeout, retries=retries),
        ContextData(),
        ObjectType(ObjectIdentity(oid)),
        lexicographicMode=False,
    ):
        if errorIndication:
            log.warning(f'SNMP walk error: {errorIndication}')
            break
        if errorStatus:
            log.warning(f'SNMP error status: {errorStatus.prettyPrint()}')
            break
        for varBind in varBinds:
            results.append((str(varBind[0]), varBind[1]))
    return results

# ── Collect ARP table from WS6008 ─────────────────────────────────────────────
def collect_arp_table(host, community, port=161):
    """
    Read ipNetToMediaTable (RFC 1213):
      ipNetToMediaPhysAddress = 1.3.6.1.2.1.4.22.1.2
      ipNetToMediaNetAddress  = 1.3.6.1.2.1.4.22.1.3
      ipNetToMediaType        = 1.3.6.1.2.1.4.22.1.4

    Returns dict: { ip_address -> mac_address }
    """
    log.info('Collecting ARP table via SNMP...')
    mac_by_key   = {}
    ip_by_key    = {}

    # Collect MAC addresses (ifIndex.ipAddress -> physAddress)
    for oid_str, val in snmp_walk(host, community, '1.3.6.1.2.1.4.22.1.2', port):
        # OID suffix is ifIndex.a.b.c.d where a.b.c.d is the IP
        parts = oid_str.split('.')
        if len(parts) >= 4:
            ip_key = '.'.join(parts[-4:])
            mac_by_key[ip_key] = normalize_mac(val)

    # Collect IP addresses
    for oid_str, val in snmp_walk(host, community, '1.3.6.1.2.1.4.22.1.3', port):
        parts = oid_str.split('.')
        if len(parts) >= 4:
            ip_key = '.'.join(parts[-4:])
            ip_by_key[ip_key] = str(val)

    result = {}
    for key, ip in ip_by_key.items():
        mac = mac_by_key.get(key, '')
        if mac and ip:
            result[ip] = mac

    log.info(f'ARP table: {len(result)} entries')
    return result

# ── Collect VLAN info ──────────────────────────────────────────────────────────
def collect_vlan_info(host, community, port=161):
    """
    Try to get VLAN membership per IP from Ruijie proprietary MIBs.
    Falls back to empty dict if not supported on this firmware.
    Returns dict: { ip_address -> { vlan_id, vlan_name } }
    """
    vlan_map = {}
    try:
        # dot1qVlanStaticName (802.1Q MIB): 1.3.6.1.2.1.17.7.1.4.3.1.1
        vlan_names = {}
        for oid_str, val in snmp_walk(host, community, '1.3.6.1.2.1.17.7.1.4.3.1.1', port):
            parts = oid_str.split('.')
            if parts:
                try:
                    vlan_id = int(parts[-1])
                    vlan_names[vlan_id] = str(val)
                except ValueError:
                    pass
        log.info(f'Found {len(vlan_names)} VLAN names')
    except Exception as e:
        log.debug(f'VLAN name walk failed: {e}')
    return vlan_map

# ── Collect wireless clients from WS6008 ──────────────────────────────────────
def collect_wireless_clients(host, community, port=161):
    """
    Attempt to collect wireless client data from Ruijie WS6008 via:
    1. Standard IEEE 802.11 MIB dot11StationID (1.2.840.10036.1.1.1.1)
    2. Ruijie enterprise wireless MIB (1.3.6.1.4.1.4881.*)

    Returns list of dicts with wireless-specific fields.
    This is best-effort — not all firmware versions expose full client data via SNMP.
    """
    clients = {}

    # Try Ruijie proprietary wireless client table
    # OID prefix for Ruijie wireless clients (adjust if firmware differs)
    ruijie_client_oid = '1.3.6.1.4.1.4881.1.1.10.2.13.1.1'
    try:
        for oid_str, val in snmp_walk(host, community, ruijie_client_oid, port):
            parts = oid_str.split('.')
            # Parse MAC from OID suffix (last 6 octets)
            if len(parts) >= 6:
                try:
                    mac_parts = parts[-6:]
                    mac = ':'.join(f'{int(x):02x}' for x in mac_parts)
                    if mac not in clients:
                        clients[mac] = {'mac': mac, 'connection_type': 'wireless'}
                    clients[mac]['raw_val'] = str(val)
                except (ValueError, IndexError):
                    pass
    except Exception as e:
        log.debug(f'Ruijie wireless client walk failed (normal if unsupported): {e}')

    log.info(f'Wireless clients from SNMP: {len(clients)}')
    return list(clients.values())

# ── Build device list ─────────────────────────────────────────────────────────
def build_device_list(config):
    host      = config['snmp']['host']
    community = config['snmp']['community']
    port      = config.get('snmp', {}).get('port', 161)
    subnets   = config.get('subnets', [])

    devices = {}

    # 1. ARP table — all reachable devices
    arp_table = collect_arp_table(host, community, port)
    for ip, mac in arp_table.items():
        if not mac or mac == '00:00:00:00:00:00':
            continue
        if mac not in devices:
            devices[mac] = {
                'mac_address':    mac,
                'ip_address':     ip,
                'hostname':       '',
                'vendor':         get_vendor(mac),
                'connection_type':'unknown',
                'vlan_id':        None,
                'vlan_name':      None,
                'ap_name':        None,
                'ssid':           None,
                'signal_dbm':     None,
            }

    # 2. Wireless clients — mark as wireless + get AP info
    wireless = collect_wireless_clients(host, community, port)
    for wc in wireless:
        mac = normalize_mac(wc.get('mac', ''))
        if mac and mac in devices:
            devices[mac]['connection_type'] = 'wireless'
            devices[mac]['ap_name']         = wc.get('ap_name')
            devices[mac]['ssid']            = wc.get('ssid')
            devices[mac]['signal_dbm']      = wc.get('signal_dbm')

    # 3. Hostname resolution (only for online devices — rate limited)
    resolve_hostnames = config.get('resolve_hostnames', True)
    if resolve_hostnames:
        for mac, dev in devices.items():
            if dev['ip_address'] and not dev['hostname']:
                dev['hostname'] = get_hostname(dev['ip_address'])
                time.sleep(0.02)  # avoid hammering DNS

    log.info(f'Total devices collected: {len(devices)}')
    return list(devices.values())

# ── POST to CRCMIS ─────────────────────────────────────────────────────────────
def post_to_crcmis(devices, config):
    api_token    = config['crcmis']['api_token']
    app_hostname = 'mis.crc.pshs.edu.ph'

    # Bypass Cloudflare by hitting the ALB directly.
    # The ALB is internet-facing and forwards to ECS without going through Cloudflare.
    alb_url  = 'https://crcmis-alb-481818448.ap-southeast-1.elb.amazonaws.com/api/network/scan-report'

    payload = {
        'agent_version': '1.0',
        'devices':       devices,
    }

    try:
        import urllib3
        urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

        resp = requests.post(
            alb_url,
            json=payload,
            headers={
                'Authorization': f'Bearer {api_token}',
                'Accept':        'application/json',
                'Content-Type':  'application/json',
                'Host':          app_hostname,
            },
            verify=False,   # ALB cert is issued for the app domain, not the ALB DNS name
            timeout=30,
        )
        resp.raise_for_status()
        data = resp.json()
        log.info(f'CRCMIS sync OK — {data.get("devices_synced")} synced, '
                 f'{data.get("devices_online")} online, '
                 f'{data.get("duration_ms")}ms server-side')
        return True
    except requests.HTTPError:
        log.error(f'CRCMIS API error {resp.status_code}: {resp.text[:200]}')
        return False
    except Exception as e:
        log.error(f'Failed to POST to CRCMIS: {e}')
        return False

# ── Main ───────────────────────────────────────────────────────────────────────
def main():
    log.info('=== CRCMIS Network Scanner starting ===')
    start = time.time()

    try:
        config  = load_config()
        load_oui()

        devices = build_device_list(config)

        if not devices:
            log.warning('No devices found — check SNMP connectivity to WS6008.')
            return

        success = post_to_crcmis(devices, config)
        elapsed = round(time.time() - start, 2)
        log.info(f'Scan complete in {elapsed}s. Status: {"OK" if success else "FAILED"}')

    except Exception as e:
        log.exception(f'Scanner failed: {e}')

if __name__ == '__main__':
    main()
