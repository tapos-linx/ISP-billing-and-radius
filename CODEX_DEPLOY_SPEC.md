# Vibe-Coder One-Command Deployment Suite Specification

Implement and verify the following in `C:\Users\DESKTOP-SHIPU-W2\projects\ISP-billing-and-radius`:

1. **Cross-Platform One-Command Deployment Scripts:**
   - `scripts/vibe-deploy.ps1` (Windows PowerShell) and `scripts/vibe-deploy.sh` (Ubuntu/WSL2 bash).
   - Check prerequisites (`docker`, `docker compose`, `git`).
   - Auto-generate missing `./secrets/*.txt` and `./infra/secrets/*.txt` files (`db_password.txt`, `redis_password.txt`, `radius_secret.txt`, `rabbitmq_password.txt`, `bkash_secret.txt`, `nagad_secret.txt`) with random 32-char alphanumeric strings if missing (never overwrite existing files).
   - Create `backend/.env` from `backend/.env.example` if missing, populate matching secrets and `APP_KEY`, and set `MIKROTIK_MOCK=true` unless `--production` is passed.
   - Build frontend Vite assets (`backend/public/build`) using host `npm` if installed or an ephemeral `node:20-alpine` Docker container (`npm ci && npm run build`).
   - Start Docker Compose (`docker compose up -d --build`), wait for database and Redis healthchecks, run `php artisan migrate --force`, `php artisan db:seed --force`, `php artisan radius:maintain-partitions`, and `php artisan optimize:clear`.
   - Check `/api/v1/health` for HTTP 200 and print clickable URLs for `/self-care`, `/portal/dashboard`, `/admin`, `/noc/dashboard`, `/noc/carrier`, `/hotspot/login`, `/gemini/audit`, Grafana (`:3000`), Prometheus (`:9090`), and RabbitMQ (`:15672`).

2. **Developer Shortcuts & Quickstart Guide:**
   - Create a root `Makefile` and root `package.json` with scripts (`up`, `down`, `logs`, `build-ui`, `test`, `audit`, `seed`, `doctor`).
   - Create `DEPLOY_QUICKSTART.md` with a 3-step Windows/Ubuntu guide, seeded demo login credentials from `DatabaseSeeder.php`, and pre-cutover steps (`MIKROTIK_MOCK=false` and live bKash/Nagad keys).

3. **Invariants & Verification:**
   - Any new or modified PHP files must use `declare(strict_types=1);` and preserve `tenant_id` scoping.
   - Ensure `secrets/*.txt`, `infra/secrets/*.txt`, and `backend/storage/framework/*` are in `.gitignore`.
   - Run `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --memory-limit=1G`, and `php artisan test` inside the running Laravel container (`laravel_bss_oss` or `laravel_app`) and confirm 0 errors.
