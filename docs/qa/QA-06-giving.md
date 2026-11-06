# QA-06 — Giving & Contributions

**Scope:** Cash recording, MoMo payment initiation, receipt delivery, void/correction, pledge tracking, Sunday offering report.
**Sprint:** 4
**Risk:** R1 (MoMo rejection rate), financial integrity

---

## Setup

- Test church with a verified payout account and PayPerlez test keys for the MoMo cases (D-018); a Paystack subaccount only for card cases
- Finance secretary user with `giving.view`, `giving.record`, `giving.edit` and `giving.summary` permissions
- Pastor user with `giving.summary` only (no `giving.view`, D-046)
- 10 members with phone numbers
- Fund types: Tithe, Sunday Offering, Building Fund (the church's seven defaults come from `config/giving.php`)
- SMS receipts go through PayPerlez's test environment (D-038) or the `log` driver locally
- Every `POST` that records a gift or pledge sends an `Idempotency-Key` header (docs/08); all paths are under `/api/v1`

---

## Test Cases

### Cash Recording

### TC-06-01 — Record a cash contribution
**Action:** `POST /api/v1/contributions` with:
```json
{
  "member_id": "{member_ulid}",
  "fund_type_id": "{tithe_id}",
  "amount": 150.00,
  "payment_method": "cash",
  "service_id": "{service_ulid}",
  "notes": "Gave at Sunday service"
}
```
**Expected:**
- Contribution created with ULID, `201`
- `payment_method: cash`, `status: completed` from the start; no processing fee and no payment reference (docs/08, D-053)
- `fund_name` holds the fund's name at that moment (D-048)
- SMS receipt queued for delivery to member's phone

### TC-06-02 — Cash receipt SMS format
**Expected SMS content** (docs/04 wording, D-051):
```
Grace Assembly: GHS 150.00 received (Tithe) on 25 Oct 2026.
Your total giving in October: GHS 150.00.
Thank you for your faithfulness.
```
- Amount formatted correctly (2 decimal places, GHS prefix)
- Church name in message (not platform name)
- No placeholder text `{{variable}}` visible

### TC-06-03 — Record contribution without member (anonymous)
**Action:** Submit contribution with no `member_id`
**Expected:**
- Contribution created with `member_id: null`; the response shows `is_anonymous: true` (worked out from the missing member, not stored)
- No SMS receipt attempted
- Counted in the offering report's `anonymous_count`; the report holds totals only and never names a giver

### TC-06-04 — Validation: Amount must be positive
**Action:** Submit `"amount": -50` or `"amount": 0`
**Expected:** `422` validation error on `amount`

### TC-06-05 — Validation: Amount must not exceed reasonable maximum
**Action:** Submit `"amount": 9999999.99`
**Expected:** `422` — amount exceeds maximum allowed (GHS 999,999.99)

---

### MoMo Payments

> **Not built yet.** The digital path waits for the legal opinion and PayPerlez's webhook event names (D-046). These cases describe the design in D-018, D-020 and D-053 and are run once it exists. Run them against PayPerlez's test environment; webhook and signature cases for cards are in QA-07.

### TC-06-06 — Initiate MoMo payment
**Action:** `POST /api/v1/contributions/initiate` with an `Idempotency-Key` and:
```json
{
  "member_id": "{member_ulid}",
  "fund_type_id": "{offering_id}",
  "amount": 50.00,
  "payment_method": "momo",
  "phone": "+233244000001"
}
```
**Expected:**
- `200` with `reference` (our ULID), `authorization_url: null`, `status: pending`
- A `contributions` row exists with that id, `status: pending`, `payment_method: momo`, `payperlez_reference` set
- PayPerlez receives the same id as its client reference and idempotency key
- The member gets the MoMo prompt (test mode: simulated)
- The pending gift is in no total, report, pledge sum or default gift list; `GET /contributions?status=pending` shows it
- Sending the same request with the same `Idempotency-Key` returns the same `reference` and creates no second row

### TC-06-07 — MoMo payment succeeds
**Action:** Deliver PayPerlez's success event for the reference in TC-06-06 (or let the polling job find it)
**Expected:**
- `status` becomes `completed`; `processing_fee` and `total_paid` are filled in
- `ContributionReceived` fires and the receipt SMS is sent
- A `disbursements` row is created for the gift (D-018)
- The payload is stored in `webhook_logs`; the gift now counts in totals

### TC-06-08 — MoMo payment fails or is abandoned
**Action:** Deliver PayPerlez's failure event, or let a payment stay unpaid past the grace period so the polling job marks it
**Expected:**
- `status` becomes `failed`; no receipt, no disbursement, nothing in any total
- The failed gift is visible with `status=failed` so the finance secretary can record it as cash instead
- A success event that arrives after the failure moves the row to `completed` (the money arrived, D-053)

### TC-06-09 — Webhook signature verification
**Action:** Send a PayPerlez webhook with an invalid `X-Payperlez-Signature`, and a card webhook with an invalid HMAC-SHA512 signature
**Expected:** rejected (`401`) — webhook is rejected without processing

### TC-06-10 — Webhook idempotency (duplicate delivery)
**Action:** Send the same `charge.success` webhook twice with the same reference
**Expected:**
- First webhook: contribution created, receipt sent
- Second webhook: no duplicate contribution created, no duplicate SMS sent
- Both webhooks return `200` (Paystack expects 200 regardless)

---

### Void / Correction

### TC-06-11 — Void a contribution
**Action:** `PATCH /api/v1/contributions/{id}/void` with `{ "reason": "Member requested reversal" }`
**Expected:**
- `is_voided: true`, `voided_at` timestamp set, `void_reason` saved
- `voided_by` set to the acting user's ID
- Contribution still exists (no deletion)
- Sunday offering report total EXCLUDES voided contributions
- Original contribution SMS receipt is NOT retracted (no "unsend")

### TC-06-12 — Cannot void an already-voided contribution
**Action:** Try to void a contribution that already has `is_voided: true`
**Expected:** `422 { "message": "This contribution has already been voided." }`

### TC-06-13 — Cannot delete a contribution
**Action:** `DELETE /api/v1/contributions/{id}`
**Expected:** `405 Method Not Allowed` — no DELETE endpoint exists for contributions

---

### Pledge Tracking

### TC-06-14 — Create a pledge
**Action:** `POST /api/v1/pledges` with:
```json
{
  "member_id": "{member_ulid}",
  "fund_type_id": "{building_fund_id}",
  "description": "Building Fund 2026 Pledge",
  "amount_pledged": 1000.00,
  "target_date": "2026-12-31"
}
```
**Expected:**
- Pledge created (`201`, `status: active`) with `amount_paid: "0.00"`, `amount_outstanding: "1000.00"`

### TC-06-15 — Pledge amount_paid updates when contribution is recorded
**Action:** Record a GHS 300 contribution for this member and fund type, with `pledge_id` set to the pledge (D-047: the gift names its pledge; nothing is matched by guesswork)
**Expected:**
- `pledges.amount_paid` = 300.00 (recalculated from the gifts in the same transaction)
- `amount_outstanding` = 700.00 (computed: amount_pledged - amount_paid)

### TC-06-16 — Pledge amount_paid reverts on contribution void
**Action:** Void the GHS 300 contribution from TC-06-15
**Expected:**
- `pledges.amount_paid` reverts to 0.00
- `amount_outstanding` returns to 1000.00

---

### Sunday Offering Report

### TC-06-17 — Sunday offering report accuracy
**Action:** Record: 3 tithe contributions (GHS 200, 150, 100), 2 offering contributions (GHS 50, 75), 1 building fund (GHS 500). Void the GHS 100 tithe. Get the Sunday offering report.
**Expected:**
```
Tithe:          GHS 350.00  (2 contributions — voided one excluded)
Offering:       GHS 125.00  (2 contributions)
Building Fund:  GHS 500.00  (1 contribution)
─────────────────────────────
Total:          GHS 975.00
```
Voided contribution does NOT appear in totals.

### TC-06-18 — Role restriction: Pastor sees summary only
**Action:** As a user with `giving.summary` but NOT `giving.view` (the pastor, D-046) → `GET /api/v1/contributions` and `GET /api/v1/pledges`
**Expected:** `403` on both list endpoints

**Action:** `GET /api/v1/services/{id}/offering-report`
**Expected:** `200` — totals only (no giver's name or id)

**Action:** `GET /api/v1/reports/giving` (Sprint 5)
**Expected:** `200` — summary data only (totals and trends, no individual records)

---

### TC-06-19 — Offering report page (browser)
**Action:** As a finance secretary, record a cash gift at today's service (Giving, Record a gift), open the service, then **Offering report**
**Expected:**
- The page shows the church, the service, the date, a table for the gifts at the service with a line for the fund, and the total for the day, all as the API wrote them
- A payment method nobody used has no column; "1 gift, 1 anonymous" is correct
- Voided gifts are not counted ("Voided gifts are not counted." is shown)
- In the print window (or with print media emulated) the app menu, the header and the buttons are gone and only the report is left
- At 375 px wide the table fits without the page scrolling sideways
- A pastor (giving.summary only) sees the same page; a department head gets "This report was not found." and no link

## Pass / Fail Criteria

TC-06-09 (webhook signature), TC-06-10 (idempotency), TC-06-11 (void), TC-06-13 (no deletion), and TC-06-17 (report accuracy) are **P0**. Any financial miscalculation or data integrity failure blocks the sprint.
