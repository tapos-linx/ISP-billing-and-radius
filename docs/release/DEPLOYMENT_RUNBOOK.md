# Carrier Production Deployment Runbook (Day-1 / Day-2)

**Document Version:** 2.2.1  
**Classification:** DevOps / NetOps Implementation Guide  
**Target Environment:** Bare-Metal Linux / Production Cloud Staging  
**Estimated Deployment Time:** 25 Minutes  

---

## 1. System Requirements & Prerequisites

### Minimum Hardware Baseline (1,000 to 5,000 Subscribers)
- **CPU:** 8 vCPU (Dedicated x86_64 cores, 2.8GHz+)
- **Memory:** 16 GB ECC RAM
- **Storage:** 200 GB Enterprise NVMe SSD (RAID-10 or ZFS mirror)
- **Network:** 2x 10GbE SFP+ (Bonded LACP: Ingress BNG VLANs + Management/Storage)
- **Operating System:** Ubuntu 24.04 LTS Server or Debian 12 (Bookworm)
- **Container Engine:** Docker Engine v26.0+ & Docker Compose v2.26+

---

## 2. Step-by-Step Deployment Protocol

### Step 1: Clone Repository & Create Dedicated Branch
```bash
git clone https://github.com/tapos-linx/ISP-billing-and-radius.git /opt/isp-billing
cd /opt/isp-billing
git checkout hardening/production-certification
```

### Step 2: Initialize Docker Secrets (Fail-Closed Baseline)
Generate high-entropy random secrets and mount them securely:
```bash
mkdir -p /run/secrets
chmod 700 /run/secrets

openssl rand -base64 32 | tr -d '\r\n' > /run/secrets/db_password
openssl rand -base64 32 | tr -d '\r\n' > /run/secrets/repl_password
openssl rand -base64 32 | tr -d '\r\n' > /run/secrets/redis_password
openssl rand -base64 24 | tr -d '\r\n' > /run/secrets/radius_secret
openssl rand -base64 24 | tr -d '\r\n' > /run/secrets/rabbitmq_password
openssl rand -hex 32 | tr -d '\r\n' > /run/secrets/bkash_secret
openssl rand -hex 32 | tr -d '\r\n' > /run/secrets/nagad_secret

chmod 600 /run/secrets/*
```

### Step 3: Configure Environment Variables
Create `/opt/isp-billing/.env` from `.env.example`:
```bash
cp backend/.env.example backend/.env
# Set APP_ENV=production, APP_DEBUG=false, and generate master key:
docker run --rm -v /opt/isp-billing/backend:/app -w /app composer:2.7 composer install --no-dev --optimize-autoloader
docker run --rm -v /opt/isp-billing/backend:/app -w /app php:8.2-cli php artisan key:generate
```

### Step 4: Bootstrap the Infrastructure Stack
Launch core storage, database, caching, and message bus services:
```bash
cd /opt/isp-billing
docker compose -f infra/docker-compose.yml up -d citus_master citus_worker_1 citus_worker_2 redis_master redis_replica clickhouse rabbitmq
```

Verify Citus 16-shard cluster health:
```bash
docker exec -i citus_master psql -U postgres -d isp_billing -c "SELECT * FROM citus_get_active_worker_nodes();"
```

### Step 5: Execute Citus Database Migrations (01 to 20)
Run all schema files sequentially:
```bash
for file in $(ls -v stage1/sql/*.sql); do
    echo "[MIGRATE] Applying $file..."
    docker exec -i citus_master psql -U postgres -d isp_billing < "$file"
done
```

### Step 6: Start FreeRADIUS & Network Control Services
```bash
docker compose -f infra/docker-compose.yml up -d isp_radius_1 isp_radius_2
```
Verify FreeRADIUS listening on UDP ports 1812, 1813:
```bash
netstat -ulnp | grep -E '1812|1813'
```

### Step 7: Launch Laravel Backend & Queue Workers
```bash
docker compose -f infra/docker-compose.yml up -d backend_api queue_worker scheduler
```

---

## 3. Post-Deployment Smoke Tests & Validation Gate

Execute the automated verification script:
```bash
# 1. Verify HTTP Healthcheck
curl -f http://localhost:8000/api/v1/health || exit 1

# 2. Test RADIUS Authentication with radclient
echo "User-Name = test_smoke_user
User-Password = SmokePassword2026!" | radclient -x 127.0.0.1:1812 auth "$(cat /run/secrets/radius_secret)"

# 3. Verify Redis Nonce Acquisition
docker exec -i redis_master redis-cli -a "$(cat /run/secrets/redis_password)" ping

# 4. Verify ClickHouse Ingestion
docker exec -i clickhouse clickhouse-client --query "SELECT count() FROM ipdr_logs"
```

When all 4 checks return exit code 0, declare the cluster operational.
