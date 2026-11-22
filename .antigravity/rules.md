# Non-Negotiable Engineering Invariants for Carrier BSS/OSS & AAA

## 1. Plan-Before-Code
Inspect the existing repository first. Present a concise file-by-file change plan and pause for approval before modifying or creating code.

## 2. Strict Typing & Static Analysis
Every new or modified PHP file MUST start with `declare(strict_types=1);`, pass `./vendor/bin/pint --test`, and pass `./vendor/bin/phpstan analyse --memory-limit=1G` (Larastan Level 6).

## 3. Citus & Multi-Tenant Safety
Every new table MUST include `tenant_id` (indexed) and `subscriber_id` where applicable for Citus co-location.

## 4. Zero-Risk Isolated Testing
Never touch live production routers or secrets. Use HTTP/Queue/Redis fakes and `coa_sim.py` / `radtest` in local/CI sandboxes. Never hardcode real credentials in code or git.

## 5. AAA & Queue Isolation
- Never place FreeRADIUS UDP 1812/1813/3799 in serverless/Cloud Run configs.
- Never allow `0.0.0.0/0` in FreeRADIUS `clients.conf`.
- Redis MUST use `--maxmemory-policy noeviction` so `SETNX` webhook locks and CoA jobs are never evicted.
- Configure `config/horizon.php` with 4 isolated queues:
  * `critical` (CoA disconnects, payment unblock `<3s`, NOC kicks)
  * `notifications` (SMS, WhatsApp, Email, OTP)
  * `network` (RouterOS API sync, SNMP/ICMP polling, OLT/ONT discovery)
  * `default` (dunning buckets, monthly invoices, CGNAT ClickHouse sync)
