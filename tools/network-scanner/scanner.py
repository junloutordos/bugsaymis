#!/usr/bin/env python3
"""
CRCMIS Network Scanner Agent
Queries Ruijie WS6008 via SNMP + active subnet sweep and posts results to CRCMIS.

Requirements:
    pip3 install pysnmp==4.4.12 pyasn1==0.4.8 requests
"""

import json
import logging
import os
import socket
import subprocess
import time
from pathlib import Path

import requests
from pysnmp.hlapi import (
    CommunityData, ContextData, ObjectIdentity, ObjectType,
    SnmpEngine, UdpTransportTarget, nextCmd,
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
        raise FileNotFoundError(f'Create {CONFIG_PATH} from config.example.json')
    with open(CONFIG_PATH) as f:
        return json.load(f)

# ── OUI vendor lookup ─────────────────────────────────────────────────────────
_mac_lookup = None

def load_oui():
    global _mac_lookup
    try:
        from mac_vendor_lookup import MacLookup
        _mac_lookup = MacLookup()
        _mac_lookup.update_vendors()
        log.info('MAC vendor database loaded.')
    except Exception as e:
        log.warning(f'MAC vendor lookup unavailable: {e}')

def get_vendor(mac: str) -> str:
    if not _mac_lookup:
        return ''
    try:
        return _mac_lookup.lookup(mac)
    except Exception:
        return ''

# ── Normalize MAC ─────────────────────────────────────────────────────────────
def normalize_mac(raw) -> str:
    try:
        raw_bytes = bytes(raw)
        if len(raw_bytes) == 6:
            return ':'.join(f'{b:02x}' for b in raw_bytes)
    except Exception:
        pass
    if isinstance(raw, bytes) and len(raw) == 6:
        return ':'.join(f'{b:02x}' for b in raw)
    cleaned = str(raw).lower()
    cleaned = cleaned.replace('0x', '').replace(':', '').replace('-', '').replace('.', '').replace(' ', '')
    cleaned = ''.join(c for c in cleaned if c in '0123456789abcdef')
    if len(cleaned) != 12:
        return ''
    return ':'.join(cleaned[i:i+2] for i in range(0, 12, 2))

def is_real_mac(mac: str) -> bool:
    """Filter out invalid, multicast, and locally-administered MACs."""
    if not mac or mac in ('00:00:00:00:00:00', 'ff:ff:ff:ff:ff:ff'):
        return False
    try:
        first_byte = int(mac.split(':')[0], 16)
        if first_byte & 0x01:   return False  # multicast bit set
        if first_byte & 0x02:   return False  # locally administered — Ruijie internal MACs
    except (ValueError, IndexError):
        return False
    return True

# ── Reverse DNS ───────────────────────────────────────────────────────────────
def get_hostname(ip: str) -> str:
    try:
        return socket.gethostbyaddr(ip)[0]
    except Exception:
        return ''

# ── SNMP walk helper ──────────────────────────────────────────────────────────
def snmp_walk(host, community, oid, port=161, timeout=5, retries=1):
    results = []
    for (errorIndication, errorStatus, _, varBinds) in nextCmd(
        SnmpEngine(),
        CommunityData(community, mpModel=1),
        UdpTransportTarget((host, port), timeout=timeout, retries=retries),
        ContextData(),
        ObjectType(ObjectIdentity(oid)),
        lexicographicMode=False,
    ):
        if errorIndication:
            log.warning(f'SNMP walk error on {oid}: {errorIndication}')
            break
        if errorStatus:
            break
        for varBind in varBinds:
            results.append((str(varBind[0]), varBind[1]))
    return results

# ── Collect ARP table from WS6008 ─────────────────────────────────────────────
def collect_arp_table(host, community, port=161):
    """
    Walk ipNetToMediaPhysAddress (1.3.6.1.2.1.4.22.1.2).
    OID format: ...4.22.1.2.{ifIndex}.{a}.{b}.{c}.{d}
    The IP address is encoded in the last 4 OID components — NOT in the value.
    Value = MAC address (6 bytes).
    """
    log.info('Collecting ARP table via SNMP...')
    result = {}

    for oid_str, val in snmp_walk(host, community, '1.3.6.1.2.1.4.22.1.2', port):
        parts = oid_str.split('.')
        # Need at least 5 parts after the base: ifIndex + 4 IP octets
        if len(parts) < 5:
            continue
        try:
            # Last 4 parts = IP address octets
            ip_octets = [int(x) for x in parts[-4:]]
            if not all(0 <= o <= 255 for o in ip_octets):
                continue
            ip  = '.'.join(str(o) for o in ip_octets)
            mac = normalize_mac(val)
            if is_real_mac(mac):
                result[ip] = mac
        except (ValueError, IndexError):
            continue

    log.info(f'SNMP ARP table: {len(result)} entries')
    return result

# ── Active subnet sweep ───────────────────────────────────────────────────────
def sweep_subnets(subnets: list) -> dict:
    """
    Ping-sweep the configured subnets then read the local ARP table.
    This discovers ALL devices on the network, not just ones known to the WS6008.
    Returns dict: { ip -> mac }
    """
    if not subnets:
        return {}

    log.info(f'Sweeping {len(subnets)} subnet(s): {subnets}')

    # Ping sweep each subnet (populates the OS ARP cache)
    for subnet in subnets:
        try:
            # nmap -sn = ping scan, no port scan (fast)
            subprocess.run(
                ['nmap', '-sn', '--min-parallelism', '50', subnet],
                capture_output=True, timeout=60,
            )
            log.info(f'Sweep complete: {subnet}')
        except FileNotFoundError:
            # nmap not installed — try fping or ping fallback
            log.warning('nmap not found — install it for subnet scanning: sudo apt install nmap')
        except subprocess.TimeoutExpired:
            log.warning(f'Subnet sweep timed out: {subnet}')
        except Exception as e:
            log.warning(f'Sweep error on {subnet}: {e}')

    # Read OS ARP table after sweep
    return read_local_arp()

def read_local_arp() -> dict:
    """Read the local OS ARP cache. Returns { ip -> mac }."""
    result = {}
    try:
        # Linux: /proc/net/arp
        if Path('/proc/net/arp').exists():
            with open('/proc/net/arp') as f:
                for line in f:
                    parts = line.split()
                    if len(parts) >= 4 and parts[2] == '0x2':  # 0x2 = complete entry
                        ip  = parts[0]
                        mac = normalize_mac(parts[3])
                        if is_real_mac(mac):
                            result[ip] = mac
        else:
            # macOS / BSD: arp -an (numeric, no hostname lookup)
            out = subprocess.check_output(['arp', '-an'], text=True, timeout=10)
            for line in out.splitlines():
                # Format: ? (10.10.100.1) at 04:eb:40:ec:36:26 on en0 ifscope [ethernet]
                if ' at ' not in line or '(' not in line:
                    continue
                try:
                    ip      = line.split('(')[1].split(')')[0]
                    mac_raw = line.split(' at ')[1].split(' ')[0]
                    if mac_raw in ('(incomplete)', 'ff:ff:ff:ff:ff:ff'):
                        continue
                    mac = normalize_mac(mac_raw)
                    if is_real_mac(mac):
                        result[ip] = mac
                except (IndexError, ValueError):
                    pass
    except Exception as e:
        log.warning(f'Local ARP read failed: {e}')

    log.info(f'Local ARP table: {len(result)} entries')
    return result

# ── Collect VLAN names ────────────────────────────────────────────────────────
def collect_vlan_names(host, community, port=161) -> dict:
    """Returns { vlan_id -> vlan_name }"""
    names = {}
    try:
        for oid_str, val in snmp_walk(host, community, '1.3.6.1.2.1.17.7.1.4.3.1.1', port):
            parts = oid_str.split('.')
            if parts:
                try:
                    vlan_id = int(parts[-1])
                    name    = str(val).strip()
                    if name and name != '':
                        names[vlan_id] = name
                except ValueError:
                    pass
        if names:
            log.info(f'VLAN names: {names}')
    except Exception as e:
        log.debug(f'VLAN name walk error: {e}')
    return names

# ── Collect wireless clients ──────────────────────────────────────────────────
def collect_wireless_clients(host, community, port=161) -> dict:
    """
    Try multiple Ruijie OID trees to get wireless client → AP mapping.
    Returns { mac -> { ap_name, ssid, signal_dbm } }
    """
    clients = {}

    # Ruijie WS6008 wireless client table — try several known OID prefixes
    candidate_oids = [
        '1.3.6.1.4.1.4881.1.1.10.2.13.1.1',  # client MAC table v1
        '1.3.6.1.4.1.4881.1.1.10.2.13.1.2',  # client IP
        '1.3.6.1.4.1.4881.1.1.10.2.32.1.1',  # AP client table (newer FW)
        '1.3.6.1.4.1.4881.1.1.10.2.10.1',    # AP client count
    ]

    for oid in candidate_oids:
        rows = snmp_walk(host, community, oid, port, timeout=5, retries=1)
        if rows:
            log.info(f'Wireless client data found at OID {oid}: {len(rows)} rows')
            for oid_str, val in rows:
                parts = oid_str.split('.')
                if len(parts) >= 6:
                    try:
                        mac_parts = parts[-6:]
                        mac = ':'.join(f'{int(x):02x}' for x in mac_parts)
                        if is_real_mac(mac) and mac not in clients:
                            clients[mac] = {'connection_type': 'wireless'}
                    except (ValueError, IndexError):
                        pass
            break  # Use first OID that returns data

    log.info(f'Wireless clients from SNMP: {len(clients)}')
    return clients

# ── Build device list ─────────────────────────────────────────────────────────
def build_device_list(config):
    host      = config['snmp']['host']
    community = config['snmp']['community']
    port      = int(config.get('snmp', {}).get('port', 161))
    subnets   = config.get('subnets', [])

    devices = {}

    # 1. WS6008 SNMP ARP table
    snmp_arp = collect_arp_table(host, community, port)
    for ip, mac in snmp_arp.items():
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

    # 2. Active subnet sweep — discovers wired + all other devices
    if subnets:
        local_arp = sweep_subnets(subnets)
        for ip, mac in local_arp.items():
            if mac not in devices:
                devices[mac] = {
                    'mac_address':    mac,
                    'ip_address':     ip,
                    'hostname':       '',
                    'vendor':         get_vendor(mac),
                    'connection_type':'wired',  # assume wired if not in WS6008 ARP
                    'vlan_id':        None,
                    'vlan_name':      None,
                    'ap_name':        None,
                    'ssid':           None,
                    'signal_dbm':     None,
                }
            else:
                # Update IP if more recent
                devices[mac]['ip_address'] = ip

    # 3. VLAN names + per-device VLAN assignment via dot1qTpFdbTable
    vlan_names = collect_vlan_names(host, community, port)
    # dot1qTpFdbTable: 1.3.6.1.2.1.17.7.1.2.2.1.2.{vlan_id}.{mac_6_octets} = port
    try:
        for oid_str, _ in snmp_walk(host, community, '1.3.6.1.2.1.17.7.1.2.2.1.2', port):
            parts = oid_str.split('.')
            if len(parts) >= 7:
                try:
                    vlan_id  = int(parts[-7])
                    mac = ':'.join(f'{int(x):02x}' for x in parts[-6:])
                    if mac in devices and not devices[mac]['vlan_id']:
                        devices[mac]['vlan_id']   = vlan_id
                        devices[mac]['vlan_name'] = vlan_names.get(vlan_id)
                except (ValueError, IndexError):
                    pass
    except Exception as e:
        log.debug(f'VLAN FDB table error: {e}')

    # 4. Wireless client data — add as new devices if not already in ARP/sweep
    wireless = collect_wireless_clients(host, community, port)
    for mac, info in wireless.items():
        if mac in devices:
            devices[mac]['connection_type'] = 'wireless'
            if info.get('ap_name'):   devices[mac]['ap_name']   = info['ap_name']
            if info.get('ssid'):      devices[mac]['ssid']      = info['ssid']
            if info.get('signal_dbm'):devices[mac]['signal_dbm']= info['signal_dbm']
        else:
            # Wireless client not in ARP — add it anyway
            devices[mac] = {
                'mac_address':    mac,
                'ip_address':     info.get('ip_address'),
                'hostname':       '',
                'vendor':         get_vendor(mac),
                'connection_type':'wireless',
                'vlan_id':        info.get('vlan_id'),
                'vlan_name':      None,
                'ap_name':        info.get('ap_name'),
                'ssid':           info.get('ssid'),
                'signal_dbm':     info.get('signal_dbm'),
            }

    # 5. Hostname resolution (rate-limited to avoid DNS flood)
    resolve = config.get('resolve_hostnames', True)
    if resolve:
        for mac, dev in list(devices.items()):
            if dev['ip_address'] and not dev['hostname']:
                dev['hostname'] = get_hostname(dev['ip_address'])
                time.sleep(0.01)

    log.info(f'Total devices collected: {len(devices)}')
    return list(devices.values())

# ── POST to CRCMIS ─────────────────────────────────────────────────────────────
def post_to_crcmis(devices, config):
    api_token    = config['crcmis']['api_token']
    app_hostname = 'mis.crc.pshs.edu.ph'
    alb_hostname = 'crcmis-alb-481818448.ap-southeast-1.elb.amazonaws.com'
    # Fallback IPs in case campus DNS cannot resolve the ALB hostname
    alb_fallback_ips = config.get('alb_fallback_ips', ['54.254.59.56', '13.214.50.217'])
    path             = '/api/network/scan-report'

    payload = {'agent_version': '1.1', 'devices': devices}

    import urllib3
    urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

    headers = {
        'Authorization': f'Bearer {api_token}',
        'Accept':        'application/json',
        'Content-Type':  'application/json',
        'Host':          app_hostname,
    }

    # Build list of URLs to try: DNS hostname first, then fallback IPs
    urls = [f'https://{alb_hostname}{path}']
    for ip in alb_fallback_ips:
        urls.append(f'https://{ip}{path}')

    for url in urls:
        try:
            resp = requests.post(url, json=payload, headers=headers,
                                 verify=False, timeout=60)
            resp.raise_for_status()
            data = resp.json()
            log.info(f'CRCMIS sync OK — {data.get("devices_synced")} synced, '
                     f'{data.get("devices_online")} online, {data.get("duration_ms")}ms')
            return True
        except requests.HTTPError:
            log.error(f'CRCMIS API error {resp.status_code}: {resp.text[:300]}')
            return False
        except requests.ConnectionError as e:
            log.warning(f'Connection failed ({url}): {e} — trying next...')
            continue
        except Exception as e:
            log.error(f'Unexpected error posting to {url}: {e}')
            return False

    log.error('All CRCMIS endpoints failed.')
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
        log.info(f'Done in {round(time.time()-start,2)}s — {"OK" if success else "FAILED"}')
    except Exception as e:
        log.exception(f'Scanner failed: {e}')

if __name__ == '__main__':
    main()
