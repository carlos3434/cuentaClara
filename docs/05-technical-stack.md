# docs/05-technical-stack.md

# CuentaClara — Technical Stack & Architecture

## 1. Stack (per CLAUDE.md)

| Layer | Choice |
|-------|--------|
| Backend | Laravel (PHP) |
| DB | MySQL (RDS) |
| Async | Laravel Queues + Jobs (SQS or Redis) |
| Frontend | Vue.js (mobile-first SPA or Inertia) |
| Storage | AWS S3 (receipt images), private bucket |
| AI | Vision LLM for receipt validation |
| Infra | AWS (RDS, S3, queue worker, env-based config) |

## 2. Architecture overview

```
                 ┌────────────────────────────────────────────┐
   Participant   │                  Vue.js SPA                  │
   / Organizer ──▶  (mobile-first, public + organizer areas)    │
                 └───────────────┬──────────────────────────────┘
                                 │ HTTPS / JSON
                 ┌───────────────▼──────────────────────────────┐
                 │                Laravel API                     │
                 │  Controllers → Form Requests → Policies        │
                 │  → Services/Actions → Eloquent → MySQL (RDS)   │
                 │  Upload → S3 (presigned) → dispatch Job        │
                 └───────┬───────────────────────┬────────────────┘
                         │                       │
                 ┌───────▼──────┐        ┌───────▼────────┐
                 │   S3 bucket   │        │  Queue (SQS)   │
                 │ (private,     │        │  ValidateReceipt│
                 │  signed URLs) │        │      Job        │
                 └───────────────┘        └───────┬────────┘
                                                   │
                                          ┌────────▼─────────┐
                                          │  Vision AI client │
                                          │  (LLM provider)   │
                                          └───────────────────┘
```

## 3. Laravel application layout

Follow the conventions named in CLAUDE.md: **Form Requests, Policies, Services /
Actions, Jobs**.

```
app/
  Actions/
    Events/CreateEventAction.php
    Events/SplitShares.php
    Receipts/StoreReceiptAction.php
    Receipts/ApplyValidationVerdict.php
  Services/
    Ai/ReceiptVisionService.php        # talks to the vision model
    Ai/ReceiptRuleEngine.php           # applies business rules → verdict
    Storage/ReceiptStorage.php         # S3 put + signed URLs
    Reminders/WhatsappLinkBuilder.php
  Jobs/
    ValidateReceiptJob.php             # async AI validation
  Models/
    User.php Event.php Participant.php Payment.php
    Receipt.php AiValidation.php EventExpense.php
    Reminder.php AuditLog.php
  Http/
    Controllers/Organizer/...          # authenticated
    Controllers/Public/...             # link-based, no auth
    Requests/...                       # validation per endpoint
    Resources/...                      # API resources (DTO shaping)
  Policies/
    EventPolicy.php PaymentPolicy.php
database/
  migrations/...
```

### Request lifecycle (receipt upload)
1. `Public\ReceiptController@store` ← validated by `StoreReceiptRequest`.
2. `StoreReceiptAction` puts the image in S3 (private) and creates `receipts` +
   `payments(status=submitted)` rows.
3. Dispatches `ValidateReceiptJob` to the queue; returns 202 + payment id.
4. Worker runs `ReceiptVisionService` → `ReceiptRuleEngine` → `ApplyValidationVerdict`.
5. Frontend polls `GET /payments/{id}` (or websockets later) for the verdict.

## 4. Auth model

- **Organizer:** Laravel auth. Recommended **email magic link** (Sanctum + signed
  login link) to reduce friction; password optional. SPA uses Sanctum tokens/cookies.
- **Participant:** no account. A **signed session token** (stored in a cookie and
  bound to `participant_id`) authorizes their uploads on the public link.
- **Public event page:** unauthenticated read of non-sensitive event data via slug.

## 5. Storage & media

- Private S3 bucket; objects keyed `events/{event_id}/receipts/{uuid}.jpg`.
- Access only via short-lived presigned URLs (e.g. 5 min) generated server-side.
- Client compresses/resizes images before upload (target ≤ 1600px, JPEG).
- Store a perceptual/byte hash for duplicate detection (BR-V5).
- Lifecycle policy: optionally transition old receipts to cheaper storage; never
  make objects public.

## 6. Async & queues

- Driver: SQS (prod) / Redis or database (local). Dedicated queue worker.
- `ValidateReceiptJob`: retries with backoff on AI/transient errors (e.g. 3 tries),
  `failed()` handler marks payment `needs_review` with reason `ai_unavailable`.
- Idempotent: re-running validation for the same receipt overwrites the AI record,
  never duplicates payments.

## 7. Configuration (env-based)

```
AI_PROVIDER, AI_MODEL, AI_API_KEY, AI_CONFIDENCE_THRESHOLD=0.85
AWS_BUCKET, AWS_REGION, AWS_USE_PATH_STYLE_ENDPOINT
QUEUE_CONNECTION=sqs
RECEIPT_SIGNED_URL_TTL=300
EXPENSE_MISMATCH_THRESHOLD=0.10
APP_TIMEZONE_DISPLAY=America/Lima
```

## 8. Observability & cost

- Log per-validation: latency, token/cost, confidence, verdict.
- Alert on AI error rate and queue backlog.
- Track AI spend per event (cost guardrail; see risks doc).

## 9. Testing strategy

- Feature tests for each endpoint (auth, validation, policies).
- Unit tests for `ReceiptRuleEngine` (the heart of correctness) with fixture
  AI outputs covering every WARN/FAIL/PASS combination.
- Fake the vision service in tests; never call the real model in CI.
- Money math property tests: `collected + outstanding == total` invariant.
