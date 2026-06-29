# CuentaClara — Documentation Index

CuentaClara is a mobile-first web app that helps an **organizer** collect shared
payments from a group, validate payment receipts with AI, and track who paid and
who still owes.

This folder holds the product and engineering analysis.

> **Status: the Lean MVP (v1) is built and tested** — Laravel + Inertia + Vue,
> 66 passing tests. Start at `docs/14` ("What's implemented" + how to run).
> `docs/03` and `docs/13` define the delivered v1 scope; `docs/02` and `docs/04`–`12`
> remain the broader north-star/target design.

## Reading order

| # | Document | Purpose |
|---|----------|---------|
| 01 | [Project Vision](01-project-vision.md) | Problem, idea, target users (source of truth) |
| 02 | [Business Flow & User Flows](02-business-flow.md) | End-to-end flows for organizer and participant |
| 03 | [MVP Scope](03-mvp-scope.md) | What is in / out of the first release |
| 04 | [UX Principles & Mobile UX](04-ux-principles.md) | Screen-by-screen mobile UX proposal |
| 05 | [Technical Stack](05-technical-stack.md) | Architecture, infra, Laravel layout |
| 06 | [AI Validation](06-ai-validation.md) | Receipt validation pipeline & contract |
| 07 | [PRD](07-prd.md) | Product Requirements Document |
| 08 | [Business Rules](08-business-rules.md) | Authoritative rules & state machines |
| 09 | [Database Model](09-database-model.md) | Schema, relations, migrations plan |
| 10 | [API Proposal](10-api-proposal.md) | REST contract (public + organizer) |
| 11 | [Development Phases](11-development-phases.md) | Phased delivery plan |
| 12 | [Risks & Edge Cases](12-risks-and-edge-cases.md) | Risks, mitigations, edge cases |
| 13 | [MVP Critique & Simplification](13-mvp-critique-and-simplification.md) | **Lean MVP v1 — authoritative for what ships first** |

> **Which doc wins?** Docs `02`–`12` are the **target/north-star design**. Doc `13`
> and the revised `03-mvp-scope.md` define the **lean v1** we build first. Where they
> disagree, **v1 wins for the first release.**

## Glossary (shared vocabulary)

- **Event** — A shared expense to be split (BBQ, football field, gift, trip).
- **Organizer** — Authenticated user who creates the event and reviews payments.
- **Participant** — Person who owes a share; identifies via the public link, no password.
- **Share** — The amount a single participant must pay.
- **Receipt** — Payment evidence uploaded by a participant (Yape/Plin/transfer screenshot).
- **Event expense receipt** — Evidence the organizer paid the real-world expense.
- **Validation** — AI extraction + rule checks producing a confidence score and verdict.

## Conventions used in these docs

- Currency: **PEN (S/)** for MVP; amounts stored as integer **cents**.
- Payment rails: **Yape, Plin, bank transfer** (Peru).
- All timestamps stored in UTC, displayed in `America/Lima`.
