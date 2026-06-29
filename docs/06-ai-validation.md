# docs/06-ai-validation.md

# CuentaClara — AI Receipt Validation

The AI **assists**, it does not make irreversible decisions. Every output is
explainable, overridable, and logged (human-override principle from CLAUDE.md).

> **Lean MVP note:** v1 uses a **single-rule verdict** — `amount ≈ share AND
> confidence ≥ threshold` → `validated`, else `needs_review`. Date, method and
> recipient are **extracted and displayed to the organizer but do NOT gate** the
> verdict (screenshot matching is too unreliable to auto-reject on). No duplicate
> detection and no AI on expense receipts in v1. The multi-dimension engine below
> is the v2 target. See `13-mvp-critique-and-simplification.md`.

## 1. Responsibility split

- **Vision model** — *reads* the image. Extracts structured fields + confidence +
  a short explanation. It does **not** decide pass/fail.
- **Rule engine** (`ReceiptRuleEngine`, plain PHP) — *decides* the verdict by
  comparing extracted fields to event expectations using the rules in
  `08-business-rules.md`. Deterministic, testable, auditable.

This separation keeps the money-affecting logic deterministic and unit-tested, and
isolates the non-deterministic part to extraction.

## 2. Pipeline

```
1. Receipt stored in S3 (private).
2. ValidateReceiptJob picks it up.
3. ReceiptVisionService sends the image to the vision model with a strict prompt
   + JSON schema for the output.
4. Model returns structured JSON (validated against schema; retry on malformed).
5. Duplicate check (image hash / extracted txn id) within the event.
6. ReceiptRuleEngine compares to {expected_share, valid_from, valid_to, recipient,
   accepted_methods} → per-dimension pass/warn/fail → verdict.
7. ApplyValidationVerdict writes ai_validations row + updates payment status.
8. Participant notified; dashboard updated; audit log written.
```

## 3. Extraction contract (model output schema)

The model is instructed to return **only** this JSON:

```json
{
  "is_payment_receipt": true,
  "amount": { "value": 40.00, "currency": "PEN", "confidence": 0.94 },
  "payment_date": { "value": "2026-06-24", "confidence": 0.90 },
  "recipient": { "name": "Caro Rojas", "handle_or_phone": "999888777", "confidence": 0.81 },
  "payment_method": { "value": "yape", "confidence": 0.97 },
  "transaction_id": { "value": "0001234567", "confidence": 0.7 },
  "overall_confidence": 0.86,
  "explanation": "Yape transfer of S/ 40 to Caro on 24 Jun 2026.",
  "warnings": ["recipient name partially legible"]
}
```

Rules for the model:
- `payment_method` ∈ {`yape`,`plin`,`bank_transfer`,`cash`,`other`}.
- Dates normalized to ISO `YYYY-MM-DD`.
- Amounts as decimal in the receipt's currency; engine converts to cents.
- If the image is not a payment receipt, set `is_payment_receipt=false` and stop.
- Never invent values; use `null` + low confidence when unreadable.

## 4. Rule engine → verdict

Inputs: extraction JSON + event expectations. Per-dimension result per
`08-business-rules.md §4`. Verdict resolution:

| Condition | Verdict |
|-----------|---------|
| amount/date/recipient all PASS, `overall_confidence ≥ 0.85`, not duplicate | `validated` |
| any dimension FAIL with high confidence (e.g. wrong recipient) | `rejected` |
| not a payment receipt | `rejected` (reason: `not_a_receipt`) |
| anything else (WARN, low confidence, partial, overpay, possible duplicate) | `needs_review` |

Each verdict carries a machine reason code + the human explanation for the UI.

## 5. Prompt design (sketch)

System prompt establishes role: *"You are a careful receipt-reading assistant for
Peruvian payment apps (Yape, Plin, bank transfers). Extract only what you can see.
Return strict JSON matching the schema. Do not judge correctness."*

User content: the image + a compact context block (expected amount, recipient
name/handle, date range) **only to aid OCR disambiguation**, with an explicit
instruction *not* to force matches. Decisioning stays in the rule engine.

## 6. Confidence & thresholds

- `AI_CONFIDENCE_THRESHOLD` (default 0.85) gates auto-validation.
- Below threshold → `needs_review` regardless of field matches.
- Thresholds are config, tunable per observed false-accept/false-reject rates.

## 7. Duplicate detection

- Compute a byte/perceptual hash on upload; also compare extracted `transaction_id`.
- Match within the same event → `needs_review` reason `possible_duplicate`
  (prevents two people claiming the same screenshot, or one person double-counting).

## 8. Event expense receipt

Same extraction pipeline, but compared to `event.total_amount`. If
`|extracted_total − event_total| / event_total > EXPENSE_MISMATCH_THRESHOLD`
(default 10%), flag `expense_mismatch` for the organizer. Does not affect
participant collection math (BR-X3).

## 9. Failure handling

- Malformed JSON → retry with a stricter reminder; after N tries → `needs_review`
  reason `ai_parse_failed`.
- Provider/timeout error → job retries with backoff; exhausted → `needs_review`
  reason `ai_unavailable`. **Never auto-reject due to our outage.**
- All raw model responses are stored (truncated if huge) for audit & tuning.

## 10. Privacy & safety

- Images contain financial PII. Use a provider/config that does not retain data for
  training where possible; document the data path in the privacy policy.
- Strip nothing the organizer needs, but never expose raw AI JSON to participants —
  show a friendly summary only.

## 11. Metrics to track

- Auto-validation rate, false-accept rate, false-reject rate.
- Mean confidence, latency, cost per validation.
- % needs_review by reason code (tune rules from this).
