# Component Dependency & Subsystem Map

**Architecture Version:** 2.2.0  
**Scope:** Carrier BSS/OSS Subsystem Interdependencies

---

## 1. Subsystem Interaction Matrix

```mermaid
graph TD
    subgraph Edge Layer
        ClientMobile[React Native / Flutter Mobile App]
        CaptivePortal[Hotspot Captive Portal]
        NocConsole[NOC Operations Console]
        MikrotikBNG[MikroTik CHR / Hardware BNG]
    end

    subgraph Application & Gateway Layer
        Nginx[Nginx Reverse Proxy]
        LaravelBSS[Laravel 11 BSS/OSS Core]
        FreeRadiusAAA[FreeRADIUS 3.2 Anycast Cluster]
        RabbitMQ[RabbitMQ Event Broker]
    end

    subgraph Data & Persistence Layer
        CitusMaster[Citus Master PostgreSQL 16]
        CitusWorker1[Citus Worker 1 Shards 1-64]
        CitusWorker2[Citus Worker 2 Shards 65-128]
        RedisCluster[Redis 7 Fast-Path AAA & Cache]
        ClickHouseDB[ClickHouse 730-Day IPDR Engine]
    end

    subgraph External & Telemetry Integrations
        MfsGateways[bKash / Nagad / SSLCommerz]
        FcmPush[Firebase Cloud Messaging v1]
        Prometheus[Prometheus Metrics Scraper]
        Grafana[Grafana Carrier Dashboard]
    end

    ClientMobile -->|HTTPS /api/v1/mobile| Nginx
    CaptivePortal -->|HTTPS /hotspot| Nginx
    NocConsole -->|HTTPS /noc| Nginx
    Nginx --> LaravelBSS

    MikrotikBNG -->|UDP 1812/1813 Auth/Acct| FreeRadiusAAA
    FreeRadiusAAA -->|Read Fast-Path| RedisCluster
    FreeRadiusAAA -->|Read/Write AAA SQL| CitusMaster

    LaravelBSS -->|Dispatches Events| RabbitMQ
    LaravelBSS -->|CoA UDP 3799| MikrotikBNG
    LaravelBSS -->|NetFlow / IPDR| ClickHouseDB
    LaravelBSS -->|Double-Entry SQL| CitusMaster
    LaravelBSS -->|FCM Push Dispatch| FcmPush
    LaravelBSS -->|Webhook Settlement| MfsGateways

    CitusMaster --> CitusWorker1
    CitusMaster --> CitusWorker2

    Prometheus -->|Scrape /metrics| LaravelBSS
    Prometheus -->|Scrape AAA Latency| FreeRadiusAAA
    Grafana --> Prometheus
```

---

## 2. Dependency Breakdown by Service

| Service | Upstream Dependencies | Downstream Consumers | Failure Mode & Fallback |
| :--- | :--- | :--- | :--- |
| **Laravel BSS/OSS** | Citus Master, Redis Cluster, RabbitMQ | Mobile Clients, Admin Users, Webhooks | 503 Maintenance Page; Async retry queues |
| **FreeRADIUS AAA** | Redis Cluster, Citus Master | MikroTik BNG, Wi-Fi APs | Local Redis fallback; fail-open or walled garden |
| **Citus Master** | Citus Workers 1 & 2 | Laravel BSS, FreeRADIUS | Automated PostgreSQL failover standby |
| **ClickHouse** | RabbitMQ / IPDR Ingest | BTRC Cyber Forensic Audits | Local buffer spooling if ClickHouse offline |
| **Mobile Push Service** | FCM v1 HTTP API | Subscriber Devices | Mock sandbox mode; Citus notification log |
