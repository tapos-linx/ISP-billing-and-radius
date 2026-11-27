# AGENTS.md — ISP-billing-and-radius

- **Local Workstation (`DESKTOP-SHIPU-W2`):** Windows PowerShell + WSL2 Ubuntu + Docker Desktop + Codex CLI (`FreeLLMAPI` at `http://127.0.0.1:31415`)
- **Production Server (`billing-t-d`):** Ubuntu 26.04.1 LTS (`45.118.70.238`, SSH port `8292`, `/opt/isp-billing`)
- **Live MikroTik BNG (`R-204_X86`):** `103.83.235.209`

## Hard Guardrails
1. **Zero-Touch Live BNG:** `103.83.235.209` is strictly passive standby (`nas` table record + passive UFW UDP 1812/1813/3799 rules). Never run RouterOS API calls, cron jobs, or `radclient` CoA packets against `103.83.235.209` without explicit written approval.
2. **Secret Isolation:** Never commit `.env`, `.venv_copilot/`, or anything inside `infra/secrets/`. Config files in Git (`clients.conf`, `sql.conf`) must use placeholders or secret mounts, never literal passwords or RADIUS secrets.
3. **Windows Git & Line Endings:** Always pass `-c safe.directory="C:/Users/DESKTOP-SHIPU-W2/projects/ISP-billing-and-radius"` to `git` commands on Windows, and save `.sh`, `.sql`, `.conf`, `Makefile`, and `.md` files with Unix `LF` line endings.
4. **Infrastructure Rules:** Alpine FreeRADIUS uses `/etc/raddb` (not `/etc/freeradius/3.0`). Use `POSTGRES_PASSWORD_FILE: /run/secrets/db_password` exclusively in `infra/docker-compose.yml` without conflicting `POSTGRES_PASSWORD` env vars.