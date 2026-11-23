# Carrier Physical BNG & GPON OLT Hardware Deployment Runbook

## Overview
This directory contains production-ready configuration templates for onboarding physical carrier BNG routers and GPON Optical Line Terminals (OLTs) to the centralized FreeRADIUS 3.2.x and Citus PostgreSQL cluster.

---

## Hardware Configurations

| Device Model | File | Target Role | Key Features |
| :--- | :--- | :--- | :--- |
| **MikroTik CCR2004-16G-2S+** | `carrier_primary_ccr2004.rsc` | Primary BRAS / BNG | RouterOS v7, RFC 5176 CoA UDP 3799, 5m Interim Accounting, Walled Garden NAT |
| **Huawei SmartAX MA5608T** | `carrier_primary_ma5608t.cfg` | GPON Mini-OLT | SNMP v2c/v3 traps, GenieACS TR-069 management server registration |
| **ZTE C320 / C300** | `carrier_primary_zte_c320.cfg` | GPON Compact OLT | SNMP telemetry, TR-069 ACS URL registration, PON port discovery |

---

## Deployment Instructions

### 1. MikroTik RouterOS v7 BNG (`carrier_primary_ccr2004.rsc`)
1. Connect via SSH or WinBox to the physical router:
   ```bash
   ssh admin@10.10.0.2
   ```
2. Upload and import configuration:
   ```routeros
   /import file-name=carrier_primary_ccr2004.rsc
   ```
3. Verify RADIUS and CoA listener status:
   ```routeros
   /radius print
   /radius monitor 0
   /radius incoming print
   /ip firewall filter print where comment~"CoA"
   ```

### 2. Huawei MA5608T OLT (`carrier_primary_ma5608t.cfg`)
1. Connect via Telnet or Serial Console:
   ```bash
   telnet 10.20.0.5
   ```
2. Enter configuration mode and apply directives:
   ```text
   enable
   config
   sysman server snmp 10.10.0.10
   snmp-agent community read carrier_ro_2026
   snmp-agent target-host trap-paramsname carrier_bss v1 securityname carrier_ro_2026
   snmp-agent target-host trap-hostname 10.10.0.10 address 10.10.0.10 trap-paramsname carrier_bss
   tr069 server url http://10.10.0.10:7547/
   tr069 server auth-type basic
   save
   ```

### 3. ZTE C320 OLT (`carrier_primary_zte_c320.cfg`)
1. Connect via SSH or Console:
   ```bash
   ssh admin@10.20.0.6
   ```
2. Enter global configuration:
   ```text
   configure terminal
   snmp-server community carrier_ro_2026 view AllView rw
   snmp-server host 10.10.0.10 version 2c carrier_ro_2026
   gpon
     tr069 server-url http://10.10.0.10:7547/
   exit
   write
   ```

---

## Security Compliance & Invariants
- **RFC 5176 CoA Firewall Filtering**: Incoming UDP 3799 traffic is restricted strictly to the BSS IP (`10.10.0.10`). All unauthorized CoA packets are dropped fail-closed.
- **Interim Accounting**: MikroTik PPP AAA interim-update is set to 5 minutes (`interim-update=5m`) to balance accounting accuracy with Citus database throughput.
- **Walled Garden Isolation**: Expired subscribers are automatically allocated IP addresses in `10.250.0.0/24` with destination NAT redirecting port 80 to the captive payment portal (`10.10.0.10:8000`).
