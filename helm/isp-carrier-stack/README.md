# ISP Carrier Stack Helm Chart

## Overview
The `isp-carrier-stack` Helm chart packages the complete Carrier BSS/OSS, FreeRADIUS AAA, Citus distributed database, Redis Sentinel, and ClickHouse IPDR cluster for Kubernetes.

---

## Chart Components (`templates/`)
- `backend-deployment.yaml`: Laravel 11 BSS/OSS container with Horizon/queue workers and readiness/liveness probes.
- `citus-cluster.yaml`: Coordinator and worker StatefulSets with persistent volume claims.
- `clickhouse-ipdr.yaml`: ClickHouse 24.x instance with 730-day automated TTL data volume.
- `freeradius-daemonset.yaml`: FreeRADIUS 3.2.x edge listeners running in `hostNetwork` mode for UDP 1812/1813/3799.
- `redis-sentinel.yaml`: HA Redis with Sentinel quorum.
- `ingress.yaml`: Ingress configuration with TLS termination for BSS API, NOC console, and Self-Care portal.
- `hpa.yaml`: Horizontal Pod Autoscaler targeting CPU/Memory thresholds.
- `vault-agent-sidecar.yaml`: HashiCorp Vault agent sidecar for zero-secret credential injection.

---

## Installation & Configuration

### Prerequisites
- Kubernetes 1.28+
- Helm 3.12+
- StorageClass supporting dynamic volume provisioning (e.g., `gp3` on AWS, `standard-rwo` on GCP, or `local-path` on bare metal).

### Install Chart
```bash
helm install isp-carrier-stack ./helm/isp-carrier-stack \
  --namespace carrier-bss \
  --create-namespace \
  -f ./helm/isp-carrier-stack/values.yaml
```

### Upgrade Deployment
```bash
helm upgrade isp-carrier-stack ./helm/isp-carrier-stack \
  --namespace carrier-bss \
  -f ./helm/isp-carrier-stack/values.yaml
```

### Rollback Release
```bash
helm rollback isp-carrier-stack 1 --namespace carrier-bss
```
