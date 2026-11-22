# Authoritative Carrier ISP Architecture Specification

**Document Version:** 2.2.0  
**Status:** Approved Reference Standard  
**Target Scale:** 1,000 to 50,000+ Active Concurrent Subscribers

---

## 1. Single Authoritative Stack Topology

To resolve discrepancies between legacy single-node Docker manifests and carrier-scale distributed topologies, the platform establishes three strictly segregated deployment profiles:

```
+----------------------------------------------------------------------------------------------------+
|                                     CARRIER ARCHITECTURE TOPOLOGY                                  |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|    [Public Wi-Fi / Hotspot]    [Subscriber Mobile App]      [NOC Web Console]    [BNG MikroTik]   |
|            |                               |                       |                   |           |
|            +-------------------------------+-----------------------+                   |           |
|                                            |                                           |           |
|                                            v (HTTPS / REST)                            v (UDP 1812)|
|                        +---------------------------------------+             +-------------------+ |
|                        |       Nginx + Laravel 11 BSS/OSS      |             | FreeRADIUS 3.2    | |
|                        |          (PHP-FPM 8.3 Engine)         |             | (Anycast VIP Mesh)| |
|                        +---------------------------------------+             +-------------------+ |
|                                   |                 |                          |         |         |
|         +-------------------------+                 +------------+             |         |         |
|         |                                                        |             |         |         |
|         v (Fast-Path AAA / Sessions)                             v             v         |         |
|  +-----------------------+                            +-------------------------------+  |         |
|  |  Redis 7.2 Cluster    |                            | Citus 16-Shard Distributed    |<-+         |
|  |  (Sentinel / Fast AAA)|                            | PostgreSQL 16 (Colocated)     |            |
|  +-----------------------+                            +-------------------------------+            |
|                                                                  |                                 |
|                                                                  v (NetFlow v9 / IPDR Stream)      |
|                                                       +-------------------------------+            |
|                                                       | ClickHouse Columnar DB        |            |
|                                                       | (730-Day BTRC Regulatory IPDR)|            |
|                                                       +-------------------------------+            |
+----------------------------------------------------------------------------------------------------+
```

### Profile Matrix
| Environment | Compose Manifest | Primary Database | In-Memory Cache | AAA Cluster | Target Workload |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Local Dev** | `docker-compose.yml` | PostgreSQL 16 (Streaming Replication Standby) | Redis 7 Standalone | 2x FreeRADIUS + HAProxy | Feature development & offline testing |
| **Staging** | `infra/docker-compose.yml` | Citus 12.1 Master + 2x Workers (16 Shards) | Redis Cluster / Sentinel | FreeRADIUS (AAA + CoA UDP 3799) | Full integration testing & Containerlab drill |
| **Production** | `deploy/terraform` / `deploy/ansible` | Citus Multi-Worker Bare-Metal Cluster | Multi-Node Redis Sentinel | Anycast BGP FreeRADIUS Multi-POP Mesh | 50,000+ Concurrent Carrier Traffic |

---

## 2. Distributed Database Architecture (Citus 16-Shard Co-Location)

To satisfy carrier throughput without cross-node network hop penalties, all high-volume tables are co-located on `subscriber_id`:

```
   Distributed Shard Co-location on 'subscriber_id':
   +------------------------------------------------------------------------+
   | Citus Worker 1 (Shards 1..64)                                          |
   |   ├── subscribers (shard_102148)                                       |
   |   ├── radcheck (colocated)                                             |
   |   ├── radreply (colocated)                                             |
   |   ├── invoices (colocated)                                             |
   |   ├── hotspot_vouchers (colocated)                                     |
   |   ├── subscriber_devices (colocated)                                   |
   |   └── mobile_push_notifications (colocated)                            |
   +------------------------------------------------------------------------+
   | Citus Worker 2 (Shards 65..128)                                        |
   |   ├── subscribers (shard_102212)                                       |
   |   └── [All child tables co-located on identical hash partition range]  |
   +------------------------------------------------------------------------+
   | Reference Tables (Replicated to All Nodes):                            |
   |   ├── tariffs                                                          |
   |   ├── nas                                                              |
   |   ├── hotspot_profiles                                                 |
   |   └── ip_pools                                                         |
   +------------------------------------------------------------------------+
```

### Key Query Constraints
1. **`SELECT ... FOR UPDATE` Queries:** Citus requires an equality filter on the partition key (`where('subscriber_id', $subId)`). Locking queries on secondary indexes alone are rejected by the distributed query planner.
2. **Partition Key Immutability:** `subscriber_id` can never be modified via `UPDATE`. Updates must delete and re-insert if a key transformation is required.
3. **Reference Tables:** Tables with low write frequency and high read frequency (`tariffs`, `nas`, `hotspot_profiles`) are designated as reference tables (`create_reference_table`), allowing worker-local joins without network overhead.

---

## 3. High-Speed AAA & RFC 5176 CoA Architecture

1. **Fast-Path Authentication (<0.4ms):**
   - FreeRADIUS queries Redis cluster fast-path (`radius:auth:{username}`) first.
   - If Redis key exists, credentials and rate limits (`Mikrotik-Rate-Limit`) are returned without hitting Citus SQL shards.
   - On cache miss, FreeRADIUS falls back to Citus `radcheck`/`radreply` and populates Redis via write-through caching.
2. **Dynamic Rate Enforcement (RFC 5176 / RFC 3576):**
   - Package upgrades, fair usage throttling, and payment restorations invoke `RadiusCoaService`.
   - Sends raw UDP 3799 `CoA-Request` or `Disconnect-Request` packets to MikroTik BNG NAS.
   - Measures latency via Prometheus histogram `radius_coa_latency_seconds` (<500ms target).
   - Failed attempts are recorded in `failed_coa_jobs` dead-letter queue for automated retry.

---

## 4. Regulatory Columnar Telemetry (ClickHouse IPDR)

- **Retention:** 730 days (2 full calendar years) per Bangladesh Telecommunication Regulatory Commission (BTRC) cyber compliance mandates.
- **Engine:** `MergeTree()` partitioned by `toYYYYMM(event_time)` and ordered by `(framed_ip, event_time, subscriber_id)`.
- **Query Performance:** Sub-second (<100ms) full-scan forensic audit across millions of framed IP allocation records.
