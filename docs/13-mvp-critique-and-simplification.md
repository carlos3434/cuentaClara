# docs/13-mvp-critique-and-simplification.md

# CuentaClara — MVP Critique & Simplification (Lean MVP v1)

This document challenges the original proposal (docs `02`–`12`) and defines a
**leaner MVP v1**. Where v1 and the deeper docs disagree, **v1 wins for the first
release**; the deeper docs remain the north-star/target design.

> **Status: the Lean MVP v1 defined here is BUILT and tested** (Laravel + Inertia
> + Vue, 66 passing tests). The "Deferred to v2+" list in §2 is still accurate —
> those items remain unbuilt. See `docs/14` → "What's implemented" for the
> delivered capability map.

---

## 1. Self-critique

### What is too complex for the MVP
- **4-dimension rule engine** (amount/date/recipient/method each pass/warn/fail +
  a verdict matrix). Recipient and date extraction from Yape/Plin screenshots is
  unreliable; gating on it manufactures false mismatches.
- **Partial payment + overpayment + credit accumulation.** A full money-math
  subsystem for a minority case.
- **Custom split per participant.** Equal split covers the overwhelming majority of
  "BBQ / football field / gift" events.
- **Duplicate detection** (byte hash + perceptual hash + transaction id).
- **9-table schema** with a separate `payments` table, polymorphic `ai_validations`,
  and a dedicated `audit_logs` table.
- **Magic-link auth** — more moving parts (email deliverability, token exchange)
  than a plain password login.
- **AI on the event-expense receipt + mismatch flagging.**
- **Reminders table + preview endpoint** for something the frontend can build as a
  `wa.me` link with zero backend.

### What should be removed from v1
- Participant **phone number** (friction + PII; organizer reminds via their own
  WhatsApp contacts anyway).
- **Predefined participant list** (organizer typing everyone upfront).
- Custom split, partial/overpayment math, duplicate detection.
- `payments`, `ai_validations` (polymorphic), `reminders`, `audit_logs` tables.
- AI on expense receipts.
- Synchronous participant "validando…" wait.

### What can be simplified
- **Verdict logic → one comparison:** `amount ≈ share AND confidence ≥ threshold`
  → `validated`, otherwise `needs_review`. Date/recipient/method are extracted and
  **displayed to the organizer**, never gating.
- **Schema → 5 tables:** `users`, `events`, `participants`, `receipts` (the receipt
  row *is* the payment record and carries the AI fields), `event_expenses`.
- **Payment window → single deadline date** instead of a from–to range.
- **Auth → Laravel Breeze password login.**
- **Overrides logging → a few columns** on the receipt (`decided_by`, `decided_at`,
  `decision_note`) instead of a separate audit table.

### Which parts create friction for mobile users
- **Asking for a phone number** before they can help — removed.
- **Separate identify screen then upload screen** — merge into one screen
  (name + photo together).
- **Spinner wait on AI** before they're "done" — removed; instant "¡Listo!".
- **Date-range comprehension** ("válido 24–30 jun") — replaced with a single,
  human "Paga antes del 30 jun".

### Which flows could fail in real life
| Flow | Failure | v1 response |
|------|---------|-------------|
| AI recipient-name match | Nicknames/handles ≠ legal name → false mismatch | Don't gate on it; display only |
| AI date parse | Many receipt date formats | Display only; never auto-reject |
| Magic-link login | Email in spam → organizer locked out | Use password auth |
| Self-registered names | Typos/garbage/dupes | Organizer can rename/merge/delete; cash + manual override always available |
| Blocking on AI | Queue backlog → participant stuck on spinner | Fire-and-forget; status shown on return |
| AI provider down | — | Receipt saved as `needs_review`; **never** auto-reject |
| Slow mobile network | Large photo upload fails | Client-side compression + retry |

---

## 2. Lean MVP v1 — definitive scope

### Organizer (authenticated, password)
1. Sign up / log in (Laravel Breeze).
2. Create event: **name, date, total, expected headcount, recipient name,
   recipient Yape/Plin number, payment deadline (single date)**. Equal split is
   computed automatically (`share = total / headcount`).
3. Get public link + "Compartir por WhatsApp".
4. Dashboard: **collected / total** progress, participant list with status +
   receipt thumbnail.
5. Tap a receipt → see image + AI reading (amount/date/method/recipient as text +
   confidence) → **Aprobar / Rechazar**.
6. **Marcar pago en efectivo** for a participant (no receipt).
7. Upload the **event expense receipt** (stored only; no AI in v1).
8. Remind a pending participant via a frontend-built `wa.me` link.

### Participant (no login, no phone)
1. Open link → see **"Tu parte: S/ 40 · Paga a Caro (Yape 999…) · antes del 30 jun"**.
2. **One screen:** enter first name + take/choose photo → "Enviar".
3. Immediate confirmation: **"¡Listo! Gracias. El organizador confirmará tu pago."**
4. Returning to the link shows a simple badge: **Pendiente / Confirmado / Revisar**.

### AI (background only)
- On upload, enqueue a job that extracts `amount, date, method, recipient(text),
  confidence`.
- Verdict: `amount ≈ share (within rounding) AND confidence ≥ THRESHOLD`
  → `validated`; else `needs_review`.
- AI failure/timeout → `needs_review` (never auto-reject).
- Extraction + raw response stored on the `receipts` row.

### Deferred to v2+ (explicitly out of v1)
Custom split · partial/overpayment math · duplicate detection · participant phones ·
predefined participant lists · AI on expense receipts · synchronous participant
verdict · audit-log table · magic-link auth · per-dimension rule engine ·
realtime/WebSockets · multi-currency · multi-organizer · refunds.

---

## 3. Lean schema (5 tables)

```
users ─1:N─ events ─1:N─ participants ─1:N─ receipts
                    └─1:N─ event_expenses
```

- **users**: id, name, email, password.
- **events**: id, user_id, slug, name, event_date, total_cents, headcount,
  share_cents (cached), recipient_name, recipient_handle, accepted_methods(json),
  pay_deadline (date), status(`draft|active|closed`), timestamps.
- **participants**: id, event_id, name, session_token, status(`pending|paid`),
  timestamps. *(status derived from having a `validated`/`cash` receipt; cached.)*
- **receipts** (the payment record + AI fields):
  id, event_id, participant_id, s3_key(nullable for cash), method,
  status(`submitted|validated|needs_review|rejected|cash`),
  extracted_amount_cents, extracted_date, extracted_method, extracted_recipient,
  confidence, ai_explanation, ai_raw(json),
  decided_by(`ai|organizer`), decided_at, decision_note, created_at.
- **event_expenses**: id, event_id, s3_key, note, created_at.

Money math: `collected = Σ receipts where status in (validated, cash)`;
`pending = max(0, total − collected)`.

---

## 4. Lean API surface

**Organizer** (`/api/organizer`, Breeze auth):
`POST/GET events`, `GET events/{e}`, `POST events/{e}/publish`,
`POST events/{e}/close`, `GET events/{e}/receipts`, `GET receipts/{r}`,
`POST receipts/{r}/approve`, `POST receipts/{r}/reject`,
`POST events/{e}/cash`, `POST events/{e}/expenses`.

**Public** (`/api/public`, slug + participant token):
`GET events/{slug}`, `POST events/{slug}/identify` (`{name}` → token),
`GET events/{slug}/me`, `POST events/{slug}/receipts` (multipart).

Removed vs original: reminders endpoints, partial endpoint, shares/rebalance,
participants CRUD (self-register only), payment polling for live verdict.

---

## 5. Lean phases

- **P0** Setup + auth (Breeze) + S3 + queue. Tables: users, events.
- **P1** Create event (equal split) + public link + sanitized landing.
- **P2** One-screen identify+upload → S3 → `receipts` row (`submitted`); organizer
  sees thumbnails. *Demoable end-to-end without AI.*
- **P3 (MVP)** Background AI extraction + amount/confidence verdict + organizer
  review (approve/reject) + cash + dashboard totals.
- **P4** Event expense upload (store only) + `wa.me` reminders + polish/states.
- **P5** Hardening, metrics, threshold tuning.

---

## 6. Decisions to confirm (deviations from original vision)
1. **Participant verdict deferred to "status on return"** rather than a live result
   screen. This softens vision step 9 ("Participant sees validation result") — the
   payer sees *Pendiente/Confirmado/Revisar*, not a detailed AI breakdown. **OK?**
2. **No participant phone numbers in v1.** Reminders rely on the organizer's own
   WhatsApp contacts. **OK?**
3. **Self-registration only** (no organizer-entered participant list). **OK?**
4. **Password auth** (not magic link) for organizers in v1. **OK?**
5. **Single payment deadline** instead of a date range. **OK?**
