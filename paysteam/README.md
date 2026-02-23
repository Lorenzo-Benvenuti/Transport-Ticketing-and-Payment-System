## Local setup

**Recommended:** use Docker Compose from the repo root.

# PaySteam — Payment / Wallet System

PHP/MySQL mini‑app that simulates a payment provider / wallet.

## Features
- Consumer dashboard and transaction history
- Merchant area
- Mock API bearer authentication (M2M)
- HMAC‑signed callback simulation (webhook)

## Configuration
Copy `./.env.example` to `./.env`.

Required variables:
- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
- `APP_BASE_URL` (browser base URL, e.g. `http://localhost:8080/paysteam`)

Security-related variables:
- `API_BEARER_TOKEN`, `WEBHOOK_SECRET`

## Demo credentials
- Consumer: `utente@example.com` / `utente123`
- Merchant: `esercente@paysteam.it` / `merchant123`
