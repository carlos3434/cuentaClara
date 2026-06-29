# docs/07-prd.md

# CuentaClara — Product Requirements Document (PRD)

> Status: **Draft for approval** · Owner: Product · Related: all docs in this folder.

## 1. Summary

CuentaClara lets an organizer create a shared-expense event, share a public link
through WhatsApp, and have each participant upload their own payment receipt. AI
reads the receipt and checks it against the expected amount, date range and
recipient. The organizer gets a live dashboard of paid vs. pending and only has
to review exceptions.

**One-liner:** *"Share a link, collect the money, let the AI check the screenshots."*

## 2. Goals & non-goals

### Goals (MVP)
1. Organizer creates an event in under 60 seconds.
2. Generate a shareable public link (no app install, no participant login).
3. Participant uploads a receipt in under 30 seconds on mobile.
4. AI validates amount, date, recipient, payment method + confidence + explanation.
5. Organizer dashboard answers: paid / pending / who uploaded / which need review.
6. Organizer can send reminders to people who have not paid.
7. Organizer can manually override any AI decision.

### Non-goals (MVP)
- Replacing WhatsApp as the communication channel.
- Processing or moving real money (no payment gateway, no Yape API integration).
- Multi-currency. PEN only at launch.
- Participant accounts, social login, or profiles.
- Native iOS/Android apps.
- Accounting exports, invoices, or tax features.

## 3. Target users & personas

- **Primary — The Organizer ("Caro").** Organizes the BBQ, fronts the money,
  chases everyone on WhatsApp. Wants the chasing to stop. Tech comfort: medium.
- **Secondary — The Participant ("José").** Owes S/ 40. Already paid via Yape,
  just needs to prove it fast. Tech comfort: any. Will abandon if it takes effort.

## 4. Success metrics

| Metric | Target (post-MVP) |
|--------|--------------------|
| Event creation completion rate | > 80% of started events |
| Participant link → receipt uploaded | > 60% of openers |
| Receipts auto-validated without organizer review | > 70% |
| AI false-accept rate (validated but wrong) | < 3% |
| Median time from link open to upload | < 60s |
| Organizer reminders sent per event | tracked, not targeted |

## 5. Functional requirements

### Organizer
- FR-O1 Sign up / log in (email magic link or password).
- FR-O2 Create event with: name, date, total amount, participant count, recipient,
  payment method(s), valid payment date range, currency (PEN default).
- FR-O3 Choose split mode: equal split, or custom amount per participant.
- FR-O4 Get and copy/share a public link + WhatsApp share intent.
- FR-O5 Upload the event expense receipt (the real cost the organizer paid).
- FR-O6 See dashboard: total, collected, pending, per-participant status.
- FR-O7 Review queue of receipts needing attention (low confidence / mismatch).
- FR-O8 Approve, reject, or override any receipt; mark a participant as paid in cash.
- FR-O9 Send reminders (generate WhatsApp message) to pending participants.
- FR-O10 Close / archive an event.

### Participant
- FR-P1 Open public link without login.
- FR-P2 See event summary + their expected share (or pick/enter their name).
- FR-P3 Identify themselves (name + phone) — lightweight, no password.
- FR-P4 Upload one or more receipt images (camera or gallery).
- FR-P5 See validation result (valid / under review / problem) with a plain message.
- FR-P6 Re-upload if rejected.

### System / AI
- FR-S1 Store images in S3, never publicly listable.
- FR-S2 Run AI validation asynchronously via queue; show "processing" state.
- FR-S3 Extract amount, date, recipient, payment method, confidence, explanation.
- FR-S4 Apply business rules to produce a verdict (see `08-business-rules.md`).
- FR-S5 Detect duplicate receipts within an event.
- FR-S6 Keep a full audit trail of AI output and human overrides.

## 6. Non-functional requirements

- **Mobile-first**: usable on a 360px-wide screen, one-handed, on 3G.
- **Performance**: public event page first paint < 2s on mid-range Android.
- **Availability**: best-effort; queue must retry AI failures.
- **Security/Privacy**: receipts contain financial data — encrypted at rest,
  signed time-limited URLs, no public bucket. See `12-risks-and-edge-cases.md`.
- **Accessibility**: legible contrast, large tap targets (≥44px), Spanish UI copy.
- **Observability**: log AI confidence, latency, cost per validation.

## 7. Assumptions & open questions

Assumptions:
- Peru market; Yape/Plin/bank transfer; PEN; Spanish UI.
- The organizer's name/phone is the expected receipt recipient.
- One organizer per event for MVP.

Open questions (need product decision):
1. Should participants be **predefined** by the organizer (named list) or
   **self-registered** (anyone with the link adds themselves)? *Proposal: support
   both — organizer optionally pre-fills names; link still allows self-add.*
2. Do we store participant phone numbers (privacy/consent)? *Proposal: optional,
   used only to build the reminder message; not shared with other participants.*
3. WhatsApp reminders: manual share intent vs. WhatsApp Business API? *Proposal:
   manual `wa.me` deep link for MVP (zero cost, zero approval).*

## 8. Out of scope explicitly

Refunds processing, splitting across multiple organizers, recurring events,
tipping, currency conversion, and integrations with banking APIs.
