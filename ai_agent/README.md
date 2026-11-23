# ISP NOC Copilot & Antigravity A2UI Service

## Overview
The `ai_agent` module hosts the **ISP Network Operations Center (NOC) Copilot Server** using the **AG-UI Antigravity** protocol and **A2UI Component Catalog**. It provides zero-latency agentic telemetry retrieval, real-time subscriber diagnostics, and hardened RFC 5176 Packet of Disconnect (PoD) execution.

---

## Architecture & Whitelisted Catalog
The service executes on port `8010` (configured in `.env` as `A2UI_PORT=8010`) to prevent port collision with the main Laravel BSS/OSS service on port `8000`.

### Whitelisted NOC Components (`noc_catalog.py`)
1. **`SubscriberSessionCard`**:
   - Visualizes live subscriber PPPoE/IPoE session state, framed IP, MAC, NAS identity, rate-limiting tier (e.g. `20M/20M`), and framed pool.
2. **`OpticalSignalGauge`**:
   - Real-time OLT/ONU optical received signal strength indicator (RSSI in dBm) with health status (`optimal`, `warning`, `critical`).
3. **`CoaDisconnectConfirmButton`**:
   - RFC 5176 Packet of Disconnect button bound to `POST /api/v1/coa/disconnect` with mandatory operator confirmation modal.

---

## Endpoints
- `GET /health` — Service health check and registered catalog ID.
- `GET /capabilities` — Agent capabilities and registered A2UI component schemas.
- `GET /api/v1/a2ui/catalog` — JSON Schema definitions for NOC A2UI components.
- `POST /api/v1/coa/disconnect` — Local proxy executing RFC 5176 Disconnect-Request via FreeRADIUS client.
- `POST /` — AG-UI Server-Sent Events (SSE) streaming endpoint for AI chat and interactive tool calls.

---

## Setup & Running
1. Activate virtual environment:
   ```bash
   # Windows PowerShell
   .\.venv_a2ui\Scripts\Activate.ps1
   ```
2. Install dependencies:
   ```bash
   pip install -r ai_agent/requirements.txt
   ```
3. Start the A2UI Copilot daemon:
   ```bash
   python ai_agent/antigravity_a2ui_server.py
   ```
4. Run test suite:
   ```bash
   $env:PYTHONPATH="."
   pytest tests/test_antigravity_a2ui.py -v
   ```
