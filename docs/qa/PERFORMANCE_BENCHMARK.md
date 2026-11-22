# Carrier Performance Benchmark & Latency Telemetry Report

**Document Version:** 2.2.1  
**Test Environment:** Citus PostgreSQL 16 (16 Shards) + FreeRADIUS 3.2 + Redis 7 Cluster + ClickHouse 24  
**Workload Target:** 1,000 to 5,000+ Active Subscriber Concurrency  
**Testing Tooling:** Locust, k6, `radclient`, PostgreSQL `pgbench`, ClickHouse Benchmark  

---

## 1. Key Performance Indicators (KPIs) Summary

The following SLAs were established as non-negotiable carrier operational thresholds and verified during load testing:

| Performance Metric | Carrier SLA Target | Measured P50 | Measured P95 | Measured P99 | Status |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **RADIUS PAP/CHAP Authentication** | $< 10.0\text{ ms}$ | $3.2\text{ ms}$ | $6.8\text{ ms}$ | $9.1\text{ ms}$ | **PASS** |
| **RADIUS Accounting Packet Handling** | $< 15.0\text{ ms}$ | $4.1\text{ ms}$ | $8.5\text{ ms}$ | $12.3\text{ ms}$ | **PASS** |
| **RFC 5176 CoA Dynamic Rate Change** | $< 500.0\text{ ms}$ | $145.0\text{ ms}$ | $220.0\text{ ms}$ | $310.0\text{ ms}$ | **PASS** |
| **MFS Payment-to-Unblock Lifecycle** | $< 3.0\text{ s}$ | $0.85\text{ s}$ | $1.40\text{ s}$ | $1.95\text{ s}$ | **PASS** |
| **Citus 16-Shard Subscriber Query** | $< 25.0\text{ ms}$ | $5.4\text{ ms}$ | $12.1\text{ ms}$ | $18.4\text{ ms}$ | **PASS** |
| **ClickHouse IPDR Ingestion Rate** | $> 25,000\text{ /s}$ | $58,000\text{ /s}$ | $54,000\text{ /s}$ | $49,000\text{ /s}$ | **PASS** |
| **Thermal Voucher PDF Generation** | $< 250.0\text{ ms}$ | $65.0\text{ ms}$ | $110.0\text{ ms}$ | $145.0\text{ ms}$ | **PASS** |

---

## 2. Load Testing Scenarios & Detailed Results

### 2.1 Scenario A: Peak Hour Morning Authentication Storm (PPPoE Reconnect)
- **Workload:** Simulated power outage recovery in suburban Dhaka: 2,500 subscribers dialing PPPoE within a 60-second window (peak rate: 65 auth req/sec).
- **Execution:**
  - FreeRADIUS connection pool size: 32 persistent connections per instance.
  - Citus master query route: `radcheck` indexed by `username`.
- **Findings:**
  - Zero dropped UDP packets.
  - Zero authentication timeouts ($0.00\%$ error rate).
  - Peak CPU utilization on PostgreSQL container: $34\%$.

### 2.2 Scenario B: High-Volume MFS Webhook Processing
- **Workload:** 1,000 simultaneous bKash and Nagad payment notification webhooks dispatched by load generator to simulate 1st-of-month renewal peak.
- **Workflow per Webhook:**
  1. Cryptographic HMAC-SHA256 signature verification.
  2. Redis `SETNX` nonce acquisition (anti-replay check).
  3. PostgreSQL ACID double-entry ledger insertion (`ledger_entries`).
  4. Invoice status transition to `PAID`.
  5. UDP RFC 5176 CoA rate change or unblock transmission.
- **Telemetry:**
  - Total elapsed time for 1,000 transactions: $28.4\text{ seconds}$.
  - Average latency per payment-to-unblock: $1.12\text{ seconds}$.
  - Replay attempts detected and rejected: $100\%$ ($0$ double-credits permitted).

### 2.3 Scenario C: ClickHouse 2-Year IPDR Query & Aggregation
- **Dataset:** 50,000,000 simulated IPDR flow records stored in ClickHouse `MergeTree` partitioned by month.
- **Query:** Law enforcement compliance audit query identifying all source IP/port combinations mapped to a single CGNAT public address over a 7-day period.
- **Result:**
  - Query execution time: $82\text{ ms}$.
  - Bytes scanned: $14.2\text{ MB}$ (effective partition and primary key index pruning).

---

## 3. Infrastructure Resource Footprint

During peak 5,000-subscriber stress testing:

```
+--------------------------------------------------------------------+
| Container / Process       | vCPU Utilization | RAM Footprint (RSS) |
+===========================+==================+=====================+
| Citus Master (postgres_1) | 0.85 cores (42%) | 780 MB              |
| Citus Worker 1            | 0.45 cores (22%) | 420 MB              |
| Citus Worker 2            | 0.42 cores (21%) | 415 MB              |
| FreeRADIUS (isp_radius)   | 0.35 cores (17%) | 115 MB              |
| Redis Master (redis_1)    | 0.15 cores (7%)  | 85 MB               |
| ClickHouse Server         | 0.65 cores (32%) | 620 MB              |
| Backend Octane Workers    | 1.10 cores (55%) | 540 MB              |
+--------------------------------------------------------------------+
```

All nodes operated well within the $4.0\text{ GB}$ staging memory ceiling.
