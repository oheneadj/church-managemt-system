# QA-07 — Paystack Integration

**Scope:** Subaccount creation, charge initiation, webhook reliability, signature verification, platform fee split, refund handling.
**Sprint:** 4
**Risk:** R10 (Paystack API changes), D-009 (Paystack decision)

> Run this plan in the Paystack **test environment** only. Never use live keys during QA.

---

## Setup

- Paystack test account with test secret key `sk_test_*`
- Test bank account details (from Paystack's test data)
- MTN MoMo test number: use Paystack's sandbox test numbers
- A test church without a subaccount (for TC-07-01)
- A test church with a valid subaccount (for payment tests)

---

## Test Cases

### Subaccount Setup

### TC-07-01 — Subaccount created during church onboarding
**Action:** Complete church onboarding with a test bank account
**Expected:**
- `POST /subaccount` called on Paystack API
- Response includes `subaccount_code: "ACCT_*"`
- `churches.paystack_subaccount_code` saved in DB
- `churches.onboarding_status` = `complete` (not `paystack_pending`)

### TC-07-02 — Giving is disabled when subaccount is missing
**Action:** Attempt to initiate a MoMo payment for a church with no subaccount code
**Expected:**
- `400 { "message": "Digital giving is not yet configured for this church. Please contact support." }`
- No charge initiated with Paystack

### TC-07-03 — Subaccount percentage split
**Action:** Initiate a GHS 100 test payment for a church with an 80% split (church gets 80%, platform keeps 20%)
**Expected:**
- Paystack charge is created with `subaccount: "ACCT_*"` and `percentage_charge: 80`
- On `charge.success`: church's Paystack balance receives GHS 80.00
- Platform account balance receives GHS 20.00
- (Verify in Paystack test dashboard — split settlement visible in transaction details)

---

### Charge Initiation

### TC-07-04 — Amount conversion (GHS to pesewas)
**Action:** Initiate a charge for GHS 200.50
**Expected:** Paystack API receives `"amount": 20050` (pesewas — multiply by 100)
**Anti-pattern:** `"amount": 200.50` or `"amount": 200` would be wrong

### TC-07-05 — Unique reference per charge
**Action:** Initiate two charges in quick succession
**Expected:** Each charge has a unique `reference` (UUID format). No two charges share a reference.

### TC-07-06 — Invalid phone number for MoMo
**Action:** Initiate MoMo with `"phone": "12345"` (not a valid Ghana number)
**Expected:** `422` validation error before Paystack is called

### TC-07-07 — Unsupported network
**Action:** Initiate MoMo with `"network": "vodacom"` (not a Ghana network)
**Expected:** `422 { "errors": { "network": ["Must be one of: mtn, telecel, airtel_tigo"] } }`

---

### Webhook Handling

### TC-07-08 — Valid charge.success webhook processed
**Action:** POST a `charge.success` webhook with a valid HMAC signature
**Expected:**
- Stored in `webhook_logs` (raw encrypted payload)
- `ProcessPaystackWebhookJob` dispatched to `payments` queue
- Job creates a contribution record
- Job fires `ContributionReceived` event
- Job returns without error

### TC-07-09 — Invalid signature rejected
**Action:** POST a `charge.success` webhook with signature `XXXXXXXXXX`
**Expected:**
- `401` returned immediately
- Nothing stored in `webhook_logs`
- No job dispatched

### TC-07-10 — Webhook idempotency — duplicate reference
**Action:** POST the same `charge.success` webhook twice (same `reference`)
**Expected:**
- First: contribution created, event fired
- Second: contribution NOT created (duplicate reference detected), no SMS sent
- Both return `200` to Paystack

### TC-07-11 — charge.failed webhook
**Action:** POST a `charge.failed` webhook
**Expected:**
- Contribution `status` updated to `failed`
- No `ContributionReceived` event fired
- No receipt SMS sent
- `webhook_logs` stores the raw payload

### TC-07-12 — refund.processed webhook
**Action:** POST a `refund.processed` webhook for an existing completed contribution
**Expected:**
- Contribution `is_voided: true`
- `void_reason: "Refund processed by Paystack"`
- `voided_by_user_id: null` (system void, not admin void)

### TC-07-13 — Unknown event type ignored
**Action:** POST a webhook with `"event": "invoice.create"` (not in our handled events list)
**Expected:**
- `200` returned (Paystack expects 200 for all webhooks)
- `Log::warning` recorded with event type
- No processing attempted

### TC-07-14 — Webhook controller returns 200 immediately
**Action:** POST any valid webhook and measure response time
**Expected:**
- Controller returns `200` in < 100ms
- Processing happens asynchronously in the queue
- If queue is down, webhook still returns 200 (payload stored; can be reprocessed)

---

### Error Scenarios

### TC-07-15 — Paystack API down during charge initiation
**Action:** Simulate Paystack API unavailability (use a wrong endpoint URL in test)
**Expected:**
- Error caught gracefully
- `500` not returned to user
- `422` or `503` with message: "Payment service temporarily unavailable. Please try again."
- No partial contribution record created

### TC-07-16 — Subaccount creation fails during onboarding
**Action:** Simulate Paystack API error during subaccount creation (wrong bank code)
**Expected:**
- Church `onboarding_status = 'paystack_pending'`
- Admin sees clear message: "Bank account couldn't be verified. Please check your details and try again."
- Church can still use the platform for manual/cash recording
- Retry mechanism: admin can re-attempt subaccount setup from settings

---

## Pass / Fail Criteria

TC-07-09 (signature rejection) and TC-07-10 (idempotency) are **P0**. TC-07-04 (amount conversion) is **P0** — wrong amount sent to Paystack means wrong charge to members. All others are P1.
