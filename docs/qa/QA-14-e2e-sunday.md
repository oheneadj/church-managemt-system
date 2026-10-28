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
- [ ] Church has a valid Paystack test subaccount
- [ ] No open services exist
- [ ] No contributions exist for today

---

## Test Steps

### Phase 1 — Sunday Morning Prep (Alice, church-admin)

### E2E-01 — Create Sunday service
**Actor:** Alice
**Action:** `POST /api/services` with:
```json
{
  "name": "Sunday Morning Service",
  "service_date": "2026-10-25",
  "service_type": "main",
  "checkin_code": "SUN1025"
}
```
**Expected:**
- `201` — service created with `status: created`
- Service appears in the services list

### E2E-02 — Open service for check-in
**Actor:** Alice
**Action:** `PATCH /api/services/{id}/open`
**Expected:**
- `200` — `status: open`, `opened_at` timestamp set
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
**Action:** `POST /api/services/{id}/attendance/bulk` with an array of 30 member ULIDs
**Expected:**
- 30 attendance records created (method: `manual`)
- Total attendance now: 34

### E2E-06 — Visitor recorded
**Actor:** Alice
**Action:** `POST /api/services/{id}/attendance` with a visitor record (no member_id, just `visitor_name: "Kofi Mensah"`)
**Expected:**
- Visitor attendance record created
- Total: 35 (34 members + 1 visitor)

---

### Phase 3 — Offering Collection (Finance Secretary)

### E2E-07 — Record cash tithe
**Actor:** Finance Secretary
**Action:** `POST /api/contributions` with:
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
- Member's phone receives SMS within 60 seconds:
  ```
  Grace Community Chapel: GHS 200.00 received (Tithe) on 25 Oct 2026. Thank you for your faithfulness!
  ```
- 1 SMS credit deducted from church balance (now 99)

### E2E-08 — Record cash offering (anonymous)
**Actor:** Finance Secretary
**Action:** `POST /api/contributions` — no `member_id`, `"is_anonymous": true`
**Expected:**
- `201` — anonymous contribution saved
- No receipt SMS sent

### E2E-09 — Initiate MoMo payment
**Actor:** Finance Secretary
**Action:** `POST /api/contributions/momo` with:
```json
{
  "member_id": "<member_ulid>",
  "amount": 500.00,
  "fund_type_id": "<offering_ulid>",
  "phone": "+233244000010",
  "network": "mtn"
}
```
**Expected:**
- Paystack test MoMo prompt sent to `+233244000010`
- Member approves on test phone (or simulate approval)
- `charge.success` webhook fires
- `ProcessPaystackWebhookJob` creates contribution record
- `ContributionReceived` event fired
- SMS receipt sent

### E2E-10 — Sunday report during service
**Actor:** Finance Secretary
**Action:** `GET /api/contributions/sunday-report?service_id={id}`
**Expected:**
- Shows all contributions for this service (including the MoMo one)
- Breakdown by fund type:
  - Tithe: GHS 200.00 (cash)
  - Offering: GHS 500.00 (MoMo)
  - Total: GHS 700.00
- Anonymous contribution listed as "Anonymous"

---

### Phase 4 — Service Close

### E2E-11 — Close service
**Actor:** Alice
**Action:** `PATCH /api/services/{id}/close`
**Expected:**
- `200` — `status: closed`, `closed_at` timestamp set
- CHECKIN commands now rejected: "No service is currently open"

### E2E-12 — Attempt late check-in on closed service
**Actor:** Alice (manual override attempt)
**Action:** `POST /api/services/{id}/attendance` after service is closed
**Expected:**
- `422 { "message": "This service is closed. No further check-ins can be recorded." }`
- No attendance record created

---

### Phase 5 — Post-Service Reports

### E2E-13 — Attendance report for the service
**Actor:** Alice
**Action:** `GET /api/services/{id}/attendance/report`
**Expected:**
- Total: 35 (34 members + 1 visitor)
- By method: WhatsApp 4, Manual 30, Visitor 1
- First-timer count: 1 (Kofi Mensah, the visitor)

### E2E-14 — Full Sunday giving report
**Actor:** Alice
**Action:** `GET /api/contributions/sunday-report?service_id={id}`
**Expected:**
- Shows final totals including the MoMo contribution
- Voided contributions excluded
- Exportable as CSV

### E2E-15 — 12-week attendance trend updated
**Action:** `GET /api/reports/attendance-trends?period=12w`
**Expected:** Today's service (35 attendees) appears in the latest week's data point

---

### Phase 6 — Post-Service Broadcast

### E2E-16 — Weekly broadcast SMS
**Actor:** Alice
**Action:** `POST /api/communications/broadcast` with:
```json
{
  "message": "Thank you for joining us today! Next Sunday we'll be studying Psalm 23. Bring a friend.",
  "recipient_type": "all_active",
  "channel": "sms"
}
```
**Expected:**
- Credit check: 50 active members → 50 credits needed. Church has 99 (after tithe receipt used 1).
- Broadcast dispatched
- All 50 active members receive the SMS (in sandbox: verify delivery logs)
- 50 credits deducted → church balance now 49
- `message_logs` has 50 rows for this broadcast

---

## Verification Checklist

After completing all steps, verify the full state:

- [ ] Service created, opened, and closed successfully
- [ ] Total attendance: 35 (4 WhatsApp + 30 manual + 1 visitor)
- [ ] Total giving: GHS 700.00 (GHS 200 cash tithe + GHS 500 MoMo offering)
- [ ] Voided contributions: 0
- [ ] Receipt SMSs sent: 1 (tithe only — offering receipt came from Paystack webhook)
- [ ] SMS credits remaining: 49 (100 - 1 tithe receipt - 50 broadcast = 49)
- [ ] Attendance trend updated with today's count
- [ ] No errors in queue worker logs
- [ ] No failed jobs in `failed_jobs` table

---

## Pass / Fail Criteria

This is an integration test. All 16 steps must pass in sequence for the flow to be considered working. Any failure pauses the test — do not skip ahead. The state carried forward matters.

**P0 failures (halt deploy):** E2E-07 (receipt SMS), E2E-09 (MoMo payment), E2E-13 (report accuracy).

Run this plan at least once before every production deploy. Automate it with browser automation in a future sprint.
