# ISP Carrier Test Suite Directory

## Overview
This repository utilizes a multi-tiered automated testing hierarchy designed for carrier-scale reliability, multi-tenancy verification, and zero regression:

1. **Backend PHPUnit Test Suite (`backend/tests/`)**:
   - 138 Feature & Unit test classes containing 1,048 assertions.
   - Executed inside the `laravel_bss_oss` container:
     ```bash
     docker exec -w /opt/laravel_bss laravel_bss_oss php artisan test
     ```
   - Covers: FreeRADIUS AAA, Citus 128-shard distribution, BEFTN ISO 20022 XML, BTRC 730-day IPDR, RFC 5176 CoA/PoD on UDP 3799, bKash/Nagad MFS idempotency, 95th-percentile burstable billing, and AI support bot workflows.

2. **A2UI Copilot Python Test Suite (`tests/test_antigravity_a2ui.py`)**:
   - Asynchronous Pytest suite testing the AG-UI Antigravity FastAPI daemon and whitelisted NOC component catalog (`SubscriberSessionCard`, `OpticalSignalGauge`, `CoaDisconnectConfirmButton`).
   - Executed via `.venv_a2ui`:
     ```bash
     $env:PYTHONPATH="."
     pytest tests/test_antigravity_a2ui.py -v
     ```

3. **Stage Verification Scripts (`scripts/`)**:
   - End-to-end integration test runners (`Run-IspLocalQa.ps1`, `Run-ClickHouseIpdrVerification.ps1`, `Run-HotspotVoucherVerification.ps1`, `Run-Stage10HaStorage.ps1`, `Run-Stage11CloudStaging.ps1`, `Run-Stage12Cutover.ps1`, `Run-Stage13Evolution.ps1`).
