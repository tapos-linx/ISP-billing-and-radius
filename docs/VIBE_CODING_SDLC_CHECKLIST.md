# 7-Stage Solo Vibe-Coding SDLC Checklist
**Target Architect & Environment:**
- **Local Dev / Workstation:** `DESKTOP-SHIPU-W2` (Windows 11 / WSL2 + Docker Desktop + Codex CLI via FreeLLMAPI `127.0.0.1:31415`)
- **Production Server:** Ubuntu 26.04.1 LTS Server (`45.118.70.238`, SSH port `8292`, Repo path `/opt/isp-billing`)
- **BNG Infrastructure:** MikroTik BNG `103.83.235.209` (Zero-Touch Standby Posture Required)

---

## Stage 1: Local Setup & Context Ingestion

### Environment Verification & Context Loading
- [ ] Verify FreeLLMAPI proxy connectivity on port 31415.
- [ ] Validate Docker Desktop engine status and WSL2 integration on `DESKTOP-SHIPU-W2`.
- [ ] Ingest repository structure, AGENTS.md rules, and docker setup files (`docker-compose.yml`, `infra/docker-compose.yml`).

### Commands (PowerShell - Local)
```powershell
# 1. Test local LLM proxy API endpoint
Invoke-RestMethod -Uri "http://127.0.0.1:31415/v1/models" -Method Get

# 2. Check Docker Desktop engine status
docker info

# 3. Check Git status and working state
git status
```

### Commands (Bash - Local WSL2)
```bash
# 1. Verify environment tools
git --version && docker --version && docker compose version

# 2. Test LLM proxy connectivity
curl -s http://127.0.0.1:31415/v1/models
```

### Pass/Fail Verification Gates
- [ ] **Gate 1.1:** LLM proxy `http://127.0.0.1:31415` returns valid JSON response (Pass).
- [ ] **Gate 1.2:** Docker Desktop daemon is active and responsive (Pass).
- [ ] **Gate 1.3:** Git directory is clean or contains traceable changes without uncommitted secrets (Pass).

### Rollback Commands
```powershell
# Reset working branch to last clean state if initialization fails
git reset --hard HEAD
```

---

## Stage 2: Prompting & Architecture Invariant Checks

### Pre-Flight Code Analysis & Compliance Check
- [ ] Verify all PHP code changes conform to `declare(strict_types=1);`.
- [ ] Enforce `tenant_id` scoping across all Eloquent models, services, and queries.
- [ ] Verify zero hardcoded secrets exist in codebase (`.env`, config files, code).

### Commands (PowerShell - Local)
```powershell
# 1. Run secret scanning tool
powershell -ExecutionPolicy Bypass -File .\scripts\scrub_hardcoded_secrets.ps1

# 2. Scan for missing strict types in backend PHP files
Select-String -Path "backend\app\*.php" -Pattern "declare\(strict_types=1\);" -NotMatch
```

### Commands (Bash - Local / Docker)
```bash
# 1. Execute repository secret scan with Gitleaks (if installed) or grep check
gitleaks detect --source . -v || grep -rn "POSTGRES_PASSWORD=" backend/ --exclude=.env.example

# 2. Check strict types compliance in backend
grep -L "declare(strict_types=1);" backend/app/**/*.php
```

### Pass/Fail Verification Gates
- [ ] **Gate 2.1:** Zero hardcoded credentials or plain-text secrets found in tracked files (Pass).
- [ ] **Gate 2.2:** 100% of newly authored or modified PHP files include `declare(strict_types=1);` (Pass).
- [ ] **Gate 2.3:** Eloquent TenantScope and RBAC rules are explicitly present on tenant entities (Pass).

### Rollback Commands
```powershell
# Revert uncommitted changes violating code conventions
git checkout -- backend/
```

---

## Stage 3: Code Generation & Ephemeral Container Execution

### One-Command Local Environment Scaffold & Asset Build
- [ ] Auto-generate missing zero-trust text secrets in `.\secrets\` and `.\infra\secrets\`.
- [ ] Auto-generate `backend/.env` with `MIKROTIK_MOCK=true` and database secrets.
- [ ] Build Vue/Inertia Vite frontend assets using host npm or ephemeral `node:20-alpine`.
- [ ] Spin up Docker Compose stack and execute database migrations and seeders.

### Commands (PowerShell - Local)
```powershell
# Execute single-command dev stack deployment
powershell -ExecutionPolicy Bypass -File .\scripts\vibe-deploy.ps1
```

### Commands (Bash - Local / WSL2)
```bash
# Execute bash deployment helper
bash scripts/vibe-deploy.sh
```

### Pass/Fail Verification Gates
- [ ] **Gate 3.1:** All container services (`postgres_primary`, `redis_master`, `freeradius_1`, `laravel_bss_oss`) in `running (healthy)` state (Pass).
- [ ] **Gate 3.2:** Health check endpoint `http://127.0.0.1:8000/api/v1/health` returns HTTP Status `200` with status `"ok"` (Pass).
- [ ] **Gate 3.3:** RADIUS auth query test via `radtest` against `127.0.0.1:1812` succeeds under <10ms (Pass).

### Rollback Commands
```powershell
# Destroy ephemeral container stack and reset storage volumes
docker compose -f infra/docker-compose.yml down -v --remove-orphans
```

---

## Stage 4: Automated Testing & Static Analysis Audit

### Code Quality, Static Analysis, and Unit/Feature Verification
- [ ] Run Laravel Pint formatting check inside container.
- [ ] Run PHPStan level static analysis analysis with 1G memory limit.
- [ ] Run full PHPUnit test suite to ensure zero regressions.

### Commands (PowerShell - Local)
```powershell
# 1. Run Laravel Pint Code Formatter test
docker exec -w /var/www/html laravel_bss_oss ./vendor/bin/pint --test

# 2. Run PHPStan Static Analysis
docker exec -w /var/www/html laravel_bss_oss ./vendor/bin/phpstan analyse --memory-limit=1G

# 3. Run PHPUnit Test Suite
docker exec -w /var/www/html laravel_bss_oss php artisan test
```

### Commands (Bash / Makefile)
```bash
# Executing shortcuts via Makefile
make audit
make test
```

### Pass/Fail Verification Gates
- [ ] **Gate 4.1:** Pint code style analysis returns 0 formatting violations (Pass).
- [ ] **Gate 4.2:** PHPStan static analysis returns 0 errors (Pass).
- [ ] **Gate 4.3:** PHPUnit suite completes with 100% tests passing and 0 failures/errors (Pass).

### Rollback Commands
```powershell
# Restore clean backend files if pint or refactoring introduced bugs
git checkout -- backend/app/
```

---

## Stage 5: Zero-Touch BNG Standby Safety Verification

### BNG Protection & Isolation Audit
- [ ] Confirm `MIKROTIK_MOCK=true` is set in local environment to prevent accidental live BNG command dispatch.
- [ ] Ensure MikroTik BNG at `103.83.235.209` receives zero live API or RFC 3576 CoA traffic during local dev testing.
- [ ] Verify CoA/PoD packets hit local mock receiver or lab instance only (`127.0.0.1:3799`).

### Commands (PowerShell - Local)
```powershell
# 1. Verify local env MIKROTIK_MOCK flag
Get-Content .\backend\.env | Select-String "MIKROTIK_MOCK"

# 2. Test RouterOS API mock safety wrapper
docker exec -w /var/www/html laravel_bss_oss php artisan carrier:pilot-drill --dry-run
```

### Commands (Bash - Local)
```bash
# Verify no outbound traffic to 103.83.235.209 during test runs
grep "103.83.235.209" backend/.env || echo "[SAFE] BNG IP not target of active local config"
```

### Pass/Fail Verification Gates
- [ ] **Gate 5.1:** `MIKROTIK_MOCK=true` actively set in local `.env` (Pass).
- [ ] **Gate 5.2:** Zero network connection attempts or CoA UDP packets logged towards `103.83.235.209` (Pass).
- [ ] **Gate 5.3:** Simulated session disconnect latency completes under <500ms in mock mode (Pass).

### Rollback Commands
```powershell
# Force enable MIKROTIK_MOCK in local environment
(Get-Content .\backend\.env) -replace "^MIKROTIK_MOCK=.*", "MIKROTIK_MOCK=true" | Set-Content .\backend\.env
```

---

## Stage 6: Staging & Production Deployment

### Production Server Deployment to Ubuntu 26.04.1 LTS
- [ ] Connect to production host `45.118.70.238` via SSH on port `8292`.
- [ ] Execute automated production script `/opt/isp-billing/deploy-ubuntu-26.04.sh`.
- [ ] Validate system tuning, UFW firewall rules, PostgreSQL 16 replication, and HAProxy load balancing.
- [ ] Verify live bKash/Nagad MFS Webhook endpoints and NBR VAT compliance triggers.

### Commands (PowerShell / SSH - Workstation to Server)
```powershell
# 1. Copy updated deployment assets or pull repo on target host
ssh -p 8292 ispadmin@45.118.70.238 "cd /opt/isp-billing && git pull origin main"

# 2. Execute target host deployment script via sudo
ssh -p 8292 -t ispadmin@45.118.70.238 "sudo /bin/bash /opt/isp-billing/deploy-ubuntu-26.04.sh"
```

### Commands (Bash - Remote Server Host `45.118.70.238`)
```bash
# 1. Run production deploy runner manually on target server
cd /opt/isp-billing
sudo ./deploy-ubuntu-26.04.sh

# 2. Verify production stack container health
docker compose ps

# 3. Check production health endpoint
curl -sf http://127.0.0.1:8000/api/v1/health
```

### Pass/Fail Verification Gates
- [ ] **Gate 6.1:** Remote SSH connection to port `8292` succeeds and user `ispadmin` has sudo privileges (Pass).
- [ ] **Gate 6.2:** `deploy-ubuntu-26.04.sh` finishes with 0 errors and output displays `[SUCCESS] Production Deployment Completed` (Pass).
- [ ] **Gate 6.3:** UFW firewall rules block public access to PostgreSQL (5432) & Redis (6379) while permitting internal subnet `10.250.10.0/24` (Pass).

### Rollback Commands
```bash
# Remote Rollback on target server: Restore previous git release and restart stack
cd /opt/isp-billing
git reset --hard HEAD~1
docker compose up -d --build
```

---

## Stage 7: Post-Deployment Monitoring & Handover

### Live Telemetry, Audit Verification & Git Finalization
- [ ] Verify real-time Grafana telemetry dashboards at `http://45.118.70.238:3000` (or `http://127.0.0.1:3000`).
- [ ] Audit database hash-chain integrity for double-entry financial ledger (`audit:verify-chain`).
- [ ] Execute `git status` locally and run exact git staging/commit commands without pushing.

### Commands (PowerShell / Bash - Server Monitoring)
```bash
# 1. Verify ledger hash chain integrity in production container
docker exec -it isp_laravel_app php artisan audit:verify-chain

# 2. Verify monthly partition maintenance status
docker exec -it isp_laravel_app php artisan radius:maintain-partitions
```

### Commands (PowerShell - Local Git Finalization)
```powershell
# 1. Inspect current git state
git status

# 2. Stage updated documentation file
git add docs/VIBE_CODING_SDLC_CHECKLIST.md

# 3. Create local git commit (without pushing)
git commit -m "docs: add 7-stage solo vibe-coding SDLC checklist for Windows/WSL2 and Ubuntu 26.04 deployment"
```

### Pass/Fail Verification Gates
- [ ] **Gate 7.1:** Financial ledger hash chain integrity returns `0` tampered entries (Pass).
- [ ] **Gate 7.2:** Prometheus metrics scraping endpoint `/api/v1/metrics` responds with valid SLA metrics (Pass).
- [ ] **Gate 7.3:** Local git commit created successfully without uncommitted dirty state (Pass).

### Rollback Commands
```powershell
# Undo last local commit while keeping changes staged
git reset --soft HEAD~1
```
