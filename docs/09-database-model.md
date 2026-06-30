# docs/09-database-model.md

> ⚠️ **Actualización post-MVP:** se agregaron `users.role` + `users.is_active`,
> la tabla `settings`, y en `receipts` se reemplazó `extracted_operation` por
> `operation_hash` (+ `extracted_operation`/`extracted_*` de OCR). Ver
> [`docs/15`](15-post-mvp-changelog.md) (§7, §8). Este doc describe el modelo
> original del MVP.

# CuentaClara — Database Model

MySQL (RDS). Money stored as **integer cents** (`BIGINT`/`INT UNSIGNED`). All
timestamps UTC. Soft deletes where audit matters.

> **Lean MVP note:** v1 ships **5 tables** — `users`, `events`, `participants`,
> `receipts` (the receipt row *is* the payment record and carries the AI fields),
> `event_expenses`. The separate `payments`, `ai_validations` (polymorphic),
> `reminders`, and `audit_logs` tables below are **v2**; in v1 the override trail
> is a few columns on `receipts` (`decided_by`, `decided_at`, `decision_note`).
> See `13-mvp-critique-and-simplification.md` §3 for the exact v1 schema.

## 1. ER overview

```
users (organizers)
  └─1:N─ events
            ├─1:N─ participants
            │         └─1:N─ payments ─1:1─ receipts ─1:1─ ai_validations
            ├─1:N─ event_expenses ─1:1─ ai_validations
            ├─1:N─ reminders ──N:1── participants
            └─1:N─ audit_logs
```

A `payment` is the money-tracking row; a `receipt` is the uploaded image; an
`ai_validation` is the extraction+verdict for a receipt (or event expense).

## 2. Tables

### users
| column | type | notes |
|--------|------|-------|
| id | bigint pk | |
| name | string | |
| email | string unique | |
| password | string nullable | nullable if magic-link only |
| phone | string nullable | used as default event recipient |
| created_at / updated_at | timestamps | |

### events
| column | type | notes |
|--------|------|-------|
| id | bigint pk | |
| user_id | fk users | organizer |
| slug | string unique | public link token (unguessable) |
| name | string | |
| event_date | date | |
| currency | char(3) | default `PEN` |
| total_amount | int unsigned | cents |
| participant_count | int unsigned | expected headcount |
| split_mode | enum(`equal`,`custom`) | |
| recipient_name | string | who receives money |
| recipient_handle | string nullable | Yape/Plin number / account |
| accepted_methods | json | e.g. `["yape","plin","bank_transfer"]` |
| valid_from | date | payment window start |
| valid_to | date | payment window end |
| status | enum(`draft`,`active`,`closed`,`archived`) | |
| created_at / updated_at / deleted_at | | |

Indexes: `slug` unique, `user_id`, `status`.

### participants
| column | type | notes |
|--------|------|-------|
| id | bigint pk | |
| event_id | fk events | |
| name | string | |
| phone | string nullable | for reminders |
| share_amount | int unsigned | cents; expected amount (BR-S3) |
| is_self_registered | bool | |
| session_token | string nullable, indexed | binds public uploads |
| status | enum(`pending`,`partial`,`paid`,`overpaid`,`in_review`) | derived, denormalized for dashboard speed |
| created_at / updated_at | | |

Indexes: `(event_id, name)`, `session_token`. Unique-ish: `(event_id, phone)` when present.

### payments
| column | type | notes |
|--------|------|-------|
| id | bigint pk | |
| event_id | fk events | denormalized for queries |
| participant_id | fk participants | |
| receipt_id | fk receipts nullable | null when `paid_cash` |
| amount | int unsigned | cents counted toward share (validated/credited) |
| method | enum(...) | yape/plin/bank_transfer/cash/other |
| status | enum(`submitted`,`validated`,`needs_review`,`rejected`,`paid_cash`) | |
| reason_code | string nullable | e.g. `partial`,`possible_duplicate`,`ai_unavailable` |
| validated_by | enum(`ai`,`organizer`) nullable | |
| created_at / updated_at | | |

Indexes: `(event_id, status)`, `participant_id`.

### receipts
| column | type | notes |
|--------|------|-------|
| id | bigint pk | |
| event_id | fk events | |
| participant_id | fk participants | |
| s3_key | string | private object key |
| mime_type | string | |
| byte_hash | string indexed | duplicate detection (BR-V5) |
| note | text nullable | participant note |
| created_at | timestamp | |

Indexes: `(event_id, byte_hash)`.

### ai_validations
| column | type | notes |
|--------|------|-------|
| id | bigint pk | |
| validatable_type / validatable_id | morphs | receipt OR event_expense |
| is_payment_receipt | bool | |
| extracted_amount | int unsigned nullable | cents |
| extracted_currency | char(3) nullable | |
| extracted_date | date nullable | |
| extracted_recipient | string nullable | |
| extracted_method | string nullable | |
| transaction_id | string nullable | |
| amount_result / date_result / recipient_result / method_result | enum(`pass`,`warn`,`fail`) nullable | per-dimension |
| confidence | decimal(4,3) | overall |
| verdict | enum(`validated`,`needs_review`,`rejected`) | |
| reason_code | string nullable | |
| explanation | text | human-readable |
| raw_response | json | full model output (audit/tuning) |
| created_at | timestamp | |

### event_expenses
| column | type | notes |
|--------|------|-------|
| id | bigint pk | |
| event_id | fk events | |
| s3_key | string | |
| extracted_total | int unsigned nullable | from AI |
| mismatch_flag | bool default false | BR-X2 |
| created_at | timestamp | |

(AI output lives in `ai_validations` via the morph relation.)

### reminders
| column | type | notes |
|--------|------|-------|
| id | bigint pk | |
| event_id | fk events | |
| participant_id | fk participants | |
| channel | enum(`whatsapp`) | MVP |
| message | text | generated message (audit) |
| created_at | timestamp | |

### audit_logs
| column | type | notes |
|--------|------|-------|
| id | bigint pk | |
| event_id | fk events nullable | |
| actor_type | enum(`organizer`,`participant`,`ai`,`system`) | |
| actor_id | bigint nullable | |
| action | string | e.g. `payment.override`,`event.publish` |
| subject_type / subject_id | morphs | |
| before / after | json nullable | state snapshot |
| reason | text nullable | |
| created_at | timestamp | |

## 3. Derived vs stored

- `participants.status` and event totals (collected/pending) are **derived** from
  validated payments but **denormalized** (cached column / computed in a service)
  for fast dashboard rendering. A single service recomputes them after any payment
  status change to preserve the `collected + outstanding == total` invariant.

## 4. Key constraints & integrity

- FK with `ON DELETE` chosen per table (cascade receipts/payments with event;
  restrict deleting an event with money collected → archive instead).
- `Σ participants.share_amount == events.total_amount` enforced in
  `SplitShares` action before publish (BR-E5).
- `receipts.byte_hash` unique per event is *not* a DB unique (re-uploads allowed)
  but is indexed for the duplicate check.
- Monetary columns never store negative values; overpayment surplus is computed,
  not stored as negative.

## 5. Migration order (Phase mapping)

1. users, events
2. participants
3. receipts, payments
4. ai_validations
5. event_expenses
6. reminders, audit_logs
