# Carrier Secret & Key Rotation Runbook

**Document Version:** 2.2.1  
**Classification:** Restricted / DevOps & SecOps Operations  
**Compliance Standard:** ISO/IEC 27001:2022 A.9.4, PCI-DSS v4.0 Req 3.6, NBR/BTRC ISP Guidelines  
**Target Environment:** Staging & Production Multi-Shard Carrier Cluster  

---

## 1. Scope & Security Objectives

This runbook establishes mandatory, zero-downtime standard operating procedures for rotating all cryptographic keys, database passwords, network secrets, and payment credentials across the ISP Billing & RADIUS infrastructure.

### Managed Credential Inventory

| Credential Name | Scope / Target Component | Storage Location / Vault Path | Rotation Cycle | Max Tolerated Downtime |
| :--- | :--- | :--- | :--- | :--- |
| `POSTGRES_PASSWORD` | Citus Master & Workers, App Pool | `/run/secrets/db_password` | 90 Days | 0 ms (Zero-Downtime) |
| `REPLICATION_PASSWORD` | PostgreSQL Streaming Replication (`repl_user`) | `/run/secrets/repl_password` | 90 Days | 0 ms |
| `REDIS_PASSWORD` | Redis Sentinel / Master-Replica Bus | `/run/secrets/redis_password` | 90 Days | 0 ms |
| `RADIUS_SECRET` | FreeRADIUS 3.2.x & MikroTik BNG NAS | `/run/secrets/radius_secret` | 180 Days | 0 ms (Dual-NAS Window) |
| `RABBITMQ_PASSWORD` | Event Broker & Job Worker Pool | `/run/secrets/rabbitmq_password` | 90 Days | 0 ms |
| `MFS_BKASH_SECRET` | bKash Checkout URL & Webhook HMAC | `/run/secrets/bkash_secret` | 180 Days | 0 ms (Dual-Secret Keyring) |
| `MFS_NAGAD_SECRET` | Nagad PGW & Webhook HMAC | `/run/secrets/nagad_secret` | 180 Days | 0 ms (Dual-Secret Keyring) |
| `APP_KEY` | Laravel Symmetric Encryption (AES-256-CBC) | Environment Variable / Vault | 365 Days | 0 ms (Re-encryption Job) |

---

## 2. Zero-Downtime Rotation Protocols

### 2.1 FreeRADIUS Shared Secret Rotation (`RADIUS_SECRET`)

FreeRADIUS validates incoming PAP/CHAP Authentication and RFC 2866 Accounting packets using the shared secret defined in `clients.conf` matching the NAS IP address.

#### Phase 1: Dual-Secret NAS Staging
FreeRADIUS supports matching NAS clients by subnet and specific IP. During rotation, configure the NAS to accept both secrets or stage an alternate port/IP:
```conf
# /opt/etc/raddb/clients.conf
client edge_bng_primary {
    ipaddr = 10.0.0.1
    secret = "OldCarrierSecret2026!#"
    nas_type = mikrotik
}

client edge_bng_primary_rot {
    ipaddr = 10.0.0.1
    secret = "NewCarrierSecret2027!$Secure"
    nas_type = mikrotik
}
```

#### Phase 2: Signal Graceful Config Reload
Do NOT restart FreeRADIUS. Send the `SIGHUP` signal to reload configuration without dropping inflight RADIUS UDP datagrams:
```bash
docker exec -i isp_radius kill -HUP 1
# Verify reload in debug log:
docker logs --tail 20 isp_radius | grep "Ready to process requests"
```

#### Phase 3: Update BNG / MikroTik RouterOS Secret
Execute RouterOS script via SSH or REST API to point to the new secret:
```routeros
/radius set [find address="10.0.0.254"] secret="NewCarrierSecret2027!$Secure"
```

#### Phase 4: Purge Old Secret
Remove the retired secret from `clients.conf` and update `/run/secrets/radius_secret`. Execute `kill -HUP 1` to complete.

---

### 2.2 Redis Cluster & Sentinel Password Rotation (`REDIS_PASSWORD`)

Redis allows changing runtime passwords dynamically in memory before persisting to Docker secrets or configuration files.

#### Step 1: Push Runtime Password to Master & Replicas
Connect via `redis-cli` using the current password and inject the new password into `requirepass` and `masterauth`:
```bash
OLD_PASS=$(cat /run/secrets/redis_password)
NEW_PASS="RedisSecToken_$(openssl rand -hex 16)"

# 1. Update Master
docker exec -i redis_master redis-cli -a "$OLD_PASS" CONFIG SET requirepass "$NEW_PASS"
docker exec -i redis_master redis-cli -a "$NEW_PASS" CONFIG SET masterauth "$NEW_PASS"

# 2. Update Replica
docker exec -i redis_replica redis-cli -a "$OLD_PASS" CONFIG SET requirepass "$NEW_PASS"
docker exec -i redis_replica redis-cli -a "$NEW_PASS" CONFIG SET masterauth "$NEW_PASS"
```

#### Step 2: Update Application Secrets
1. Write the `$NEW_PASS` to `/run/secrets/redis_password`.
2. Reload backend Laravel octane / queue worker containers:
```bash
docker exec -i isp_backend php artisan queue:restart
docker exec -i isp_backend php artisan config:clear
```

---

### 2.3 PostgreSQL Citus Database Password Rotation (`POSTGRES_PASSWORD`)

#### Step 1: Create Dual-Auth User Transition
PostgreSQL allows multiple credentials during rolling migrations.
```sql
-- Connect as postgres superuser
ALTER ROLE radius_user WITH PASSWORD 'NewDbPassword_2027_SuperSafe!';
```

#### Step 2: Rolling Update of Application & FreeRADIUS Secrets
1. Update `/run/secrets/db_password` with the new value.
2. Trigger rolling restart of backend web workers:
```bash
docker compose up -d --no-deps isp_backend
```
3. Update FreeRADIUS `/opt/etc/raddb/mods-available/sql` and trigger `kill -HUP 1`.

#### Step 3: Verify Replication Sync
Check streaming replication status:
```sql
SELECT application_name, client_addr, state, sync_state 
FROM pg_stat_replication;
```

---

### 2.4 Mobile Financial Services (MFS) HMAC Webhook Key Rotation

Both bKash and Nagad webhook handlers implement timestamped HMAC-SHA256 verification with dual-key validation windows to prevent dropped webhooks during partner key transitions.

#### Dual-Key Verification Algorithm
The `MfsWebhookSignatureValidator` evaluates signatures against both active and pending secrets:
```php
public function verifySignature(string $payload, string $receivedSignature): bool
{
    $activeSecret = config('services.mfs.active_secret');
    $graceSecret = config('services.mfs.grace_secret');

    $validActive = hash_equals(hash_hmac('sha256', $payload, $activeSecret), $receivedSignature);
    if ($validActive) {
        return true;
    }

    if ($graceSecret && hash_equals(hash_hmac('sha256', $payload, $graceSecret), $receivedSignature)) {
        Log::warning('MFS Webhook verified with deprecating grace secret.');
        return true;
    }

    return false;
}
```

#### Transition Steps:
1. Obtain new secret from bKash / Nagad Developer Portal.
2. Set `MFS_GRACE_SECRET` to current secret; set `MFS_ACTIVE_SECRET` to new secret.
3. Deploy configuration: `docker exec -i isp_backend php artisan config:cache`.
4. Switch secret in partner portal.
5. After 24 hours of zero hits on `grace_secret`, clear `MFS_GRACE_SECRET`.

---

## 3. Emergency Secret Compromise & Revocation Procedure

If a secret is leaked via log dump, GitHub commit, or host intrusion:

1. **Phase 1 — Sever External Ingress (< 60 Seconds):**
   ```bash
   # Block BNG RADIUS port or isolate public API
   docker exec -i isp_bng iptables -I INPUT -p udp --dport 1812:1813 -j DROP
   ```
2. **Phase 2 — Generate High-Entropy Cryptographic Replacements (< 3 Minutes):**
   ```bash
   openssl rand -base64 32 | tr -d '\r\n' > /run/secrets/compromised_secret
   ```
3. **Phase 3 — Database Kill-Switch (< 2 Minutes):**
   Terminate all active pooled sessions using the compromised credential:
   ```sql
   SELECT pg_terminate_backend(pid) 
   FROM pg_stat_activity 
   WHERE usename = 'radius_user' AND pid <> pg_backend_pid();
   ```
4. **Phase 4 — Immutable Audit Log Verification:**
   Query `audit_logs` for any unauthorized access or privilege escalation attempts:
   ```sql
   SELECT id, user_id, action, ip_address, created_at, hash_chain 
   FROM audit_logs 
   WHERE created_at >= NOW() - INTERVAL '6 hours' 
   ORDER BY id DESC;
   ```
5. **Phase 5 — File Security Incident Report:**
   Notify BTRC Computer Emergency Response Team (CERT) within 24 hours pursuant to telecom license obligations.
