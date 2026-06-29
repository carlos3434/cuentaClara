# docs/11-development-phases.md

# CuentaClara — Development Phases

Phases are ordered to ship the **core loop** first (link → upload → AI → truth),
then harden. Each phase ends with a demoable, testable increment.

> **Lean MVP note:** the **leaner phase plan is in
> `13-mvp-critique-and-simplification.md` §5** (5 tables, password auth, single-rule
> verdict, one-screen upload). The phases below are the fuller target; treat doc 13
> as authoritative for v1 sequencing.

## Phase 0 — Foundations (setup)
- Laravel project, Vue frontend scaffold, CI, env config.
- AWS: S3 bucket (private), RDS MySQL, queue (SQS) + worker.
- Auth baseline (organizer magic link), Sanctum.
- Migrations: `users`, `events`.
- **Exit:** organizer can sign in; empty app deploys to AWS.

## Phase 1 — Event creation & public link
- Create-event wizard (API + Vue), `SplitShares` action (equal split).
- `events`, `participants` migrations; publish endpoint + slug.
- Public event landing page (sanitized read).
- **Exit:** organizer creates an event, shares a working public link; participant
  sees event summary + their share. No uploads yet.

## Phase 2 — Receipt upload & storage
- Participant identify (session token), upload endpoint, S3 storage.
- `receipts`, `payments` migrations; client-side image compression.
- "Processing" state; payment created as `submitted`.
- **Exit:** participant uploads a receipt; organizer sees it in the dashboard
  (status submitted), image viewable via signed URL. AI not wired yet.

## Phase 3 — AI validation (the core differentiator)
- `ValidateReceiptJob`, `ReceiptVisionService`, `ReceiptRuleEngine`.
- `ai_validations` migration; verdict + reason codes; duplicate detection.
- Participant result screen; organizer review queue + override endpoints.
- Money math service + dashboard totals (collected/pending) with invariant.
- **Exit:** clean receipts auto-validate; ambiguous ones land in review;
  organizer can approve/reject/partial/cash. **This is the MVP.**

## Phase 4 — Reminders & event expense receipt
- `reminders` + `wa.me` message builder; reminder logging.
- Event expense upload + mismatch flagging (`event_expenses`).
- Dashboard polish (progress bar, per-status filters).
- **Exit:** organizer can chase pending people and document the real cost.

## Phase 5 — Hardening & observability
- `audit_logs` everywhere; rate limiting; abuse protection.
- AI metrics (auto-validation rate, false accept/reject, cost/latency).
- Error/empty/offline states across all screens; accessibility pass.
- Threshold tuning from real data.
- **Exit:** production-ready, monitored, tunable.

## Phase 6 — Post-MVP (backlog)
- Realtime updates (WebSockets) instead of polling.
- WhatsApp Business API automated reminders.
- Multi-currency, multi-organizer, refunds/credits handling.
- CSV/PDF summary export.
- Templates / recurring events.

## Dependency graph

```
P0 → P1 → P2 → P3 (MVP) → P4 → P5 → P6
                    │
                    └── rule engine has the most unit tests; gate on it
```

## Suggested team slicing (if parallelized)
- **Backend/AI:** P0 infra → rule engine + job (P3) is critical path.
- **Frontend:** participant flow (P1–P3) then organizer review/dashboard.
- **Design/QA:** UX states (`04`) and rule-engine fixtures in lockstep with P3.

## Per-phase quality gate
Every phase: feature tests green, money invariant test green, no public S3 object,
all new endpoints behind Form Request + Policy.
