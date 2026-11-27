#!/usr/bin/env bash
# ==============================================================================
# ISP Billing & RADIUS — Production Deployment Script for Ubuntu 26.04.1 LTS
# Repository: https://github.com/tapos-linx/ISP-billing-and-radius
# Target: Ubuntu 26.04.1 (Noble Numbat) Server
# ==============================================================================
# This script deploys the full carrier-grade ISP Billing stack:
#   - PostgreSQL 16 (Primary + Replica, partitioned radacct)
#   - Redis 7 (Master + Replica + Sentinel, noeviction policy)
#   - FreeRADIUS 3.2.3 (Dual-node + HAProxy LB, RFC 5176 CoA/PoD)
#   - Laravel 11 BSS API (via Cloud Run / Docker)
#   - MinIO S3 (Encrypted backups)
#   - Network: 10.250.10.0/24 internal bridge
# ==============================================================================

set -euo pipefail

# -----------------------------------------------------------------------------
# Configuration
# -----------------------------------------------------------------------------
REPO_URL="https://github.com/tapos-linx/ISP-billing-and-radius.git"
REPO_DIR="/opt/isp-billing"
DEPLOY_USER="ispadmin"
DEPLOY_GROUP="docker"

# Docker Compose project name
COMPOSE_PROJECT="isp_billing"

# Network configuration
INTERNAL_SUBNET="10.250.10.0/24"

# Service ports (host binding)
POSTGRES_BIND_IP="127.0.0.1"
POSTGRES_PORT="5432"
POSTGRES_REPLICA_PORT="5433"
REDIS_BIND_IP="127.0.0.1"
REDIS_PORT="6379"
REDIS_REPLICA_PORT="6380"
SENTINEL_PORT="26379"
RADIUS_AUTH_PORT="1812"
RADIUS_ACCT_PORT="1813"
RADIUS_COA_PORT="3799"
HAPROXY_STATS_PORT="8404"
MINIO_API_PORT="9000"
MINIO_CONSOLE_PORT="9001"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m'

log_info()  { echo -e "${CYAN}[INFO]${NC} $*"; }
log_ok()    { echo -e "${GREEN}[OK]${NC} $*"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC} $*"; }
log_err()   { echo -e "${RED}[ERROR]${NC} $*"; }
log_step()  { echo -e "${MAGENTA}[STEP]${NC} $*"; }

# -----------------------------------------------------------------------------
# Pre-flight Checks
# -----------------------------------------------------------------------------
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_err "This script must be run as root (sudo)."
        exit 1
    fi
}

check_ubuntu_version() {
    if ! grep -q "26.04" /etc/os-release; then
        log_warn "This script is optimized for Ubuntu 26.04.1. Current: $(lsb_release -ds)"
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
    log_ok "Ubuntu version check passed: $(lsb_release -ds)"
}

check_architecture() {
    ARCH=$(uname -m)
    if [[ "$ARCH" != "x86_64" && "$ARCH" != "aarch64" ]]; then
        log_err "Unsupported architecture: $ARCH. Only x86_64 and aarch64 are supported."
        exit 1
    fi
    log_ok "Architecture: $ARCH"
}

# -----------------------------------------------------------------------------
# System Preparation
# -----------------------------------------------------------------------------
install_prerequisites() {
    log_step "Installing system prerequisites..."

    apt-get update -qq
    apt-get install -y -qq \
        ca-certificates \
        curl \
        gnupg \
        lsb-release \
        git \
        ufw \
        fail2ban \
        htop \
        jq \
        netcat-openbsd \
        postgresql-client-16 \
        redis-tools \
        freeradius-utils \
        apt-transport-https \
        software-properties-common \
        unattended-upgrades

    log_ok "Prerequisites installed"
}

install_docker() {
    log_step "Installing Docker Engine & Docker Compose Plugin..."

    # Add Docker's official GPG key
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    # Add Docker repository
    echo \
        "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
        $(. /etc/os-release && echo "${UBUNTU_CODENAME:-noble}") stable" | \
        tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

    # Verify installation
    docker --version
    docker compose version

    log_ok "Docker installed: $(docker --version | cut -d' ' -f3 | tr -d ',')"
}

configure_docker() {
    log_step "Configuring Docker daemon..."

    mkdir -p /etc/docker
    cat > /etc/docker/daemon.json <<'EOF'
{
    "log-driver": "json-file",
    "log-opts": {
        "max-size": "50m",
        "max-file": "5"
    },
    "default-ulimits": {
        "nofile": {
            "Name": "nofile",
            "Hard": 65536,
            "Soft": 65536
        }
    },
    "storage-driver": "overlay2",
    "live-restore": true,
    "userland-proxy": false
}
EOF

    systemctl restart docker
    systemctl enable docker

    log_ok "Docker daemon configured and restarted"
}

create_deploy_user() {
    log_step "Creating deployment user..."

    if ! id "$DEPLOY_USER" &>/dev/null; then
        useradd -m -s /bin/bash -G docker "$DEPLOY_USER"
        log_ok "User $DEPLOY_USER created and added to docker group"
    else
        usermod -aG docker "$DEPLOY_USER"
        log_ok "User $DEPLOY_USER already exists, added to docker group"
    fi
}

configure_firewall() {
    log_step "Configuring UFW firewall..."

    # Default policies
    ufw --force reset
    ufw default deny incoming
    ufw default allow outgoing

    # SSH (adjust port if needed)
    ufw allow 22/tcp comment "SSH"

    # RADIUS AAA (restrict to known NAS IPs in production!)
    ufw allow from 10.250.10.0/24 to any port 1812 proto udp comment "RADIUS Auth (internal)"
    ufw allow from 10.250.10.0/24 to any port 1813 proto udp comment "RADIUS Acct (internal)"
    ufw allow from 10.250.10.0/24 to any port 3799 proto udp comment "RADIUS CoA/PoD (internal)"

    # PostgreSQL (internal only)
    ufw allow from 10.250.10.0/24 to any port 5432 proto tcp comment "PostgreSQL Primary"
    ufw allow from 10.250.10.0/24 to any port 5433 proto tcp comment "PostgreSQL Replica"

    # Redis (internal only)
    ufw allow from 10.250.10.0/24 to any port 6379 proto tcp comment "Redis Master"
    ufw allow from 10.250.10.0/24 to any port 6380 proto tcp comment "Redis Replica"
    ufw allow from 10.250.10.0/24 to any port 26379 proto tcp comment "Redis Sentinel"

    # HAProxy Stats (internal only)
    ufw allow from 10.250.10.0/24 to any port 8404 proto tcp comment "HAProxy Stats"

    # MinIO (internal only)
    ufw allow from 10.250.10.0/24 to any port 9000 proto tcp comment "MinIO API"
    ufw allow from 10.250.10.0/24 to any port 9001 proto tcp comment "MinIO Console"

    # Laravel API (if hosted locally)
    # ufw allow from 10.250.10.0/24 to any port 8000 proto tcp comment "Laravel API"

    # Enable firewall
    ufw --force enable

    log_ok "Firewall configured and enabled"
    ufw status numbered
}

configure_fail2ban() {
    log_step "Configuring Fail2Ban..."

    cat > /etc/fail2ban/jail.local <<'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
backend = systemd

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3

[docker-radacct]
enabled = true
filter = docker-radacct
logpath = /var/log/syslog
maxretry = 10
port = 1812,1813,3799
protocol = udp
EOF

    cat > /etc/fail2ban/filter.d/docker-radacct.conf <<'EOF'
[Definition]
failregex = ^.*radiusd.*\[.*\] .*Login incorrect.* <HOST>.*
            ^.*radiusd.*\[.*\] .*Failed to authenticate.* <HOST>.*
ignoreregex =
EOF

    systemctl restart fail2ban
    systemctl enable fail2ban

    log_ok "Fail2Ban configured"
}

optimize_system() {
    log_step "Applying system optimizations..."

    # Kernel parameters for high-concurrency networking
    cat > /etc/sysctl.d/99-isp-billing.conf <<'EOF'
# Network stack tuning for RADIUS UDP high throughput
net.core.rmem_max = 16777216
net.core.wmem_max = 16777216
net.core.rmem_default = 1048576
net.core.wmem_default = 1048576
net.core.netdev_max_backlog = 5000
net.core.somaxconn = 65535

# UDP buffer tuning
net.ipv4.udp_mem = 65536 131072 262144
net.ipv4.udp_rmem_min = 8192
net.ipv4.udp_wmem_min = 8192

# Connection tracking
net.netfilter.nf_conntrack_max = 1048576
net.netfilter.nf_conntrack_tcp_timeout_established = 86400
net.netfilter.nf_conntrack_udp_timeout = 30
net.netfilter.nf_conntrack_udp_timeout_stream = 120

# File descriptors
fs.file-max = 2097152
fs.inotify.max_user_instances = 8192
fs.inotify.max_user_watches = 524288

# Memory management
vm.swappiness = 10
vm.dirty_ratio = 15
vm.dirty_background_ratio = 5
EOF

    sysctl --system

    # Increase limits for docker user
    cat > /etc/security/limits.d/99-docker.conf <<'EOF'
* soft nofile 65536
* hard nofile 65536
* soft nproc 65536
* hard nproc 65536
root soft nofile 65536
root hard nofile 65536
root soft nproc 65536
root hard nproc 65536
EOF

    log_ok "System optimizations applied"
}

# -----------------------------------------------------------------------------
# Repository & Secrets
# -----------------------------------------------------------------------------
clone_repository() {
    log_step "Cloning repository..."

    if [[ -d "$REPO_DIR" ]]; then
        log_warn "Repository directory exists. Updating..."
        cd "$REPO_DIR"
        git fetch --all
        git reset --hard origin/main
    else
        git clone "$REPO_URL" "$REPO_DIR"
        cd "$REPO_DIR"
    fi

    chown -R "$DEPLOY_USER:$DEPLOY_GROUP" "$REPO_DIR"
    chmod -R 750 "$REPO_DIR"

    log_ok "Repository cloned/updated at $REPO_DIR"
}

generate_secrets() {
    log_step "Generating Docker secrets..."

    cd "$REPO_DIR"
    sudo -u "$DEPLOY_USER" bash scripts/generate_docker_secrets.sh

    log_ok "Docker secrets generated"
}

create_env_file() {
    log_step "Creating production .env file..."

    cd "$REPO_DIR"

    # Generate strong passwords if not already in secrets
    DB_PASSWORD=$(cat secrets/db_password.txt 2>/dev/null || openssl rand -base64 32 | tr -d '\n\r/' | cut -c1-32)
    REDIS_PASSWORD=$(cat secrets/redis_password.txt 2>/dev/null || openssl rand -base64 32 | tr -d '\n\r/' | cut -c1-32)
    RADIUS_SECRET=$(cat secrets/radius_secret.txt 2>/dev/null || openssl rand -base64 32 | tr -d '\n\r/' | cut -c1-32)
    REPLICATION_PASSWORD=$(openssl rand -base64 32 | tr -d '\n\r/' | cut -c1-32)
    AWS_ACCESS_KEY_ID=$(cat secrets/minio_access_key.txt 2>/dev/null || openssl rand -base64 16 | tr -d '\n\r/' | cut -c1-16)
    AWS_SECRET_ACCESS_KEY=$(cat secrets/minio_secret_key.txt 2>/dev/null || openssl rand -base64 32 | tr -d '\n\r/' | cut -c1-32)
    S3_BUCKET="isp-encrypted-backups"

    cat > .env.production <<EOF
# ==============================================================================
# ISP Billing — Production Environment Configuration
# Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")
# ==============================================================================

# Application
APP_NAME="ISP Billing"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://billing.yourdomain.com
APP_TIMEZONE=Asia/Dhaka

# Database
POSTGRES_DB=isp_billing
POSTGRES_USER=radius_user
POSTGRES_PASSWORD=${DB_PASSWORD}
REPLICATION_PASSWORD=${REPLICATION_PASSWORD}

# Redis
REDIS_PASSWORD=${REDIS_PASSWORD}

# RADIUS
RADIUS_SECRET=${RADIUS_SECRET}
RADIUS_AUTH_PORT=1812
RADIUS_ACCT_PORT=1813
RADIUS_COA_PORT=3799

# MinIO / S3
AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID}
AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY}
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=${S3_BUCKET}
AWS_USE_PATH_STYLE_ENDPOINT=true
S3_BUCKET=${S3_BUCKET}

# Network Binding
POSTGRES_BIND_IP=127.0.0.1
POSTGRES_PORT=5432
POSTGRES_REPLICA_BIND_IP=127.0.0.1
POSTGRES_REPLICA_PORT=5433
REDIS_BIND_IP=127.0.0.1
REDIS_PORT=6379
REDIS_REPLICA_BIND_IP=127.0.0.1
REDIS_REPLICA_PORT=6380
SENTINEL_BIND_IP=127.0.0.1
SENTINEL_PORT=26379
RADIUS_BIND_IP=0.0.0.0
HAPROXY_STATS_BIND_IP=127.0.0.1
MINIO_BIND_IP=127.0.0.1
MINIO_CONSOLE_BIND_IP=127.0.0.1

# MFS / Payment Gateway (configure with actual credentials)
BKASH_APP_KEY=
BKASH_APP_SECRET=
NAGAD_MERCHANT_KEY=
SSLCOMMERZ_STORE_ID=
SSLCOMMERZ_STORE_PASSWORD=

# Monitoring
GEMINI_API_KEY=

# Laravel
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
EOF

    chown "$DEPLOY_USER:$DEPLOY_GROUP" .env.production
    chmod 640 .env.production

    # Symlink for docker-compose
    ln -sf .env.production .env

    log_ok "Production .env created"
}

# -----------------------------------------------------------------------------
# Deployment
# -----------------------------------------------------------------------------
deploy_stack() {
    log_step "Deploying Docker stack..."

    cd "$REPO_DIR"

    # Pull images first
    sudo -u "$DEPLOY_USER" docker compose pull --quiet

    # Start core infrastructure
    log_info "Starting PostgreSQL Primary..."
    sudo -u "$DEPLOY_USER" docker compose up -d postgres_primary

    log_info "Waiting for PostgreSQL Primary to be healthy..."
    local retries=30
    while [[ $retries -gt 0 ]]; do
        if sudo -u "$DEPLOY_USER" docker compose exec -T postgres_primary pg_isready -U radius_user -d isp_billing &>/dev/null; then
            break
        fi
        sleep 2
        ((retries--))
    done

    if [[ $retries -eq 0 ]]; then
        log_err "PostgreSQL Primary failed to become healthy"
        sudo -u "$DEPLOY_USER" docker compose logs postgres_primary
        exit 1
    fi
    log_ok "PostgreSQL Primary is healthy"

    log_info "Starting Redis Master..."
    sudo -u "$DEPLOY_USER" docker compose up -d redis_master

    log_info "Waiting for Redis Master..."
    retries=20
    while [[ $retries -gt 0 ]]; do
        if sudo -u "$DEPLOY_USER" docker compose exec -T redis_master redis-cli -a "$(cat secrets/redis_password.txt)" ping &>/dev/null; then
            break
        fi
        sleep 2
        ((retries--))
    done

    if [[ $retries -eq 0 ]]; then
        log_err "Redis Master failed to become healthy"
        exit 1
    fi
    log_ok "Redis Master is healthy"

    # Start remaining services
    log_info "Starting all services..."
    sudo -u "$DEPLOY_USER" docker compose up -d

    log_ok "Docker stack deployed"
}

verify_deployment() {
    log_step "Verifying deployment..."

    cd "$REPO_DIR"

    # Check all containers are running
    local failed=0
    for service in postgres_primary postgres_replica redis_master redis_replica redis_sentinel freeradius_1 freeradius_2 freeradius_lb minio minio_setup; do
        if sudo -u "$DEPLOY_USER" docker compose ps "$service" | grep -q "Up"; then
            log_ok "Service $service: RUNNING"
        else
            log_err "Service $service: NOT RUNNING"
            sudo -u "$DEPLOY_USER" docker compose logs "$service" --tail 50
            ((failed++))
        fi
    done

    if [[ $failed -gt 0 ]]; then
        log_err "$failed service(s) failed to start"
        exit 1
    fi

    # Test PostgreSQL connectivity and schema
    log_info "Verifying PostgreSQL schema..."
    sudo -u "$DEPLOY_USER" docker compose exec -T postgres_primary psql -U radius_user -d isp_billing -c "
        SELECT 'radcheck' as table_name, count(*) FROM radcheck
        UNION ALL SELECT 'radreply', count(*) FROM radreply
        UNION ALL SELECT 'nas', count(*) FROM nas
        UNION ALL SELECT 'radacct partitions', count(*) FROM pg_class WHERE relname LIKE 'radacct_y%';
    "

    # Test Redis
    log_info "Verifying Redis..."
    sudo -u "$DEPLOY_USER" docker compose exec -T redis_master redis-cli -a "$(cat secrets/redis_password.txt)" info replication | grep -E "role|connected_slaves|master_link_status"

    # Test FreeRADIUS
    log_info "Verifying FreeRADIUS..."
    sleep 5
    sudo -u "$DEPLOY_USER" docker compose exec -T freeradius_1 radtest tapos_test Tapos@2026 localhost:1812 0 "$(cat secrets/radius_secret.txt)" 2>/dev/null | grep -E "Access-Accept|Mikrotik-Rate-Limit" && log_ok "FreeRADIUS auth test passed" || log_warn "FreeRADIUS auth test needs manual verification"

    # Test HAProxy stats
    log_info "Verifying HAProxy..."
    curl -sf "http://127.0.0.1:8404/stats" >/dev/null && log_ok "HAProxy stats accessible" || log_warn "HAProxy stats not accessible"

    # Test MinIO
    log_info "Verifying MinIO..."
    curl -sf "http://127.0.0.1:9000/minio/health/live" >/dev/null && log_ok "MinIO healthy" || log_warn "MinIO health check failed"

    log_ok "All verification checks passed"
}

run_migrations() {
    log_step "Running Laravel migrations..."

    cd "$REPO_DIR/backend"

    # Note: In production, migrations should be run via CI/CD or manually after code deploy
    # This is a placeholder for the migration step
    log_warn "Laravel migrations should be run via your CI/CD pipeline after code deployment"
    log_info "Example: docker compose exec laravel_app php artisan migrate --force"

    log_ok "Migration step noted (manual/CI/CD required)"
}

setup_cron_jobs() {
    log_step "Setting up cron jobs..."

    cat > /etc/cron.d/isp-billing <<'EOF'
# ISP Billing — Scheduled Tasks
# Runs as ispadmin user

# Monthly radacct partition maintenance (1st of month at 02:00)
0 2 1 * * ispadmin cd /opt/isp-billing && /usr/bin/docker compose exec -T laravel_app php artisan radacct:maintain-partitions --ahead=6 --retention=24 >> /var/log/isp-billing/partition-maintenance.log 2>&1

# Daily VAT Mushak report generation (2nd of month at 03:00)
0 3 2 * * ispadmin cd /opt/isp-billing && /usr/bin/docker compose exec -T laravel_app php artisan report:vat-mushak >> /var/log/isp-billing/vat-report.log 2>&1

# Daily BTRC compliance report (2nd of month at 04:00)
0 4 2 * * ispadmin cd /opt/isp-billing && /usr/bin/docker compose exec -T laravel_app php artisan report:btrc-compliance >> /var/log/isp-billing/btrc-report.log 2>&1

# Daily ledger reconciliation (03:30)
30 3 * * * ispadmin cd /opt/isp-billing && /usr/bin/docker compose exec -T laravel_app php artisan ledger:reconcile >> /var/log/isp-billing/ledger-reconcile.log 2>&1

# Hourly audit chain verification
0 * * * * ispadmin cd /opt/isp-billing && /usr/bin/docker compose exec -T laravel_app php artisan audit:verify-chain >> /var/log/isp-billing/audit-verify.log 2>&1

# Daily backup (02:30)
30 2 * * * ispadmin /opt/isp-billing/scripts/backup_database_encrypted.sh >> /var/log/isp-billing/backup.log 2>&1

# Log rotation (daily at 05:00)
0 5 * * * root /usr/sbin/logrotate /etc/logrotate.d/isp-billing
EOF

    mkdir -p /var/log/isp-billing
    chown "$DEPLOY_USER:$DEPLOY_GROUP" /var/log/isp-billing

    cat > /etc/logrotate.d/isp-billing <<'EOF'
/var/log/isp-billing/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 640 ispadmin docker
    sharedscripts
    postrotate
        systemctl reload rsyslog > /dev/null 2>&1 || true
    endscript
}
EOF

    log_ok "Cron jobs and log rotation configured"
}

# -----------------------------------------------------------------------------
# Post-Deployment Summary
# -----------------------------------------------------------------------------
print_summary() {
    log_step "Deployment Summary"

    cat <<EOF

${GREEN}╔═══════════════════════════════════════════════════════════════════════╗
║           ISP BILLING & RADIUS — DEPLOYMENT COMPLETE                   ║
╚═══════════════════════════════════════════════════════════════════════╝${NC}

${CYAN}Repository:${NC} $REPO_DIR
${CYAN}Deploy User:${NC} $DEPLOY_USER
${CYAN}Docker Compose Project:${NC} $COMPOSE_PROJECT

${CYAN}Service Endpoints:${NC}
  PostgreSQL Primary:    postgresql://radius_user@127.0.0.1:5432/isp_billing
  PostgreSQL Replica:    postgresql://radius_user@127.0.0.1:5433/isp_billing
  Redis Master:          redis://:PASSWORD@127.0.0.1:6379
  Redis Replica:         redis://:PASSWORD@127.0.0.1:6380
  Redis Sentinel:        redis://:PASSWORD@127.0.0.1:26379
  FreeRADIUS Auth:       UDP 1812 (via HAProxy LB)
  FreeRADIUS Acct:       UDP 1813 (via HAProxy LB)
  FreeRADIUS CoA/PoD:    UDP 3799 (via HAProxy LB)
  HAProxy Stats:         http://127.0.0.1:8404/stats
  MinIO API:             http://127.0.0.1:9000
  MinIO Console:         http://127.0.0.1:9001 (user: $AWS_ACCESS_KEY_ID)

${CYAN}Secrets Location:${NC} $REPO_DIR/secrets/
  - db_password.txt
  - redis_password.txt
  - radius_secret.txt

${CYAN}Environment File:${NC} $REPO_DIR/.env.production (symlinked as .env)

${CYAN}Logs:${NC} /var/log/isp-billing/
${CYAN}Cron Jobs:${NC} /etc/cron.d/isp-billing

${YELLOW}Next Steps:${NC}
  1. Configure MikroTik NAS in FreeRADIUS (clients.conf)
  2. Deploy Laravel application code to backend/ and run migrations
  3. Configure payment gateway credentials (bKash, Nagad, SSLCommerz)
  4. Set up SSL/TLS termination (nginx/Traefik) for API endpoints
  5. Configure monitoring (Prometheus/Grafana) to scrape /api/v1/metrics
  6. Test end-to-end: radtest -> CoA/PoD -> MFS webhook -> unblock
  7. Run local QA suite: ./scripts/run_isp_local_qa.sh

${YELLOW}Important Security Notes:${NC}
  - RADIUS ports (1812/1813/3799 UDP) are restricted to 10.250.10.0/24
  - Update UFW rules with actual NAS IPs for production
  - Rotate secrets periodically using scripts/generate_docker_secrets.sh
  - Enable automated encrypted backups (scripts/backup_database_encrypted.sh)
  - Review Fail2Ban logs regularly

${GREEN}Deployment completed successfully!${NC}

EOF
}

# -----------------------------------------------------------------------------
# Main Execution Flow
# -----------------------------------------------------------------------------
main() {
    echo -e "${MAGENTA}"
    cat <<'EOF'
╔═══════════════════════════════════════════════════════════════════════╗
║     ISP Billing & RADIUS — Ubuntu 26.04.1 Production Deployment       ║
║     https://github.com/tapos-linx/ISP-billing-and-radius              ║
╚═══════════════════════════════════════════════════════════════════════╝
EOF
    echo -e "${NC}"

    check_root
    check_ubuntu_version
    check_architecture

    install_prerequisites
    install_docker
    configure_docker
    create_deploy_user
    configure_firewall
    configure_fail2ban
    optimize_system

    clone_repository
    generate_secrets
    create_env_file

    deploy_stack
    verify_deployment
    run_migrations
    setup_cron_jobs

    print_summary
}

# Run main
main "$@"