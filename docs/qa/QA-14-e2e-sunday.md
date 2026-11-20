# QA-14 — End-to-End: Sunday Service Flow

**Scope:** Full Sunday morning workflow from service open to post-service report, tested as a real user sequence — not isolated unit tests.
**Sprint:** 5 (after all features land). Re-run before each major release.
**Risk:** Integration — many components touching each other.

> This test plan is a dress rehearsal. Run it as close to a real Sunday morning scenario as possible. One person plays each role.

---

## Scenario Overview

**Church:** Grace Community Chapel (test church)
**Date:** Simulated Sunday
**Actors:**
- Alice — `church-admin`
- Finance Secretary — `finance-secretary` role
- 50 test members (in seed data)
- 3 members with WhatsApp opted-in

**Sequence:**
1. Service created and opened
2. Attendance recorded (manual + WhatsApp CHECKIN)
3. Offering collected (cash + MoMo)
4. Service closed
5. Reports viewed
6. Weekly broadcast sent

---

## Pre-Test State

- [ ] Church seeded with 50 active members (10 with WhatsApp opt-in)
- [ ] Church has 100 SMS credits
- [ ] Church has a verified payout account (`payout_verified_at` set) and PayPerlez test keys (D-018). A Paystack subaccount is needed only for card gifts
- [ ] No open services exist
- [ ] No contributions exist for today

---

## Test Steps

### Phase 1 — Sunday Morning Prep (Alice, church-admin)

### E2E-01 — Create Sunday service
**Actor:** Alice
**Action:** `POST /api/v1/services` with:
```json
{
  "name": "Sunday Morning Service",
  "type": "sunday",
  "service_date": "2026-10-25",
  "starts_at": "09:00"
}
```
**Expected:**
- `201` — service created with `is_open: false` and a system-made `checkin_code` (a code in the body is ignored, D-044). Use that code wherever `SUN1025` appears below
- Service appears in the services list (or was already made by the weekly schedule, D-050)

### E2E-02 — Open service for check-in
**Actor:** Alice
**Action:** `PATCH /api/v1/services/{id}/checkin` with `{ "is_open": true }` (the service must be dated today, D-052)
**Expected:**
- `200` — `is_open: true`, `checkin_opened_at` set
- WhatsApp CHECKIN is now live for this service

---

### Phase 2 — Attendance (Multiple actors)

### E2E-03 — WhatsApp CHECKIN (opted-in members)
**Actor:** 3 test members with WhatsApp numbers
**Action:** Each sends "CHECKIN" to the platform WhatsApp number
**Expected:**
- 3 attendance records created (method: `whatsapp`)
- 3 confirmation replies sent within 30 seconds
- Attendance count: 3

### E2E-04 — WhatsApp CHECKIN with service code
**Actor:** One test member
**Action:** Sends "CHECKIN SUN1025"
**Expected:**
- Attendance record created for the correct service (not just any open service)
- Confirmation reply includes service name

### E2E-05 — Manual bulk check-in
**Actor:** Alice (church-admin)
**Action:** `POST /api/v1/services/{id}/attendance` with `{ "member_ids": [30 member ULIDs], "method": "manual" }`
**Expected:**
- `201` with `recorded: 30`, `already_checked_in: 0`
- 30 attendance records created (method: `manual`)
- Total attendance now: 34

### E2E-06 — Visitor recorded
**Actor:** Alice
**Action:** `POST /api/v1/services/{id}/visitors` with `{ "name": "Kofi Mensah" }`
**Expected:**
- `201`; a `visitor_records` row is made and an attendance row is linked to it (`visitor_record_id`), so the visitor is counted once per service
- Total: 35 (34 members + 1 visitor)

---

### Phase 3 — Offering Collection (Finance Secretary)

### E2E-07 — Record cash tithe
**Actor:** Finance Secretary
**Action:** `POST /api/v1/contributions` with an `Idempotency-Key` header and:
```json
{
  "member_id": "<member_ulid>",
  "amount": 200.00,
  "fund_type_id": "<tithe_ulid>",
  "payment_method": "cash",
  "service_id": "<service_ulid>"
}
```
**Expected:**
- `201` — contribution created
- Receipt SMS dispatched to `notifications` queue
- Member's phone receives SMS within 60 seconds (docs/04 wording, D-051):
  ```
  Grace Community Chapel: GHS 200.00 received (Tithe) on 25 Oct 2026.
  Your total giving in October: GHS 200.00.
  Thank you for your faithfulness.
  ```
- 1 SMS credit deducted from church balance (now 99)

### E2E-08 — Record cash offering (anonymous)
**Actor:** Finance Secretary
**Action:** `POST /api/v1/contributions` (with an `Idempotency-Key`) — no `member_id`; a gift with no member is anonymous and the response shows `is_anonymous: true`
**Expected:**
- `201` — anonymous contribution saved
- No receipt SMS sent

### E2E-09 — Initiate MoMo payment
**Actor:** Finance Secretary
> **Not built yet.** The digital path waits for the legal opinion and PayPerlez's webhook event names (D-046). This step describes the intended flow and is run once it exists.

**Action:** `POST /api/v1/contributions/initiate` with an `Idempotency-Key` and:
```json
{
  "member_id": "<member_ulid>",
  "amount": 500.00,
  "fund_type_id": "<offering_ulid>",
  "payment_method": "momo",
  "phone": "+233244000010"
}
```
**Expected:**
- `200` with `reference` (our ULID), `authorization_url: null` and `status: pending`; a `pending` contribution row exists with that id (D-053)
- PayPerlez test MoMo prompt sent to `+233244000010` (network chosen from the number)
- Member approves on test phone (or simulate approval)
- PayPerlez confirms (webhook, or our polling fallback) and the gift is recorded
- `ContributionReceived` event fired, then the automatic payout to the church (D-018)
- SMS receipt sent

### E2E-10 — Sunday report during service
**Actor:** Finance Secretary
**Action:** `GET /api/v1/services/{id}/offering-report`
**Expected:**
- Shows the gifts recorded at this service (the MoMo one is counted once it is confirmed; it has no service, so it appears in the `no_service` block and in `day_grand_total`, D-048)
- Breakdown by fund type and payment method:
  - Tithe: GHS 200.00 (cash)
  - Offering: GHS 500.00 (MoMo)
  - `day_grand_total`: GHS 700.00 plus the anonymous gift
- The report holds totals only; the anonymous gift is counted in `anonymous_count`, and no giver's name appears

---

### Phase 4 — Service Close

### E2E-11 — Close service
**Actor:** Alice
**Action:** `PATCH /api/v1/services/{id}/checkin` with `{ "is_open": false }`
**Expected:**
- `200` — `is_open: false`
- CHECKIN commands now rejected: "No service is currently open"

### E2E-12 — Late hand-recorded attendance on a closed service
**Actor:** Alice (`members.edit`)
**Action:** `POST /api/v1/services/{id}/attendance` with one member who was present but missed, after the service is closed
**Expected:**
- `201` with `recorded: 1` — a closed service still accepts a person recorded by hand (docs/08, QA-05 TC-05-14)
- WhatsApp and QR check-in stay refused after close (E2E-11); only a user with `members.edit` can add someone late
- Total attendance increases by 1 (carry that into E2E-13)

---

### Phase 5 — Post-Service Reports

### E2E-13 — Attendance report for the service
**Actor:** Alice
**Action:** `GET /api/v1/services/{id}/attendance` (its `summary` is the report; there is no separate report endpoint)
**Expected:**
- `summary.total`: 36 (35 from the earlier steps plus the late record from E2E-12), of which `visitors`: 1
- `summary.by_method`: whatsapp 4, manual 31 (30 bulk plus the late one), qr 0
- The visitor is a `visitor_records` row with `visit_count` 1 and `follow_up_status: pending`; "first-timer" means that row's first visit is this service. A separate first-timer count in a report is Sprint 5 (QA-11)

### E2E-14 — Full Sunday giving report
**Actor:** Alice
**Action:** `GET /api/v1/services/{id}/offering-report`
**Expected:**
- Shows final totals including the MoMo contribution (in `no_service` / `day_grand_total`)
- Voided contributions excluded from every number, counts included
- Exportable as CSV (`POST /api/v1/exports/contributions`, Sprint 5, QA-11)

### E2E-15 — 12-week attendance trend updated
**Action:** `GET /api/v1/reports/attendance?weeks=12` (Sprint 5)
**Expected:** Today's service (36 attendees) appears in the latest week's data point

---

### Phase 6 — Post-Service Broadcast

### E2E-16 — Weekly broadcast SMS
**Actor:** Alice
**Action:** `POST /api/v1/messages/broadcast` (Sprint 5) with:
```json
{
  "channel": "sms",
  "recipient_filter": { "status": "active" },
  "message": "Thank you for joining us today! Next Sunday we'll be studying Psalm 23. Bring a friend."
}
```
**Expected:**
- Credit check: 50 active members → 50 credits needed. Church has 98 (the cash tithe receipt and the MoMo offering receipt used 1 each).
- Broadcast dispatched
- All 50 active members receive the SMS (in sandbox: verify delivery logs)
- 50 credits deducted → church balance now 48
- `message_logs` has 50 rows for this broadcast

---

## Verification Checklist

After completing all steps, verify the full state:

- [ ] Service created, opened, and closed successfully
- [ ] Total attendance: 36 (4 WhatsApp + 30 manual + 1 late manual + 1 visitor)
- [ ] Total giving: GHS 700.00 (GHS 200 cash tithe + GHS 500 MoMo offering)
- [ ] Voided contributions: 0
- [ ] Receipt SMSs sent: 2 (the cash tithe now, and the MoMo offering once PayPerlez confirms it; the anonymous gift has none)
- [ ] SMS credits remaining: 48 (100 - 2 receipts - 50 broadcast = 48)
- [ ] Attendance trend updated with today's count
- [ ] No errors in queue worker logs
- [ ] No failed jobs in `failed_jobs` table

---

## Pass / Fail Criteria

This is an integration test. All 16 steps must pass in sequence for the flow to be considered working. Any failure pauses the test — do not skip ahead. The state carried forward matters.

**P0 failures (halt deploy):** E2E-07 (receipt SMS), E2E-09 (MoMo payment), E2E-13 (report accuracy).

Run this plan at least once before every production deploy. Automate it with browser automation in a future sprint.
