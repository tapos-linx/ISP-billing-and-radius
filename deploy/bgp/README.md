# Carrier BGP Anycast & Autonomic Peering Engine

## Overview
This directory contains production routing configurations for the Carrier BGP Anycast architecture and national internet exchange (BDIX) peering telemetry.

---

## Topology & Routing Engine

- **Anycast Virtual IP (VIP)**: `103.145.10.1/32` (FreeRADIUS AAA Anycast entrypoint)
- **Carrier Autonomous System**: `AS65001`
- **Upstream Transit Peer**: `192.168.100.1` (`AS64512`)
- **BDIX National IXP Peer**: `192.168.100.2` (`AS64513`)

---

## Configuration Files

### 1. BIRD 2.x Anycast Configuration (`bird.conf`)
- Runs on FreeRADIUS edge nodes.
- Injects `/32` loopback VIP with BGP community tagging (`65001:100` for active, `65001:200` for drain).
- Implements **Bidirectional Forwarding Detection (BFD)** with 100ms intervals and 3x multiplier for sub-300ms failover detection.
- Export filters support automated node draining via AS path prepending.

### 2. FRRouting Configuration (`frr.conf`)
- Alternative RFC-compliant BGP daemon for Linux router instances.
- Configures address families, route-maps, and prefix-lists for BDIX versus Global Transit peering.

---

## Operational Commands

### Reloading BIRD 2.x
```bash
birdc configure
birdc show protocols all
birdc show bfd sessions
```

### Draining an Anycast Node (Maintenance Mode)
```bash
birdc "reload anycast_drain"
```

### Verifying FRR BGP Neighbors
```bash
vtysh -c "show ip bgp summary"
vtysh -c "show ip bgp neighbors 192.168.100.2 advertised-routes"
```
