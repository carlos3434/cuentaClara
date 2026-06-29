# docs/03-mvp-scope.md

# CuentaClara — MVP Scope (Lean v1)

> **Status: BUILT ✅** — every in-scope item below is implemented and tested
> (66 passing tests). See `docs/14` → "What's implemented" for the capability
> map and how to run it. The checkboxes below are now delivered, not planned.

> This scope was **simplified** after a self-critique. See
> `13-mvp-critique-and-simplification.md` for the reasoning and the full list of
> deferrals. Docs `02`, `04`, `06`, `08`–`11` describe the broader target design;
> **this file + doc 13 are authoritative for what v1 actually ships.**

Goal: the **smallest useful version** that proves one loop —
*share link → upload receipt → AI checks the amount → organizer sees the truth.*

## In scope (v1)

### Organizer (password auth — Laravel Breeze)
- [x] Sign up / log in.
- [x] Create event: name, date, total, **expected headcount**, recipient name,
      recipient Yape/Plin number, **single payment deadline**. Currency = PEN.
- [x] **Equal split only** (`share = total / headcount`, auto).
- [x] Public link + QR + "Compartir por WhatsApp".
- [x] Dashboard: total / collected / pending + participant list with status &
      receipt thumbnail.
- [x] Review a receipt: image + AI reading → **Aprobar / Rechazar**.
- [x] **Marcar pago en efectivo** (manual, no receipt).
- [x] Upload event expense receipt — **stored only, no AI in v1**.
- [x] Reminders via a frontend-built `wa.me` link.
- [x] Close event.

### Participant (no login, **no phone**)
- [x] Open public link.
- [x] **One screen**: enter first name + take/choose photo → Enviar.
- [x] Instant confirmation ("¡Listo! El organizador confirmará").
- [x] Status badge on return: **Pendiente / Confirmado / Revisar**.

### System / AI
- [x] Private S3 storage + signed URLs.
- [x] **Background** AI extraction (fire-and-forget; participant not blocked).
- [x] Extract amount/date/method/recipient(text) + confidence.
- [x] **Single-rule verdict**: `amount ≈ share AND confidence ≥ threshold`
      → `validated`, else `needs_review`. AI failure → `needs_review`.
- [x] AI fields + raw response stored on the `receipts` row.

## Out of scope (deferred to v2+)

- ❌ Custom split / per-participant amounts.
- ❌ Partial payments, overpayment, credit accumulation.
- ❌ Duplicate-receipt detection.
- ❌ Participant phone numbers.
- ❌ Predefined participant lists (self-registration only).
- ❌ AI on event-expense receipts (mismatch flagging).
- ❌ Synchronous participant verdict / live result screen.
- ❌ Multi-dimension pass/warn/fail rule engine.
- ❌ Separate `payments` / `ai_validations` / `reminders` / `audit_logs` tables.
- ❌ Magic-link auth, realtime/WebSockets, multi-currency, multi-organizer, refunds.
- ❌ Real money movement / payment gateway. (Permanent — we track, we don't pay.)

## Why this cut line

The MVP must prove the loop, nothing else. Recipient/date matching from real
Yape/Plin screenshots is unreliable, so gating on it would generate false
mismatches and erode trust — we extract and **display** those fields but only the
**amount** drives the auto-verdict. Phone numbers, predefined lists, and a
blocking verdict screen are pure friction on the participant's 30-second path.

## Definition of Done (v1)

1. Organizer creates an event and gets a working public link.
2. A participant on a phone completes name + upload on **one screen** and gets an
   instant "¡Listo!" — no waiting on the AI.
3. Receipts whose amount matches the share auto-mark `validated`; everything else
   lands in the organizer's list as `needs_review`.
4. Dashboard reconciles: `collected + pending = total`.
5. Every receipt is overridable by the organizer; the decision is recorded.
6. No receipt image is reachable without a signed URL.

## Phasing

Maps to lean Phases 0–3 in `13` §5 (and `11-development-phases.md`). Expense
receipt, reminders, and hardening are Phase 4+.
