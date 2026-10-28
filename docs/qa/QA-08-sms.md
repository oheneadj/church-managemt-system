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
  Thank you for your faithfulness! – Your Church Family
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
**Action:** `POST /api/communications/broadcast` with:
```json
{
  "message": "Service this Sunday starts at 9am. Bring a friend! – Pastor Emmanuel",
  "recipient_type": "all_active",
  "channel": "sms"
}
```
**Expected:**
- Credit check: church has enough credits for the recipient count
- `SendSmsJob` dispatched for each recipient (or batch job)
- Each recipient gets one SMS
- `message_logs` row created for each send
- Total credits deducted = number of recipients × parts per message

### TC-08-08 — Broadcast preview shows credit estimate
**Action:** On the broadcast compose page, enter a message and select recipients before sending
**Expected:**
- UI shows: "This message will be sent to 48 members and use approximately 48 SMS credits. You have 50 credits remaining."
- Count updates as message length changes (multi-part threshold at 160 chars)

### TC-08-09 — Broadcast blocked when credits insufficient
**Action:** Try to send a broadcast to 60 members when the church has 30 credits
**Expected:**
- `422 { "message": "Insufficient SMS credits. You need 60 credits, you have 30. Purchase more credits to proceed." }`
- Link to top-up flow shown in error

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

### TC-08-13 — SMS credit top-up via Paystack
**Action:** Purchase a "Bundle 50" SMS top-up via the Paystack payment flow
**Expected:**
- Paystack payment completes
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

## Pass / Fail Criteria

TC-08-09 (credit block), TC-08-12 (zero credits block), TC-08-15 (graceful failure) are **P1**. TC-08-03 (receipt content) is critical for the product's value proposition.
