# docs/14-running-the-app.md

# Running CuentaClara (local dev)

Stack: **Laravel 13 + Inertia + Vue 3 + Tailwind v4 + SQLite** (dev).

## First-time setup

```bash
composer install
npm install
cp .env.example .env          # if .env is missing
php artisan key:generate
touch database/database.sqlite # SQLite dev DB
php artisan migrate
npm run build                  # or: npm run dev (HMR)
```

## Run

```bash
# Terminal 1 — app
php artisan serve

# Terminal 2 — assets (hot reload during development)
npm run dev
```

Open `http://127.0.0.1:8000` → redirects to the organizer **dashboard**
(guests are bounced to `/login`; register a new organizer to start).

## Tests

```bash
php artisan test
```

Tests use an in-memory SQLite DB (`phpunit.xml`) — no setup needed.

## Corporate TLS note

This machine is behind a TLS-intercepting proxy, so Composer/npm reject the
self-signed cert in the chain. A combined CA bundle was generated from the macOS
keychain and Composer was pointed at it globally:

```bash
composer config --global cafile ~/.config/cuentaclara-corp-ca.pem
```

If `composer` or `npm` later fail with `SSL certificate ... self-signed
certificate in certificate chain`, regenerate the bundle:

```bash
cat /opt/homebrew/etc/openssl@3/cert.pem > ~/.config/cuentaclara-corp-ca.pem
security find-certificate -a -p /Library/Keychains/System.keychain >> ~/.config/cuentaclara-corp-ca.pem
security find-certificate -a -p ~/Library/Keychains/login.keychain-db >> ~/.config/cuentaclara-corp-ca.pem
```

For npm, if needed: `npm config set cafile ~/.config/cuentaclara-corp-ca.pem`.

## Environment configuration

```ini
# Receipts storage (private). Use s3 in production.
RECEIPTS_DISK=local
RECEIPTS_MAX_KB=8192

# AI receipt validation
AI_DRIVER=fake                 # 'fake' (dev/test) or 'anthropic' (real Claude vision)
AI_CONFIDENCE_THRESHOLD=0.85
ANTHROPIC_API_KEY=             # required when AI_DRIVER=anthropic
AI_MODEL=claude-opus-4-8

# Rate limits (requests/minute)
RATE_LIMIT_UPLOADS=20          # public POST /e/{slug}/receipts (per IP)
RATE_LIMIT_LOGIN=10            # POST /login (per email+IP)

# Queue: validation runs async. 'sync' runs it inline (handy for local demos);
# in production run a worker: php artisan queue:work
QUEUE_CONNECTION=database
```

## What's implemented (lean MVP — complete)

The full loop works end to end, behind **66 passing tests**:

| Capability | Notes |
|------------|-------|
| Organizer auth | Register / login / logout (password, session); login rate-limited |
| Dashboard | `/events` — organizer's events, newest first |
| Create event | Mobile-first form, equal split, unguessable public slug |
| Public landing + upload | `/e/{slug}` — identify (name only, no login) + voucher upload, one screen; rate-limited; reflects closed events |
| AI validation | Async `ValidateReceiptJob`; `FakeReceiptVision` (default) or `AnthropicReceiptVision`; deterministic `ReceiptRuleEngine` (amount + confidence); failure → `needs_review`, never auto-reject; structured logging |
| Review hub | `/events/{slug}/review` — needs-review queue (image + AI reading), approve / reject / mark-cash, collected/pending totals |
| Reminders | `wa.me` deep links (group + per pending participant) |
| Expense receipt | Organizer's own cost evidence (store-only) |
| Close / reopen event | Blocks further uploads when closed |

**Deferred to v2** (see `docs/13`): custom split, partial/overpayment math,
duplicate detection, participant phones, predefined participant lists, AI on
expense receipts, real-time updates, multi-currency, multi-organizer.

To watch AI validation happen on a local upload, run with `QUEUE_CONNECTION=sync`
(or start a worker) and `AI_DRIVER=fake` — the fake driver auto-validates.
