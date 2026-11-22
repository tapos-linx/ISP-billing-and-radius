# Carrier ISP Threat Model (STRIDE Methodology)

**Target System:** Carrier BSS/OSS, Citus Distributed DB, FreeRADIUS 3.2, MikroTik BNG  
**Standard:** STRIDE Threat Classification & OWASP Application Security Verification

---

## 1. Threat Identification & Mitigation Matrix

| Category | Threat Description | Attack Vector | Carrier Mitigation & Control | Verification Status |
| :--- | :--- | :--- | :--- | :---: |
| **Spoofing** | Rogue NAS injecting falsified accounting records | Forged UDP 1813 packets | Pre-shared RADIUS secrets per NAS IP in `clients.conf` + IP binding in Citus | `VERIFIED` |
| **Spoofing** | Forged MFS payment webhook | Malicious POST to `/api/v1/webhooks/bkash` | HMAC-SHA256 signature verification over body + timestamp + nonce | `VERIFIED` |
| **Tampering** | Rogue DBA modifying billing records or clearing debt | Direct SQL UPDATE on `invoices` or `subscribers` | Append-only double-entry ledger + immutable SHA-256 hash chains | `VERIFIED` |
| **Tampering** | Citus partition column alteration | Modifying `subscriber_id` to steal sessions | Citus distributed partition immutability enforcement | `VERIFIED` |
| **Repudiation** | Subscriber disputing invoice issuance or VAT | Claiming bill was never rendered | Cryptographically signed Mushak-6.3 PDF generation + NBR BIN archiving | `VERIFIED` |
| **Information Disclosure** | Cross-tenant data leak | Reseller querying another reseller's subscribers | `TenantScope` global query filters + RBAC token verification | `VERIFIED` |
| **Information Disclosure** | Unauthenticated IPDR regulatory data access | Accessing `/api/v1/ipdr/search` | Restricted to authorized BTRC compliance tokens | `VERIFIED` |
| **Denial of Service** | RADIUS AAA authentication storm | Mass PPPoE reconnects during fiber cut | Redis in-memory fast-path (<0.4ms) + connection pool rate limiting | `VERIFIED` |
| **Denial of Service** | Webhook flooding / replay attacks | Repeating identical transaction ID | Redis `SETNX` nonce locks + 300s clock drift rejection | `VERIFIED` |
| **Elevation of Privilege** | Reseller accessing SuperAdmin endpoints | Fetching `/api/v1/tenants` | `EnsureUserRole` middleware blocking unauthorized access with HTTP 403 | `VERIFIED` |

---

## 2. Cryptographic Security Standards

1. **Password Hashing:** Passwords in database use Argon2id / Bcrypt with cost factor 12.
2. **Wi-Fi Voucher PINs:** Stored as salted SHA-256 hashes in `hotspot_vouchers.pin_hash`.
3. **Audit Log Integrity:** Every entry in `tamper_evident_audit_logs` records `previous_hash`, `payload_hash`, and `current_hash` (`SHA256(prev + payload)`). Any manual row alteration breaks the hash chain, detected automatically by `ComplianceService::verifyHashChain()`.
