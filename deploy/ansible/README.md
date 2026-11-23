# Carrier Automation Playbooks & Roles (Ansible)

## Overview
This directory contains Ansible playbooks and modular roles for automated bare-metal and virtual host provisioning, kernel sysctl performance tuning, and service orchestration across carrier nodes.

---

## Roles Overview (`roles/`)

1. **`sysctl_tuning`**:
   - Applies high-throughput carrier kernel parameters:
     * `net.core.rmem_max = 16777216`
     * `net.core.wmem_max = 16777216`
     * `net.ipv4.udp_rmem_min = 16384`
     * `net.core.netdev_max_backlog = 10000`
     * `fs.file-max = 2097152`
2. **`freeradius_carrier`**:
   - Installs and provisions FreeRADIUS 3.2.x with `rlm_sql` PostgreSQL driver, hardened `clients.conf`, and RFC 5176 CoA listener.
3. **`bird2_anycast`**:
   - Configures BIRD 2.x daemon for Anycast VIP announcement (`103.145.10.1/32`) and BFD sub-second link health probing.
4. **`clickhouse_ipdr`**:
   - Provisions ClickHouse server with optimized memory ceilings and automated 730-day retention policies.

---

## Execution
```bash
cd deploy/ansible
ansible-playbook -i inventory.ini playbooks/site.yml --check
ansible-playbook -i inventory.ini playbooks/site.yml
```
