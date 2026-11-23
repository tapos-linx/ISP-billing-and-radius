# Virtual Containerlab Network Simulation Runbook

## Overview
The `deploy/containerlab` directory provides a reproducible, lightweight virtual testing environment using **Containerlab** and **MikroTik Cloud Hosted Router (CHR)** running RouterOS v7.

It allows automated end-to-end testing of:
- PPPoE subscriber discovery and session establishment
- Real-time RADIUS AAA Access-Request authentication against FreeRADIUS
- Dynamic interim accounting updates to Citus PostgreSQL
- Automated RFC 5176 Packet of Disconnect (PoD) session termination on UDP 3799

---

## Topology Specification (`clab-isp-carrier.yml`)
- **`routeros-bng`**: MikroTik CHR v7.14+ acting as the virtual BNG.
- **`freeradius`**: FreeRADIUS 3.2.x linked via dedicated bridge `br-isp-carrier`.
- **`client-pppoe`**: Linux test subscriber dialing into the virtual BNG via PPPoE.

---

## Running the Containerlab Lab

### 1. Deploy Topology
```bash
sudo containerlab deploy -t deploy/containerlab/clab-isp-carrier.yml
```

### 2. Run Automated Dial-in & CoA Verification
```bash
bash deploy/containerlab/test_chr_dialin.sh
```

### 3. Verify Session Inside RouterOS CHR
```bash
ssh admin@172.20.20.2 -p 22
# Inside RouterOS CLI:
/interface pppoe-server print
/ppp active print
```

### 4. Teardown Topology
```bash
sudo containerlab destroy -t deploy/containerlab/clab-isp-carrier.yml --cleanup
```
