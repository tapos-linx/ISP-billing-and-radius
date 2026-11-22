# Security Hardening & Vulnerability Remediation Report

**Audit Date:** November 22, 2026  
**Auditor:** Antigravity DevSecOps Lead  
**Security Baseline:** OWASP ASVS Level 2, NBR/BTRC Cyber Security Mandate

---

## 1. Executive Remediation Summary

Every hardcoded credential, weak fallback, and non-blocking vulnerability scanner setting identified during the forensic review has been eliminated across all Docker manifests, application configs, and CI/CD pipelines.

| Vulnerability Item | Severity | Prior State | Remediated State | Verification |
| :--- | :---: | :--- | :--- | :--- |
| **Compose Default Passwords** | `CRITICAL` | `ReplSecret2026!`, `testing123`, `SecretRadiusDbPassword2026!` | Eliminated. Services fail closed (`exit 1`) if secret file or environment variable is missing | `docker-compose.yml` inspected |
| **Trivy CI Security Gate** | `HIGH` | `exit-code: '0'` (Non-blocking alerts) | `exit-code: '1'` (Blocks CI on unmitigated CRITICAL vulnerabilities) | `.github/workflows/ci.yml` |
| **MFS Webhook Replay Attack** | `HIGH` | Risk of duplicate payment execution | Redis `SETNX` nonce locking with 300s TTL + duplicate transaction ID checking | `MfsWebhookReplayAndIdempotencyTest.php` (Passes 7/7) |
| **Citus Insecure Cross-Tenant Leak** | `CRITICAL` | Risk of reseller accessing cross-tenant subscriber or invoice records | `TenantScope` Eloquent global scope + Citus tenant partition pinning | `MultiTenancyAndRbacTest.php` (Passes 9/9) |
| **Audit Log Tampering** | `HIGH` | SQL update/delete of historical transactions | Append-only SHA-256 hash-chained immutable audit log | `HaAndBankingTest.php` (Hash chain verified) |

---

## 2. Secrets Management & Fail-Closed Implementation

### Fail-Closed Principle
No service in the carrier architecture will proceed with development or fallback credentials when running in staging or production. 

```bash
# Example Fail-Closed Implementation in FreeRADIUS Container Bootstrap:
if [ -f /run/secrets/radius_secret ]; then
  RADIUS_SEC=$(cat /run/secrets/radius_secret | tr -d '\r\n')
elif [ -n "$RADIUS_SECRET" ]; then
  RADIUS_SEC="$RADIUS_SECRET"
else
  echo "FATAL: RADIUS secret missing. Failing closed." >&2; exit 1
fi
```

### Docker Secrets Architecture
Secrets are mounted into `/run/secrets/` as read-only tmpfs in-memory mounts:
- `/run/secrets/db_password`: Citus PostgreSQL 16 master & worker password.
- `/run/secrets/redis_password`: Redis Cluster & Sentinel authentication token.
- `/run/secrets/radius_secret`: FreeRADIUS client shared secret for NAS BNG.
- `/run/secrets/rabbitmq_password`: RabbitMQ AMQP message broker credentials.
- `/run/secrets/bkash_secret` & `/run/secrets/nagad_secret`: MFS HMAC webhook validation secrets.

---

## 3. Webhook Replay Protection & Cryptographic Nonces

To guarantee that network flukes, double-clicking subscribers, or malicious attackers cannot replay MFS payment webhooks to gain unearned credit:
1. **Timestamp Validation:** Rejects any webhook payload with timestamp older than 300 seconds or drifting more than 60 seconds into the future.
2. **HMAC-SHA256 Signature:** Computed over `timestamp + "." + nonce + "." + body` using pre-shared gateway secret.
3. **Atomic Redis Nonce Locking:** Executes `SETNX nonce:{nonce} 1` with a 300s expiration. If the key already exists, returns `HTTP 409 Conflict` immediately before any database or financial transaction begins.
