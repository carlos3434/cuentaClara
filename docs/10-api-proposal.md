# docs/10-api-proposal.md

# CuentaClara — API Proposal

REST/JSON. Two surfaces:
- **Organizer API** — authenticated (Sanctum), `/api/organizer/*`.
- **Public API** — link + participant session token, `/api/public/*`.

All money in responses is returned as integer `*_cents` plus a formatted string.
Validation via Form Requests; authorization via Policies.

> **Lean MVP note:** v1 trims this surface — **no** reminders endpoints (frontend
> builds the `wa.me` link), **no** partial/shares-rebalance/participants-CRUD
> endpoints (self-registration only), and **no** live verdict polling (status comes
> from `GET public/events/{slug}/me`). Organizer review acts on **receipts**, not a
> separate `payments` resource. Auth is **Breeze password**, not magic link. The v1
> endpoint list is in `13-mvp-critique-and-simplification.md` §4.

## 1. Conventions

- Auth (organizer): `Authorization: Bearer <token>` (Sanctum).
- Participant: `X-Participant-Token` header (or signed cookie) bound to event slug.
- Errors: `{ "message": "...", "errors": { "field": ["..."] } }`, proper HTTP codes.
- Timestamps ISO-8601 UTC; client renders in `America/Lima`.
- Idempotency: receipt upload accepts `Idempotency-Key` header.

## 2. Auth (organizer)

| Method | Path | Body | Notes |
|--------|------|------|-------|
| POST | `/api/auth/magic-link` | `{email}` | sends signed login link |
| GET | `/api/auth/callback` | `?token=` | exchanges for session/token |
| POST | `/api/auth/logout` | — | |
| GET | `/api/organizer/me` | — | current user |

(If password mode: `POST /api/auth/register`, `POST /api/auth/login`.)

## 3. Organizer — events

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/organizer/events` | list events with collected/total summary |
| POST | `/api/organizer/events` | create event (draft) |
| GET | `/api/organizer/events/{event}` | full detail + participants + status |
| PATCH | `/api/organizer/events/{event}` | edit fields (rules in BR-E6) |
| POST | `/api/organizer/events/{event}/publish` | draft → active, returns public link |
| POST | `/api/organizer/events/{event}/close` | active → closed |
| POST | `/api/organizer/events/{event}/archive` | → archived |

### Create event — request
```json
{
  "name": "BBQ Cumpleaños Caro",
  "event_date": "2026-06-28",
  "total_amount_cents": 48000,
  "participant_count": 12,
  "split_mode": "equal",
  "recipient_name": "Caro Rojas",
  "recipient_handle": "999888777",
  "accepted_methods": ["yape", "plin"],
  "valid_from": "2026-06-24",
  "valid_to": "2026-06-30",
  "participants": [{ "name": "José", "phone": "9..." }]   // optional
}
```
### Create event — response (201)
```json
{
  "id": 1, "slug": "k7Qp2x", "status": "draft",
  "public_url": "https://cuentaclara.app/e/k7Qp2x",
  "share_preview_cents": 4000,
  "participants": [ ... ]
}
```

## 4. Organizer — participants & shares

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/organizer/events/{event}/participants` | add participant |
| PATCH | `/api/organizer/events/{event}/participants/{p}` | edit name/phone/share |
| DELETE | `/api/organizer/events/{event}/participants/{p}` | remove (if no validated payment) |
| POST | `/api/organizer/events/{event}/shares/rebalance` | recompute equal split |

Server enforces `Σ shares == total` (BR-E5/BR-S2) and returns 422 otherwise.

## 5. Organizer — payments / review

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/organizer/events/{event}/review` | queue of `needs_review` payments |
| GET | `/api/organizer/payments/{payment}` | detail + signed receipt URL + AI output |
| POST | `/api/organizer/payments/{payment}/approve` | override → validated |
| POST | `/api/organizer/payments/{payment}/reject` | override → rejected (+reason) |
| POST | `/api/organizer/payments/{payment}/partial` | mark partial (credits amount) |
| POST | `/api/organizer/events/{event}/cash-payment` | record `paid_cash` for a participant |

Every override writes an `audit_log` (BR-A1) and recomputes totals.

## 6. Organizer — event expense receipt

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/organizer/events/{event}/expenses` | upload expense receipt → AI |
| GET | `/api/organizer/events/{event}/expenses` | list + extracted totals + mismatch flag |

## 7. Organizer — reminders

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/organizer/events/{event}/reminders/preview?participant_id=` | returns generated WhatsApp message + `wa.me` URL |
| POST | `/api/organizer/events/{event}/reminders` | log a sent reminder |

## 8. Public API (participant, no login)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/public/events/{slug}` | event summary (no other participants' PII) |
| POST | `/api/public/events/{slug}/identify` | `{name, phone}` → participant + session token |
| GET | `/api/public/events/{slug}/me` | current participant status + their payments |
| POST | `/api/public/events/{slug}/receipts` | multipart upload → 202 + payment id |
| GET | `/api/public/payments/{payment}` | poll verdict (scoped to session token) |

### Public event response (sanitized)
```json
{
  "name": "BBQ Cumpleaños Caro",
  "event_date": "2026-06-28",
  "total_amount_cents": 48000,
  "recipient_name": "Caro Rojas",
  "recipient_handle": "999888777",
  "accepted_methods": ["yape","plin"],
  "valid_from": "2026-06-24", "valid_to": "2026-06-30",
  "status": "active",
  "my_share_cents": 4000          // present after identify
}
```

### Upload receipt
- `multipart/form-data`: `image` (required), `note` (optional).
- Validates type/size; stores to S3; creates payment(`submitted`); dispatches
  `ValidateReceiptJob`; returns:
```json
{ "payment_id": 99, "status": "submitted" }
```
- Client polls `GET /api/public/payments/99`:
```json
{
  "payment_id": 99,
  "status": "needs_review",
  "ai_summary": { "amount_cents": 3000, "date": "2026-06-24",
                  "recipient": "Caro", "method": "yape", "confidence": 0.72 },
  "message": "Leímos S/ 30; tu parte es S/ 40. El organizador lo revisará."
}
```
> Public responses expose a **friendly AI summary only** — never raw model JSON.

## 9. Status codes

- 200 ok · 201 created · 202 accepted (async validation queued) ·
  401 unauthenticated · 403 policy denied · 404 unknown slug/resource ·
  409 conflict (e.g. event closed) · 422 validation error · 429 rate limit.

## 10. Rate limiting & abuse

- Public upload endpoint rate-limited per session token + IP.
- Slug is unguessable; identify endpoint throttled to limit enumeration.
- Max image size and count per participant enforced server-side.

## 11. Realtime (post-MVP)

MVP uses polling on `GET payments/{id}`. Later: broadcast verdict via WebSockets/
Echo for instant participant + dashboard updates.
