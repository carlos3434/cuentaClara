# docs/02-business-flow.md

# CuentaClara — Business Flow & User Flows

> **Lean MVP note:** this describes the full target flow. For v1 we simplify the
> participant path — **no phone, one combined identify+upload screen, no blocking
> AI wait** (status shown on return), **self-registration only**, and a **single
> payment deadline** instead of a range. See `13-mvp-critique-and-simplification.md`.

This document maps the end-to-end flows. Authoritative rules live in
`08-business-rules.md`; this is the narrative of *who does what, when*.

## 1. High-level flow

```
Organizer                         System / AI                    Participant
   |                                  |                              |
   | create event ------------------> |                              |
   | (name, total, count, recipient,  |                              |
   |  method, date range, split)      |                              |
   |                                  | generate public link         |
   | <----- public link + QR -------- |                              |
   | upload event expense receipt --> | (optional, validates cost)   |
   | share link via WhatsApp -------------------------------------->  | opens link
   |                                  | show event + expected share   | identifies (name+phone)
   |                                  | <------------ upload receipt --| (camera/gallery)
   |                                  | enqueue AI validation         |
   |                                  | extract + score + verdict     |
   |                                  | -- result -->                 | sees "valid / review / problem"
   | <--- dashboard updates --------- |                              |
   | review exceptions / override     |                              |
   | send reminders ---------------------------------------------->   | (WhatsApp message)
   | close event                      |                              |
```

## 2. Organizer flow (detailed)

1. **Auth** — sign up / log in (magic link or password).
2. **Create event** — wizard:
   - Step 1: name + date.
   - Step 2: total amount + number of participants → app suggests equal share.
   - Step 3: recipient (who receives the money: usually the organizer) + accepted
     payment methods (Yape/Plin/transfer).
   - Step 4: valid payment date range (from–to).
   - Step 5: split mode (equal vs custom) + optional participant names.
3. **Link generated** — copy link, show QR, "Share to WhatsApp" button.
4. **(Optional) Upload event expense receipt** — proves the real cost; AI extracts
   total and flags if it differs materially from the event total.
5. **Monitor dashboard** — collected vs pending, list of participants by status.
6. **Handle exceptions** — open review queue, view receipt + AI explanation,
   approve / reject / override / mark cash.
7. **Remind** — one tap generates a pre-filled WhatsApp message per pending person.
8. **Close** — when collected ≥ total (or organizer decides), archive the event.

## 3. Participant flow (detailed)

1. **Open link** — public page, no login. Sees event name, total, their expected
   share, accepted methods, recipient, and the valid date range.
2. **Identify** — pick their name from the list (if predefined) OR add themselves
   with name + phone. Stored in a lightweight session (cookie/token) so they can
   return without re-typing.
3. **Pay outside the app** — they Yape/Plin/transfer to the recipient (CuentaClara
   does **not** move money).
4. **Upload receipt** — take photo or pick from gallery. Optional note.
5. **Processing** — sees a "validando…" state while the queue runs AI.
6. **Result**:
   - ✅ *Valid* — amount, date and recipient match → "¡Pago confirmado!".
   - 🕓 *Under review* — low confidence or mismatch → "El organizador lo revisará".
   - ⚠️ *Problem* — clear mismatch (wrong amount/date/recipient) → reason + re-upload.
7. **Return visits** — can re-open the link to check status or upload again.

## 4. AI validation flow (summary)

See `06-ai-validation.md` for the full pipeline.

```
receipt uploaded → stored in S3 → ValidateReceiptJob (queue)
   → call vision model → extract {amount, date, recipient, method, confidence, explanation}
   → apply business rules (amount/date/recipient/duplicate)
   → set verdict: validated | needs_review | rejected
   → notify participant + update dashboard
```

## 5. State transitions (overview)

- **Participant payment status:** `pending → submitted → (validated | needs_review | rejected)`;
  `needs_review → validated | rejected` (organizer); any → `paid_cash` (manual override).
- **Event status:** `draft → active → closed → archived`.

Full state machines and guards are defined in `08-business-rules.md`.

## 6. Key decision points

| Decision | Owner | Default |
|----------|-------|---------|
| Predefined vs self-registered participants | Organizer at creation | Allow both |
| Auto-accept threshold | System config | confidence ≥ 0.85 AND all rules pass |
| What counts as "match" on amount | Business rules | exact, ± rounding tolerance |
| Reminder channel | Organizer | WhatsApp deep link (`wa.me`) |
| Closing the event | Organizer | manual; suggested when collected ≥ total |
