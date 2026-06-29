# CLAUDE.md

# Project: CuentaClara

CuentaClara is a mobile-first web application for organizing shared payments between friends, coworkers, or groups.

The app helps an organizer create an event, share a payment link, collect payment receipts, validate them with AI, and track who paid and who still owes money.

## Working Rules

Do not start coding immediately.

Before implementing any feature:

1. Analyze the business flow.
2. Identify edge cases.
3. Propose the database model.
4. Propose the API contract.
5. Propose the mobile UX.
6. Ask for approval before coding.

## Tech Stack

Backend:

* Laravel
* MySQL
* Queues
* Jobs
* Policies
* Form Requests
* Services / Actions

Frontend:

* Vue.js
* Mobile-first UI
* Simple components
* Clear loading and error states

Infrastructure:

* AWS
* S3 for receipt images
* RDS MySQL
* Queue worker
* Environment-based configuration

AI:

* Vision model for receipt validation
* Extract amount, date, recipient, payment method
* Return confidence score and explanation

## Product Principles

* Do not replace WhatsApp.
* The organizer shares a link through WhatsApp or any chat app.
* Participants upload their own payment evidence.
* The organizer should only review exceptions.
* The app must be very easy to use on mobile.
* Avoid unnecessary login friction.
* AI should assist, not make irreversible decisions.
* Human override must always exist.

## Main Workflow

1. Organizer creates an event.
2. Organizer enters:

    * event name
    * event date
    * total amount
    * participant count
    * payment recipient
    * payment method
    * valid payment date range
3. The app generates a public event link.
4. Organizer shares the link in WhatsApp.
5. Participant opens the link.
6. Participant identifies themselves.
7. Participant uploads payment receipt.
8. AI validates amount, date and recipient.
9. Participant sees validation result.
10. Organizer sees dashboard with paid and pending amounts.
11. Organizer sends reminders to people who have not paid.

## Important Feature

The organizer can upload the original event expense receipt.

Example:
The organizer paid for the football field, restaurant reservation, gift, or event expense.

This receipt is different from participant payment receipts.

It validates that the event had a real expense, while participant receipts validate reimbursements to the organizer.

## Expected First Task

Read all files inside docs/.

Then produce:

1. Product analysis
2. MVP proposal
3. Business rules
4. Database model proposal
5. API proposal
6. Mobile UX proposal
7. Development phases

Do not write application code until the analysis is approved.
