# Carrier RADIUS AAA Verification & Validation Report

**Document Version:** 2.2.1  
**Classification:** Network Engineering & Operations  
**Compliance Standard:** RFC 2865 (RADIUS Auth), RFC 2866 (Accounting), RFC 5176 (CoA/Disconnect)  
**Target Engine:** FreeRADIUS 3.2.x + Citus PostgreSQL 16 Distributed Engine  

---

## 1. Network Architecture Overview

The carrier AAA plane terminates PPPoE, IPoE, and Hotspot Captive Portal sessions from edge Broadband Network Gateways (BNG / MikroTik CCR2216 / CHR) into a distributed FreeRADIUS cluster backed by Citus PostgreSQL.

```
+-----------------------------------------------------------------------+
|                         Edge BNGs (MikroTik CHR / CCR2216)            |
|       PPPoE Dial-in (PAP/CHAP)             Hotspot HTTP Redirect      |
+-----------------------------------------------------------------------+
                     | UDP 1812 (Auth)       | UDP 1813 (Acct)
                     v                       v
+-----------------------------------------------------------------------+
|                    Anycast Virtual IP (10.0.0.254)                    |
|             Active-Active FreeRADIUS Instances (isp_radius)           |
+-----------------------------------------------------------------------+
                     | High-Speed Unix Sockets / TCP Pool (<5ms)
                     v
+-----------------------------------------------------------------------+
|           PostgreSQL 16 Citus Distributed Cluster (16 Shards)         |
|   - radcheck (Auth Credentials)      - radreply (Profiles/VSA)        |
|   - radacct (Monthly Range Sharded)  - radpostauth (Audit Trail)      |
+-----------------------------------------------------------------------+
```

---

## 2. Authentication Protocol Verification

### 2.1 PAP (Password Authentication Protocol)
- **Protocol Flow:** Subscriber sends credentials in plaintext inside encrypted PPPoE tunnel (`Cleartext-Password` in `radcheck`).
- **SQL Query (`mods-available/sql`):**
  ```sql
  SELECT id, username, attribute, value, op 
  FROM radcheck 
  WHERE username = '%{SQL-User-Name}' 
  ORDER BY id;
  ```
- **Validation Criteria:** Returns `Access-Accept` with reply attributes if password matches; returns `Access-Reject` and logs to `radpostauth` on mismatch.

### 2.2 CHAP (Challenge Handshake Authentication Protocol)
- **Protocol Flow:** Edge BNG issues 16-byte challenge. Client responds with MD5(`CHAP-ID + Password + Challenge`).
- **FreeRADIUS Processing:** FreeRADIUS retrieves `Cleartext-Password` from `radcheck`, independently computes the MD5 digest over the challenge, and validates equality.
- **Security Posture:** Immune to replay attacks over the local access loop (OLT/ONU).

---

## 3. Authorization Attributes & MikroTik Vendor-Specific Attributes (VSAs)

Upon successful authentication, FreeRADIUS returns standard RFC attributes and Vendor 14988 (MikroTik) VSAs from `radreply`:

| RADIUS Attribute | Type | Example Carrier Value | Purpose |
| :--- | :--- | :--- | :--- |
| `Framed-Protocol` | RFC 2865 | `PPP` | Protocol encapsulation |
| `Framed-IP-Address` | RFC 2865 | `100.64.12.45` | CGNAT or Public Static IP |
| `Framed-IP-Netmask` | RFC 2865 | `255.255.255.255` | Host route assignment |
| `Mikrotik-Rate-Limit` | Vendor 14988 | `20M/20M 30M/30M 15M/15M 10/10 8 10M/10M` | Rx/Tx rate, burst, threshold, priority |
| `Acct-Interim-Interval` | RFC 2869 | `300` | 5-minute telemetry heartbeat |
| `Session-Timeout` | RFC 2865 | `86400` | Mandatory 24-hour re-authentication |

---

## 4. Accounting (RFC 2866) & High-Volume Octet Tracking

Accounting packets are delivered via UDP port 1813 and stored in the monthly-partitioned `radacct` table.

### 4.1 64-Bit Gigabit Counter Rollover Handling
Carrier links exceeding 4 Gbps overflow the standard 32-bit `Acct-Input-Octets` counter in minutes. FreeRADIUS automatically calculates 64-bit totals using RFC 2869 `Acct-[Input|Output]-Gigawords`:

$$\text{Total Bytes} = (\text{Acct-Gigawords} \times 2^{32}) + \text{Acct-Octets}$$

### 4.2 Declarative Monthly Range Partitioning (`radacct`)
To prevent multi-million row B-tree index bloat, `radacct` uses native range partitioning:
```sql
CREATE TABLE radacct_y2026m11 PARTITION OF radacct
    FOR VALUES FROM ('2026-11-01 00:00:00+00') TO ('2026-12-01 00:00:00+00');
```
- **Pruning Verification:** `EXPLAIN ANALYZE SELECT * FROM radacct WHERE acctstarttime >= '2026-11-15'` executes exclusively against `radacct_y2026m11`, skipping all historical partitions.
- **Index Hotspots:** Unique B-tree indices on `(username, acctstarttime)`, `(acctsessionid)`, and partial index on active sessions:
  ```sql
  CREATE INDEX idx_radacct_active_ip ON radacct(framedipaddress) 
  WHERE acctstoptime IS NULL;
  ```

---

## 5. High Availability & Failover Verification

1. **Anycast VIP Resiliency:**
   Edge BNGs configure dual RADIUS servers:
   - Primary: `10.0.0.254` (VIP)
   - Secondary: `10.0.0.2` (Direct node backup)
2. **Dead NAS & Dead Host Detection:**
   RouterOS marks RADIUS server dead if timeout exceeds 3000ms over 3 consecutive attempts, triggering sub-second failover to the secondary node.
3. **Database Failover Safety:**
   FreeRADIUS connection pool utilizes PostgreSQL `target_session_attrs=read-write` to guarantee write traffic immediately routes to the promoted primary replica upon failover.
