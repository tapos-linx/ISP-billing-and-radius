# Carrier Kubernetes Native Manifests

## Overview
This directory contains vanilla Kubernetes declarative manifests for staging or deploying the carrier ISP billing and RADIUS stack without Helm.

---

## Component Manifests

| Manifest | Purpose | Highlights |
| :--- | :--- | :--- |
| `citus-cluster.yaml` | Citus 16-Shard Database Cluster | Distributed coordinator + worker StatefulSets with persistent storage |
| `clickhouse-cluster.yaml` | ClickHouse IPDR Engine | High-throughput columnar logging with 730-day automated TTL |
| `freeradius-anycast.yaml` | FreeRADIUS AAA Edge DaemonSet | HostNetwork UDP 1812/1813/3799 listeners with BGP Anycast sidecar |
| `laravel-carrier-bss.yaml` | Laravel BSS/OSS Application | Multi-replica deployment with queue workers, Nginx sidecar, and health checks |
| `redis-sentinel-ha.yaml` | Redis Sentinel HA Cache | 3-node Sentinel quorum with automatic primary failover |

---

## Deployment Sequence
```bash
# 1. Create namespace
kubectl create namespace carrier-bss

# 2. Deploy Citus & Redis State
kubectl apply -f deploy/kubernetes/citus-cluster.yaml -n carrier-bss
kubectl apply -f deploy/kubernetes/redis-sentinel-ha.yaml -n carrier-bss
kubectl apply -f deploy/kubernetes/clickhouse-cluster.yaml -n carrier-bss

# 3. Deploy FreeRADIUS Edge & Laravel Core
kubectl apply -f deploy/kubernetes/freeradius-anycast.yaml -n carrier-bss
kubectl apply -f deploy/kubernetes/laravel-carrier-bss.yaml -n carrier-bss
```
