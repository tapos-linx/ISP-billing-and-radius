# Carrier Production Rollback & Emergency Disaster Recovery Runbook

**Document Version:** 2.2.1  
**Classification:** Incident Response / Disaster Recovery  
**Target Recovery Time Objective (RTO):** $< 15\text{ Minutes}$  
**Target Recovery Point Objective (RPO):** $< 60\text{ Seconds}$  

---

## 1. Rollback Decision Triggers

The Incident Commander MUST authorize an immediate rollback if any of the following critical conditions persist for $> 3\text{ minutes}$ post-deployment:

1. **AAA Authentication Blackout:** FreeRADIUS rejection rate or timeout exceeds $2.0\%$ of total subscriber attempts.
2. **Citus Database Partition Failure:** Write failures across any of the 16 Citus distributed shards.
3. **Double-Entry Ledger Imbalance:** Any transaction triggering debit/credit mismatch or hash chain corruption.
4. **MFS Payment Gateway Failure:** Complete failure of bKash or Nagad webhook processing.

---

## 2. Emergency Rollback Protocol

```
                                  ROLLBACK SEQUENCE
[Trigger Event] ---> [Freeze BNG Edge] ---> [Restore DB Snapshot] ---> [Deploy Prior Image] ---> [Verify AAA]
```

### Step 1: Immediate Network Edge Freeze (< 2 Minutes)
Isolate incoming changes by pointing edge BNGs to the legacy or secondary standalone FreeRADIUS node:
```bash
# On MikroTik BNG via SSH/REST
/radius set [find comment="Anycast-Primary-AAA"] disabled=yes
/radius set [find comment="Legacy-Emergency-AAA"] disabled=no
```

### Step 2: Stop Staging / Production Containers (< 1 Minute)
```bash
cd /opt/isp-billing
docker compose -f infra/docker-compose.yml stop backend_api queue_worker isp_radius_1 isp_radius_2
```
> [!CAUTION]
> NEVER execute `docker compose down -v`. Volume data MUST be preserved for forensic data recovery and state preservation.

### Step 3: Database Point-In-Time Restore (PITR) or Snapshot Revert (< 7 Minutes)
If a schema migration corrupted state:
```bash
# 1. Stop Citus master and workers
docker compose -f infra/docker-compose.yml stop citus_master citus_worker_1 citus_worker_2

# 2. Revert NVMe storage volume to pre-deployment ZFS / LVM snapshot:
zfs rollback rpool/docker/volumes/citus_master_data@pre_deploy_v2_2_0
zfs rollback rpool/docker/volumes/citus_worker_1_data@pre_deploy_v2_2_0
zfs rollback rpool/docker/volumes/citus_worker_2_data@pre_deploy_v2_2_0

# 3. Restart Citus cluster
docker compose -f infra/docker-compose.yml up -d citus_master citus_worker_1 citus_worker_2
```

### Step 4: Checkout Prior Certified Git Release Tag (< 2 Minutes)
```bash
cd /opt/isp-billing
git checkout tags/v2.1.0-release -f
docker compose -f infra/docker-compose.yml up -d --build backend_api queue_worker
```

### Step 5: Flush Ephemeral Nonces & Redis Cache (< 1 Minute)
Clear cached routes, application configs, and incomplete session nonces:
```bash
docker exec -i redis_master redis-cli -a "$(cat /run/secrets/redis_password)" FLUSHDB
docker exec -i backend_api php artisan config:clear
docker exec -i backend_api php artisan route:clear
```

### Step 6: Re-enable Edge BNG Primary AAA Route
```bash
/radius set [find comment="Anycast-Primary-AAA"] disabled=no
/radius set [find comment="Legacy-Emergency-AAA"] disabled=yes
```

---

## 3. Post-Rollback Data Integrity & Ledger Audit

Following rollback, execute the accounting reconciliation script:
```bash
docker exec -i backend_api php artisan billing:reconcile-ledger --verify-hash-chain
```
- Ensures all debits equal credits in `ledger_entries`.
- Validates no orphan invoices exist in `invoices` table.
- Logs incident report to `docs/11-evolution/POSTMORTEM_TEMPLATE.md`.
