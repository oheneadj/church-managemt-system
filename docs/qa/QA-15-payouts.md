# QA-15 — Automatic Payouts and Platform Fee

**Scope:** The platform fee calculation, the automatic payout of each MoMo gift to the church (D-018), retries, the minimum balance rule, and the limits on digital gifts.
**Sprint:** 4 (digital slice, after the legal opinion and PayPerlez's event names, D-046)
**Risk:** Financial integrity, R1 (MoMo rejection), the platform holding money for seconds

> **Not built yet.** These cases describe the design in D-018, D-020 and D-053 and are run when the digital slice exists. Run them against PayPerlez's **test** environment only. Money values are exact decimal strings in GHS (D-017); no case may use a float.

---

## Setup

- Test church with a verified payout account (`payout_verified_at` set, `payout_method`, `payout_network`, `payout_account_number`)
- A second test church with no payout account
- PayPerlez test keys; the `log` SMS driver; a worker on the `payments` queue
- Platform config: `giving.platform_fee_rate` 1%, `giving.platform_fee_cap` 10.00, `giving.platform_fee_floor` 0.10, `giving.min_digital_gift` 1.00, maximum per digital gift 30,000.00 (D-018)

---

## Test Cases

### Platform fee

The fee is `clamp(round(gross × rate, 2), floor, cap)`, where `gross` is the gift amount.

### TC-15-01 — Fee is the percentage in the middle range
**Action:** A completed GHS 100.00 MoMo gift
**Expected:** `platform_fee` = `1.00`, `requested_amount` = `99.00`

### TC-15-02 — Fee floor
**Action:** A completed GHS 1.00 MoMo gift (the minimum)
**Expected:** `platform_fee` = `0.10` (1% would be 0.01), `requested_amount` = `0.90`

### TC-15-03 — Fee cap
**Action:** A completed GHS 5,000.00 MoMo gift
**Expected:** `platform_fee` = `10.00` (1% would be 50.00), `requested_amount` = `4,990.00`

### TC-15-04 — Rounding
**Action:** Completed gifts of GHS 33.33, 12.50 and 250.55
**Expected:** `platform_fee` = `0.33`, `0.13` (0.125 rounds half up), `2.51`; each computed with exact decimal arithmetic, never floats; the same rule gives the same answer on every run. **Open point:** D-018 says "round to 2 places" without naming the mode; this plan assumes round half up. Confirm it and record it in D-018 when the fee code is written, then set it in one place only (agreement section 12)

### TC-15-05 — Rate, cap and floor come from config
**Action:** Change the three config values and complete a gift
**Expected:** the new values are used; none is written in code (agreement section 3b)

---

### Limits on digital gifts

### TC-15-06 — Below the minimum
**Action:** `POST /contributions/initiate` with `amount: 0.99`
**Expected:** `422` on `amount`; no row created, nothing sent to PayPerlez

### TC-15-07 — Above the maximum
**Action:** `POST /contributions/initiate` with `amount: 30000.01`, then `30000.00`
**Expected:** the first is `422`; the second is accepted. (A cash gift still allows up to 999,999.99.)

### TC-15-08 — Church without a verified payout account
**Action:** `POST /contributions/initiate` for the second test church
**Expected:** `400 { "message": "Digital giving is not yet configured for this church. Please contact support." }`; no row, no provider call

---

### The payout

### TC-15-09 — Payout is sent when a gift completes
**Action:** Complete a GHS 100.00 MoMo gift (QA-06 TC-06-07)
**Expected:**
- One `disbursements` row: `gross_amount` 100.00, `platform_fee` 1.00, `requested_amount` 99.00, `status` `pending` then `processing`, `provider` `payperlez`
- PayPerlez receives a disbursement for `99.00` to the church's account with our own client reference and idempotency key
- When confirmed: `status` `paid`, `provider_fee` and `net_amount` (`requested_amount − provider_fee`) filled in, `paid_at` set
- The church bears the payout fee; the platform fee is the only amount we keep

### TC-15-10 — The church receives the full gift less the two fees
**Action:** Complete a GHS 100.00 gift where PayPerlez reports a `provider_fee` of 0.50
**Expected:** `net_amount` = `98.50`. The giver paid the processing fee on top (`processing_fee`, `total_paid`), and it is not deducted from the church (D-018).

### TC-15-11 — One payout per gift
**Action:** Deliver the success event for the same gift twice, and run the polling job at the same time
**Expected:** exactly one `disbursements` row (unique on `contribution_id`) and one request to PayPerlez; the second attempt is a no-op

### TC-15-12 — No payout before the gift completes
**Action:** Leave a gift `pending`, then mark it `failed`
**Expected:** no `disbursements` row for either (D-053)

### TC-15-13 — Payout fails, then retries
**Action:** Make PayPerlez answer with a temporary error (server error, timeout) for the first two attempts, then success
**Expected:** `attempts` goes 1, 2, 3; the gift is not paid out twice (same idempotency key); the final status is `paid`; a refusal that retrying cannot fix (invalid account) sets `failed` at once with `failure_reason`

### TC-15-14 — Payout fails for good
**Action:** Exhaust the retries
**Expected:** `status` `failed`; the job's `failed()` handler raises an alert linked to the runbook; the finance secretary sees the stuck payout (`(church_id, status)` view); the money is not lost track of

### TC-15-15 — Minimum operating balance
**Action:** With the provider balance at 5.00, request a payout of 4.50 (leaves 0.50), then of 4.00 (leaves 1.00)
**Expected:** the first waits and is retried later (it is sent only when `available_balance − requested_amount ≥ 1.00`); the second is sent. No payout is marked `failed` just for waiting.

### TC-15-16 — Payout retries survive a restart
**Action:** Stop the worker with a payout `processing`, restart it
**Expected:** the polling job finds the payout through PayPerlez's status endpoint and settles the row; nothing is sent twice

---

### Reconciliation and records

### TC-15-17 — Daily reconciliation
**Action:** Run the reconciliation job after a day of mixed gifts
**Expected:** total collected = total disbursed + platform fees + payouts still in flight; any difference raises an alert. Voided, pending and failed gifts are not in the total.

### TC-15-18 — No refunds or clawback after payout
**Action:** Void a gift whose payout is `paid`
**Expected:** the void is recorded and the gift leaves the reports, but no money is requested back from the church and no refund is sent to the giver (D-018); the audit line says so

### TC-15-19 — Every provider call is logged
**Action:** Complete one gift
**Expected:** the collection, status checks and the payout each have a row with request, response, status code and duration in the third-party call log (agreement section 17), with sensitive fields encrypted and no phone number or amount in the file log

### TC-15-20 — Tenant isolation
**Action:** Church B's admin and finance secretary request Church A's disbursement and gift
**Expected:** `404`; a payout job for Church A never reads or sends Church B's gifts

---

## Pass / Fail Criteria

TC-15-01 to TC-15-04 (fee arithmetic), TC-15-09, TC-15-10, TC-15-11 (one payout per gift) and TC-15-17 (reconciliation) are **P0**: a wrong fee or a double payout is a direct financial loss. Everything else is P1.
