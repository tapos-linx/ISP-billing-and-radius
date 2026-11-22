# Carrier Production Residual Risks & Mitigations

**Document Version:** 2.2.1  
**Classification:** Operational Risk Management & Incident Prevention  
**Review Cycle:** Quarterly Architecture Review  

---

## 1. Residual Risk Assessment Matrix

While all identified code-level vulnerabilities, credential fallbacks, and logic bugs have been completely eliminated, deployment and operation in real-world carrier network environments entail known infrastructure and external operational risks.

```
+---------------------------------------------------------------------------------------+
| ID   | Risk Description              | Probability | Severity | Mitigation Strategy   |
+======+===============================+=============+==========+=======================+
| R-01 | Host OS Docker Bridge Latency | Low         | Moderate | Bare-Metal Linux Host |
| R-02 | Upstream MFS Gateway Downtime | Moderate    | Moderate | Asynchronous Retries  |
| R-03 | TR-069 Optical Data Variance  | Moderate    | Low      | Telemetry Normalizer  |
| R-04 | Long-Term IPDR Storage Growth | Low         | High     | ClickHouse Cold-Tier  |
| R-05 | BNG UDP Packet Storm Flooding | Low         | High     | Linux Socket Buffers  |
+---------------------------------------------------------------------------------------+
```

---

## 2. Risk Profiles & Mitigation Runbooks

### 2.1 R-01: Windows WSL2 vs. Production Bare-Metal Linux
- **Context:** Development and virtual drill verification occur in Windows Docker Desktop / WSL2. In production, carrier networks handle 10,000+ PPPoE sessions.
- **Risk:** Virtualized network translation overhead in WSL2 adds jitter to high-rate UDP accounting packets.
- **Mitigation:** Production deployment MUST run on bare-metal Ubuntu 24.04 LTS or Debian 12 with Docker Engine configured with `host` networking mode for FreeRADIUS UDP ports 1812/1813 and CoA 3799.

### 2.2 R-02: Upstream MFS Gateway (bKash / Nagad) Intermittent Downtime
- **Context:** Payment networks in Bangladesh undergo maintenance or experience upstream timeout spikes.
- **Risk:** Subscriber initiates payment; webhook or callback confirmation is delayed by upstream partner gateway.
- **Mitigation:**
  1. The billing core provides an automated 48-hour Grace Period preventing abrupt disconnection during MFS maintenance.
  2. RabbitMQ dead-letter queues (DLQ) retry webhook reconciliation every 60 seconds with exponential backoff up to 24 hours.
  3. Reseller POS thermal vouchers and scratch cards provide an immediate offline cash payment fallback.

### 2.3 R-03: TR-069 Optical Telemetry Normalization
- **Context:** Different Optical Network Unit (ONU) vendors (Huawei, ZTE, FiberHome, VSOL) report optical power in varying units (e.g. dBm vs. tenths of dBm or microwatts).
- **Risk:** Misinterpretation of optical light levels leading to false low-light alerts in customer self-care app.
- **Mitigation:** The `SubscriberSelfCareMobileService` normalizes incoming TR-069 telemetry into floating-point dBm and validates that values fall within realistic physical fiber thresholds ($-8.0\text{ dBm}$ to $-32.0\text{ dBm}$) before broadcasting to subscribers.

### 2.4 R-04: BTRC 2-Year IPDR Storage Capacity Exhaustion
- **Context:** BTRC mandates 2-year retention of all NAT translations and IPDR flows.
- **Risk:** Unmonitored disk exhaustion on ClickHouse storage nodes.
- **Calculated Growth Model:**
  $$\text{Storage Rate} = 10,000\text{ Subscribers} \times 1,200\text{ Flows/Day} \times 365\text{ Days} \times 2\text{ Years} \approx 8.76\text{ Billion Flows}$$
  Using ClickHouse LZ4 compression ($\approx 3.2\text{ bytes/record}$), required raw disk is $\approx 28\text{ GB}$.
- **Mitigation:**
  - Automated ClickHouse disk volume alerting at $75\%$ and $85\%$ thresholds via Prometheus.
  - S3/MinIO cold-storage tiering configured in `storage_configuration` for flows older than 180 days.

### 2.5 R-05: High-Rate BNG RADIUS Accounting Packet Floods
- **Context:** Sudden fiber break disconnecting an entire OLT chassis causes hundreds of simultaneous PPPoE Stop packets.
- **Risk:** Linux kernel UDP receive buffer (`rmem_max`) drops incoming packets.
- **Mitigation:** Production host sysctl hardening:
  ```bash
  sysctl -w net.core.rmem_max=67108864
  sysctl -w net.core.rmem_default=33554432
  ```
