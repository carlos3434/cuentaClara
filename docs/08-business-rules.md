# docs/08-business-rules.md

# CuentaClara — Business Rules

Authoritative rules. The API (`10`), DB (`09`) and AI pipeline (`06`) must enforce
these. Amounts are integer **cents** of PEN unless stated.

> **Lean MVP note:** several rules below are **deferred to v2** — custom split
> (§2 BR-S2), partial payments & overpayment (§4 WARN paths, §6 BR-M4/M5),
> duplicate detection (BR-V5), and date/recipient/method **gating** (§4 BR-V2–V4
> are display-only in v1). v1 verdict = amount match + confidence (see `06` and
> `13`). Single payment deadline replaces the `valid_from/valid_to` range.

## 1. Events

- BR-E1 An event belongs to exactly one organizer (MVP).
- BR-E2 `total_amount > 0` and `participant_count >= 1`.
- BR-E3 `valid_from <= valid_to`. Both required.
- BR-E4 Event status: `draft → active → closed → archived`. A `draft` has no live
  public link; publishing moves it to `active`.
- BR-E5 The **sum of participant shares** must equal `total_amount`. The system
  enforces this when shares are edited (the remainder cent goes to the last share).
- BR-E6 Editing `total_amount` or shares after participants have paid is allowed
  but flagged; existing validated payments are not retroactively invalidated.

## 2. Splitting

- BR-S1 **Equal split:** `share = floor(total / count)`; the rounding remainder
  (`total - share*count`) is added to one designated participant (default: the
  last one). Sum always equals total (BR-E5).
- BR-S2 **Custom split:** organizer sets each share; system validates sum == total
  before publishing.
- BR-S3 A participant's share is the **expected amount** used in amount matching.

## 3. Participants

- BR-P1 A participant is identified by `name` + `phone` within an event.
- BR-P2 No password. A signed session token ties uploads to a participant.
- BR-P3 Self-registration (if enabled) creates a participant on first identify;
  shares for self-registered users default to the equal-split share.
- BR-P4 A participant may upload multiple receipts (e.g. paid in two parts).
- BR-P5 A participant's status is **derived** from their payments (see §5).

## 4. Receipt validation rules

A receipt is checked on four dimensions. Each produces pass / warn / fail.

- BR-V1 **Amount match:**
  - PASS if `extracted_amount == expected_share` (exact).
  - PASS (tolerance) if `|extracted - expected| <= max(1 cent, rounding remainder)`.
  - WARN if `extracted < expected` → treated as **partial payment** (see §6).
  - WARN if `extracted > expected` → **overpayment** (accept, flag, note credit).
  - FAIL only if amount is unreadable AND confidence low.
- BR-V2 **Date match:** PASS if extracted payment date ∈ `[valid_from, valid_to]`.
  WARN if outside range (organizer decides). FAIL if no date found and low confidence.
- BR-V3 **Recipient match:** PASS if extracted recipient name/phone/handle matches
  the event recipient (fuzzy match, accent/case-insensitive). WARN on partial
  match. FAIL on clear mismatch (money sent to someone else).
- BR-V4 **Payment method:** informational; must be one of the accepted methods,
  otherwise WARN.
- BR-V5 **Duplicate detection:** if an image hash (or extracted txn id) already
  exists in the event, mark `needs_review` with reason `possible_duplicate` and do
  not auto-validate.

### Verdict resolution
- **`validated`** — all of {amount, date, recipient} PASS **and** `confidence >= 0.85`
  **and** not a duplicate.
- **`rejected`** — any dimension FAIL with high confidence (e.g. wrong recipient).
- **`needs_review`** — everything else (any WARN, confidence `< 0.85`, partial,
  overpayment, possible duplicate, unreadable). Default-safe: *when in doubt, a
  human reviews.*

> The AI never auto-**rejects** a participant's good-faith upload silently; a
> rejection always carries a human-readable reason and allows re-upload.

## 5. Payment & participant status (state machine)

### Payment (single receipt)
```
submitted ──ai──> validated
          ──ai──> needs_review ──organizer──> validated | rejected
          ──ai──> rejected ──participant re-upload──> submitted
```
- Any payment can be set to `validated` or `rejected` by organizer override
  (logged, with reason). `paid_cash` is an organizer-only manual payment record.

### Participant (derived)
- `pending` — no validated/credited payments.
- `partial` — sum of validated payments `> 0` but `< share`.
- `paid` — sum of validated payments `>= share`.
- `overpaid` — sum `> share` (informational; surplus tracked, not auto-refunded).
- `in_review` — has at least one `needs_review` payment and not yet `paid`.

## 6. Money math rules

- BR-M1 `collected = Σ validated/credited payments across participants`.
- BR-M2 `pending = max(0, total_amount − collected)`.
- BR-M3 Invariant shown on dashboard: `collected + outstanding_shares == total`
  (within rounding). The dashboard must always reconcile.
- BR-M4 Partial payments accumulate toward a participant's share.
- BR-M5 Overpayment is recorded as a credit/note; no automatic refund in MVP.

## 7. Overrides & audit

- BR-A1 Every AI verdict and every organizer override is written to an audit log
  with: actor, before/after status, reason, timestamp.
- BR-A2 Organizer override always wins over AI (human-override principle).
- BR-A3 Overrides never delete the original AI output (kept for learning/dispute).

## 8. Event expense receipt (organizer's own cost)

- BR-X1 Optional. One or more allowed per event.
- BR-X2 AI extracts total; if it differs from `event.total_amount` by more than a
  configurable threshold (e.g. 10%), flag `expense_mismatch` for the organizer.
- BR-X3 This receipt validates the *real cost*; it does **not** affect participant
  collection math, only provides legitimacy/context.

## 9. Reminders

- BR-R1 Reminders may target only `pending`, `partial`, or `in_review` participants.
- BR-R2 MVP generates a pre-filled `wa.me` message; sending is the organizer's action.
- BR-R3 Each generated reminder is logged (who, when) to avoid spamming.

## 10. Authorization (summary; see API doc)

- Organizer can act only on their own events.
- Participant can act only on their own participant record within an event, via the
  public link + session token.
- Public event page exposes only non-sensitive data (no other participants' phones).
