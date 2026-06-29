# docs/12-risks-and-edge-cases.md

# CuentaClara — Risks & Edge Cases

## 1. Product risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Participants won't bother uploading | No data, organizer keeps chasing | Zero-login, <30s upload, WhatsApp reminders, value visible on landing |
| Organizer doesn't trust AI | Reviews everything, no time saved | Transparency ("IA leyó…"), always overridable, default-safe to review |
| We're seen as "another WhatsApp" | Low adoption | We hand off to WhatsApp; we own truth/tracking, not chat |
| AI cost per event too high | Unit economics break | Track cost/validation, cache by hash, cap retries, dedupe |

## 2. AI / validation edge cases

| Case | Handling |
|------|----------|
| Not a receipt (selfie, meme) | `is_payment_receipt=false` → `rejected` reason `not_a_receipt`, re-upload allowed |
| Blurry / unreadable | low confidence → `needs_review`, ask for clearer photo |
| Amount less than share | partial payment → `needs_review`/credit toward share (BR-M4) |
| Amount more than share | overpayment → accept + flag surplus (BR-M5), no auto-refund |
| Date outside valid range | WARN → `needs_review`; organizer decides |
| Money sent to wrong recipient | recipient FAIL → `rejected` with reason |
| Same screenshot uploaded twice | duplicate hash → `needs_review` reason `possible_duplicate` |
| Two participants upload the same receipt | duplicate detection + manual review |
| Edited/photoshopped receipt | out of MVP scope to detect reliably; human review + audit trail; document limitation |
| Foreign currency / non-PEN receipt | currency mismatch → `needs_review` (MVP is PEN only) |
| AI provider down / timeout | retries w/ backoff → `needs_review` reason `ai_unavailable`; never auto-reject |
| Malformed model JSON | strict schema + retry → `needs_review` reason `ai_parse_failed` |
| Cash payment, no receipt | organizer marks `paid_cash` manually |

## 3. Money & data integrity

| Case | Handling |
|------|----------|
| Rounding on equal split | remainder cent assigned to one participant (BR-S1); sum==total always |
| Organizer edits total after payments | allowed but flagged; validated payments preserved (BR-E6); totals recomputed |
| Participant removed after paying | block delete if validated payment exists; archive instead |
| Multiple partial payments | accumulate toward share; status `partial` until `>=` share |
| Concurrent override + AI verdict | DB transaction + organizer-wins precedence (BR-A2); audit both |
| Dashboard must reconcile | invariant test `collected + outstanding == total` in CI |

## 4. Security & privacy

| Risk | Mitigation |
|------|------------|
| Receipts are financial PII | private S3, signed URLs (short TTL), encryption at rest |
| Public link leaks data | sanitized public payload — no other participants' phones; unguessable slug |
| Slug enumeration | long random slug, throttle identify endpoint |
| One participant viewing another's receipt | session-token-scoped access; policies on payment endpoints |
| Spam uploads / abuse | rate limit per token+IP, max size/count, image-type validation |
| AI provider data retention | choose no-train config; document data path in privacy policy; consent on upload |
| Phone-number consent | collect only with notice; never expose to other participants |

## 5. UX edge cases

| Case | Handling |
|------|----------|
| Participant not in predefined list | self-register (if enabled) with name+phone |
| Same name, two people | disambiguate by phone; allow manual organizer fix |
| Slow / flaky mobile network | client compression, upload retry/queue, clear offline state |
| Wrong-orientation photo | fix EXIF orientation client-side |
| Participant uploads to wrong event | event context shown prominently before upload |
| Returning participant | session token restores identity & status |
| Organizer reopens a closed event | allowed from archive; status transitions logged |

## 6. Operational risks

| Risk | Mitigation |
|------|------------|
| Queue backlog → slow verdicts | autoscale worker, alert on backlog, show honest "processing" |
| S3 cost growth | lifecycle policy, image compression, optional retention limit |
| AI threshold mis-tuned | metrics dashboard, config-driven thresholds, periodic review |
| Legal/regulatory (handling payments evidence) | we never move money; clarify ToS that we only track evidence |

## 7. Explicit MVP limitations (state openly)
- Cannot reliably detect forged/edited receipts.
- PEN only; no FX.
- Reminders are manual WhatsApp hand-offs (no automated sending).
- Single organizer per event.
- No real-money settlement — CuentaClara tracks, it does not pay.

## 8. Top decisions still needing sign-off
1. Predefined vs self-registered participants (default: both).
2. Storing participant phone numbers + consent copy.
3. Auto-validation confidence threshold (default 0.85).
4. Magic-link vs password auth for organizers.

(See `07-prd.md §7` for context.)
