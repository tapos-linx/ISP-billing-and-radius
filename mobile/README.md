# ISP Subscriber Self-Care Mobile Application

## Overview
The `mobile` directory contains the **React Native Expo** cross-platform mobile client for subscriber self-care, optical diagnostics, and instant bill payment.

---

## Key Features & Components (`components/`)
1. **`FupDataRing.tsx`**:
   - Visualizes fair usage policy (FUP) monthly data consumption, consumed GB, and remaining quota with animated circular progress rings.
2. **`OpticalPowerGauge.tsx`**:
   - Subscriber-facing optical received signal indicator (Rx dBm) with health feedback to reduce Tier-1 helpdesk support calls.
3. **`QuickPayCard.tsx`**:
   - One-tap MFS bill payment integration supporting bKash, Nagad, and Rocket direct checkouts.
4. **`SpeedBoostCard.tsx`**:
   - On-demand temporary speed turbo boost triggering sub-second RFC 5176 CoA rate-limit modification.

---

## Directory Structure
```text
mobile/
├── components/          # Reusable UI widgets (FupDataRing, OpticalPowerGauge, etc.)
├── screens/             # Top-level screen views (DashboardScreen.tsx)
├── services/            # API bridge client (selfcare-api.ts, api.ts)
├── types/               # TypeScript interfaces and contracts
├── App.tsx              # Main application entrypoint
├── app.json             # Expo project configuration
├── package.json         # Dependencies and build scripts
└── tsconfig.json        # Strict TypeScript compiler options
```

---

## Local Development & Typecheck
```bash
# In mobile directory:
npm install
npm run typecheck
npm start
```
