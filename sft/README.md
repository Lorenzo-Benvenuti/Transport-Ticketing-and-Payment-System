## Local setup

**Recommended:** use Docker Compose from the repo root.

# SFT — Transport Ticketing System

PHP/MySQL mini‑app that simulates a transport ticketing system.

## Features
- Routes / schedules consultation
- Ticket purchase flow (mock payment)
- Admin backoffice (management/reporting)
- Operations backoffice (rolling stock / train composition / planning)

## Configuration
Copy `./.env.example` to `./.env`.

Required variables:
- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
Optional base URLs (recommended for the PaySteam demo):
- `PUBLIC_BASE_URL` (browser/host): e.g. `http://localhost:8080/sft`
- `INTERNAL_BASE_URL` (server-to-server inside Docker): e.g. `http://localhost/sft`

Optional PaySteam demo integration:
- `PAYSTEAM_BASE_URL` (server-to-server): e.g. `http://localhost/paysteam`
- `PAYSTEAM_API_TOKEN`, `PAYSTEAM_WEBHOOK_SECRET`

## Demo credentials
- Admin: `admin@sft.it` / `admin123`
- Operations: `responsabile@sft.it` / `responsabile123`
- Passenger: `utente@example.com` / `utente123`
