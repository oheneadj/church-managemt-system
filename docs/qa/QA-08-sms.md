# QA-08 — SMS / Africa's Talking

**Scope:** OTP delivery, contribution receipts, broadcast messages, credit tracking, low-credit alerts, message logs.
**Sprint:** 4 (receipts), Sprint 5 (broadcasts)
**Risk:** R3 (sender ID registration)

> Run all tests in the Africa's Talking **sandbox** environment. Never consume production credits during QA.

---

## Setup

- Africa's Talking sandbox account with sandbox API key
- Test phone numbers registered in the AT sandbox
- Test church with 50 SMS credits
- A finance secretary with `giving.record` permission
- A church admin with `sms.send` permission

---

## Test Cases

### OTP via SMS

### TC-08-01 — OTP SMS delivered
**Action:** Request an OTP for a registered phone number
**Expected:**
- SMS arrives on the test phone within 30 seconds
- Message format: "Your [AppName] code is: 382910. Valid for 10 minutes. Do not share."
- Sender ID: platform sender ID (or numeric shortcode if sender ID not approved yet)
- Credit NOT deducted from church balance (OTP is platform-funded)

### TC-08-02 — OTP SMS to invalid phone
**Action:** Request OTP for `+1234` (not a valid E.164 number)
**Expected:**
- `422` validation error before AT is called
- No AT API call made

---

### Contribution Receipts

### TC-08-03 — Cash contribution receipt delivered
**Action:** Record a cash contribution for a member with a registered phone
**Expected:**
- `SendSmsJob` dispatched to `notifications` queue
- SMS delivered within 60 seconds
- Message content:
  ```
  Grace Assembly: GHS 150.00 received (Tithe) on 25 Oct 2026.
  Your total giving in October: GHS 150.00.
  Thank you for your faithfulness.
  ```
- `message_logs` record created with `type: contribution_receipt`, `status: sent`
- 1 SMS credit deducted from church's balance
- `sms_credit_ledger` shows new row: `type: consumption`, `credits: -1`

### TC-08-04 — Receipt not sent to members without a phone
**Action:** Record a cash contribution for a member with no phone number on file
**Expected:**
- Contribution created successfully
- No `SendSmsJob` dispatched
- No error — silent skip

### TC-08-05 — Receipt content does not exceed 160 characters
**Action:** Check the receipt message length for a church with a long name (e.g. "International Central Gospel Church Accra Headquarters")
**Expected:**
- Message is ≤ 160 characters OR is correctly split into multi-part (each part costs 1 credit)
- Message contains no truncated placeholder text or broken characters

### TC-08-06 — Cash receipt can be disabled per church
**Action:** In church settings, disable "Send SMS receipt for cash contributions"
**Expected:**
- After disabling, cash contributions do NOT trigger a receipt SMS
- MoMo/card contributions still send receipts (from Paystack webhook)

---

### Broadcast Messages

### TC-08-07 — Send broadcast to all active members
**Action:** `POST /api/v1/messages/broadcast` (with an `Idempotency-Key`) with:
```json
{
  "message": "Service this Sunday starts at 9am. Bring a friend! - Pastor Emmanuel",
  "channel": "sms"
}
```
**Expected:**
- `202 { "queued": true, "broadcast_id": "...", "estimated_recipients": 48, "estimated_credit_cost": 48, "current_credit_balance": 50 }`; a `broadcasts` row records who sent what
- Credit check at the request: the church has enough credits for the recipients times the parts
- A worker queues one `SendSmsJob` for each member who is active, has a phone and has SMS on (a deceased member never); each has a `message_logs` row (`message_type: broadcast`, `broadcast_id`)
- Each recipient gets one SMS; running the worker twice sends nothing twice
- Credits are taken text by text just before each is sent (one for each part), and given back if a text fails for good; total = recipients x parts
- The same `Idempotency-Key` again sends nothing more

### TC-08-08 — Broadcast preview shows credit estimate
**Action:** On the broadcast compose page, enter a message and select recipients before sending (the page calls `POST /messages/broadcast/estimate`, D-065; nothing is sent or charged)
**Expected:**
- UI shows: "This message will be sent to 48 members and use approximately 48 SMS credits. You have 50 credits remaining."
- Count updates as message length changes (multi-part threshold at 160 chars)

### TC-08-09 — Broadcast blocked when credits insufficient
**Action:** Try to send a broadcast to 60 members when the church has 30 credits
**Expected:**
- `422 { "message": "Insufficient SMS credits. You need 60 credits, you have 30. Purchase more credits to proceed." }`
- Nothing is recorded or queued; no credits are taken
- Link to top-up flow shown in the error (the top-up arrives with story 5.4)

### TC-08-10 — Broadcast filtered by department
**Action:** Send broadcast to members in the "Youth" department only
**Expected:**
- Only youth department members receive the SMS
- Credit cost = youth member count only
- `message_logs` records show correct recipient count

---

### Credit Management

### TC-08-11 — Low-balance alert sent to admin
**Action:** Let a church's credit balance drop below 20
**Expected:**
- Daily credit check job fires
- Email sent to church admin: "Your SMS credits are running low (18 remaining). Purchase a top-up to keep sending messages."
- NOT an SMS (they may be out of credits; use email)

### TC-08-12 — Zero credits blocks outbound SMS
**Action:** Set a church's SMS credits to 0. Trigger a contribution receipt.
**Expected:**
- `SendSmsJob` is NOT dispatched
- Log warning: "SMS send blocked — insufficient credits" with church_id
- Admin notified via email
- No error is shown to the end user triggering the contribution

### TC-08-13 — SMS credit top-up via PayPerlez MoMo (D-069)
**Action:** As the church admin, buy a "Bundle 50" package: `POST /sms-credits/purchases` with a MoMo number and network, then approve the prompt on the phone
**Expected:**
- The call answers `202` with a `pending` purchase and no credits yet
- PayPerlez confirms the payment (checked every minute) and the purchase becomes `completed`
- Only the exact package price counts; a failed payment leaves the purchase `failed` and adds nothing
- Calling again with the same Idempotency-Key starts no second purchase
- `sms_credit_ledger` row created: `type: topup`, `credits: +50`
- `churches.sms_credits` increases by 50
- Admin sees updated balance in settings page

### TC-08-14 — Message log records delivery status
**Action:** After a successful send, check the message logs
**Expected:**
- `message_logs` row with `status: sent`, `sent_at` timestamp, `cost_units: 1`
- AT provider response stored (JSON)
- On delivery receipt (if AT provides one): `status: delivered`

### TC-08-15 — SMS delivery failure handled gracefully
**Action:** Simulate AT API failure (wrong credentials in sandbox)
**Expected:**
- `SendSmsJob` fails, retries 3 times with exponential backoff
- After 3 failures: `message_logs.status = 'failed'`
- `SendSmsJob::failed()` method updates the record
- Error logged — no unhandled exception propagates

---

### TC-08-16 — Credit balance in the header (browser)
**Action:** Sign in as a church admin, then as a finance secretary, and look at the header on any page. Set the church's credits to 1,250, then 50, then 51, then 0 and reload
**Expected:**
- "SMS credits" and the balance, written with thousands separators ("1,250"), are in the header for both roles
- At 1,250 and at 51 there is no warning; at 50 and under it says **Running low** in words; at 0 it says **None left** and that gift receipts and other texts are not sent
- Record a gift for a member with a phone and SMS on: the balance goes down by the receipt's credits within a minute, without a reload
- The church admin sees a **Top up** button that opens a notice saying buying credits is coming soon, with one **Got it** button; the finance secretary sees no Top up button
- At 375 px wide the header wraps and the page does not scroll sideways

## Pass / Fail Criteria

TC-08-09 (credit block), TC-08-12 (zero credits block), TC-08-15 (graceful failure) are **P1**. TC-08-03 (receipt content) is critical for the product's value proposition.
