# QA-06 — Giving & Contributions

**Scope:** Cash recording, MoMo payment initiation, receipt delivery, void/correction, pledge tracking, Sunday offering report.
**Sprint:** 4
**Risk:** R1 (MoMo rejection rate), financial integrity

---

## Setup

- Test church with Paystack subaccount configured (test environment)
- Finance secretary user with `giving.record` and `giving.edit` permissions
- Pastor user with `giving.summary` only
- 10 members with phone numbers
- Fund types: Tithe, Offering, Building Fund
- Africa's Talking sandbox for SMS receipts

---

## Test Cases

### Cash Recording

### TC-06-01 — Record a cash contribution
**Action:** `POST /api/contributions` with:
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
- Contribution created with ULID
- `payment_method: cash`, `status: completed`
- `paystack_reference` is null (no payment gateway involved)
- SMS receipt queued for delivery to member's phone

### TC-06-02 — Cash receipt SMS format
**Expected SMS content:**
```
Grace Assembly: GHS 150.00 received (Tithe) on 25 Oct 2026. 
Thank you for your faithfulness! – Your Church Family
```
- Amount formatted correctly (2 decimal places, GHS prefix)
- Church name in message (not platform name)
- No placeholder text `{{variable}}` visible

### TC-06-03 — Record contribution without member (anonymous)
**Action:** Submit contribution with no `member_id`
**Expected:**
- Contribution created with `member_id: null`, `is_anonymous: true`
- No SMS receipt attempted
- Appears in Sunday offering report as "Anonymous"

### TC-06-04 — Validation: Amount must be positive
**Action:** Submit `"amount": -50` or `"amount": 0`
**Expected:** `422` validation error on `amount`

### TC-06-05 — Validation: Amount must not exceed reasonable maximum
**Action:** Submit `"amount": 9999999.99`
**Expected:** `422` — amount exceeds maximum allowed (GHS 999,999.99)

---

### MoMo Payments

### TC-06-06 — Initiate MoMo payment — MTN
**Action:** `POST /api/contributions/momo` with:
```json
{
  "member_id": "{member_ulid}",
  "fund_type_id": "{offering_id}",
  "amount": 50.00,
  "phone": "+233244000001",
  "network": "mtn"
}
```
**Expected:**
- Paystack charge initiated
- `contribution` record created with `status: pending`, `paystack_reference: "ref_xyz"`
- Member receives a USSD MoMo prompt on their phone (test mode: simulated)
- Pre-charge SMS sent: "Grace Assembly: Tap OK on the upcoming MoMo prompt to give GHS 50.00 as Offering. Thank you!"

### TC-06-07 — MoMo payment completed (webhook: charge.success)
**Action:** Simulate `charge.success` webhook from Paystack for the reference in TC-06-06
**Expected:**
- Contribution `status` updated to `completed`
- `paid_at` timestamp set
- `ContributionReceived` event fired
- SMS receipt sent to member
- Webhook stored in `webhook_logs`

### TC-06-08 — MoMo payment failed (webhook: charge.failed)
**Action:** Simulate `charge.failed` webhook
**Expected:**
- Contribution `status` updated to `failed`
- No SMS receipt sent
- Admin dashboard shows the failed contribution (for manual cash fallback)

### TC-06-09 — Paystack webhook signature verification
**Action:** Send a webhook with an invalid HMAC-SHA512 signature
**Expected:** `401` — webhook is rejected without processing

### TC-06-10 — Webhook idempotency (duplicate delivery)
**Action:** Send the same `charge.success` webhook twice with the same reference
**Expected:**
- First webhook: contribution created, receipt sent
- Second webhook: no duplicate contribution created, no duplicate SMS sent
- Both webhooks return `200` (Paystack expects 200 regardless)

---

### Void / Correction

### TC-06-11 — Void a contribution
**Action:** `PATCH /api/contributions/{id}/void` with `{ "reason": "Member requested reversal" }`
**Expected:**
- `is_voided: true`, `voided_at` timestamp set, `void_reason` saved
- `voided_by_user_id` set to the acting user's ID
- Contribution still exists (no deletion)
- Sunday offering report total EXCLUDES voided contributions
- Original contribution SMS receipt is NOT retracted (no "unsend")

### TC-06-12 — Cannot void an already-voided contribution
**Action:** Try to void a contribution that already has `is_voided: true`
**Expected:** `422 { "message": "This contribution has already been voided." }`

### TC-06-13 — Cannot delete a contribution
**Action:** `DELETE /api/contributions/{id}`
**Expected:** `405 Method Not Allowed` — no DELETE endpoint exists for contributions

---

### Pledge Tracking

### TC-06-14 — Create a pledge
**Action:** `POST /api/pledges` with:
```json
{
  "member_id": "{member_ulid}",
  "fund_type_id": "{building_fund_id}",
  "pledged_amount": 1000.00,
  "due_date": "2026-12-31"
}
```
**Expected:**
- Pledge created with `amount_paid: 0`, `amount_outstanding: 1000.00`

### TC-06-15 — Pledge amount_paid updates when contribution is recorded
**Action:** Record a GHS 300 contribution against the same fund type for this member
**Expected:**
- `pledges.amount_paid` = 300.00 (updated by observer)
- `amount_outstanding` = 700.00 (computed: pledged_amount - amount_paid)

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
**Action:** As a user with `giving.summary` but NOT `giving.record` → `GET /api/contributions`
**Expected:** `403` on the full list endpoint

**Action:** `GET /api/reports/giving-trends`
**Expected:** `200` — summary data only (totals and trends, no individual records)

---

## Pass / Fail Criteria

TC-06-09 (webhook signature), TC-06-10 (idempotency), TC-06-11 (void), TC-06-13 (no deletion), and TC-06-17 (report accuracy) are **P0**. Any financial miscalculation or data integrity failure blocks the sprint.
