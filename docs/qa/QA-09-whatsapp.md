# QA-09 — WhatsApp / Meta Business API

**Scope:** Webhook verification, CHECKIN command flow, template messages, opt-in, broadcast via WhatsApp.
**Sprint:** 3 (CHECKIN), Sprint 5 (broadcasts)
**Risk:** R2 (Meta approval delays)

> If Meta Business API is not approved by Sprint 3, all tests are run against a mock webhook endpoint. SMS fallback is tested in QA-08.

---

## Setup

- Meta Business API account verified and approved (or test against a mock)
- Platform WhatsApp number configured in Meta dashboard
- Webhook URL registered: `POST /api/webhooks/whatsapp`
- Verify token set in `.env`
- 5 test members with WhatsApp numbers who have opted in (previously messaged the platform number)
- CHECKIN confirmation and contribution receipt templates approved in Meta Business Manager

---

## Test Cases

### Webhook Setup

### TC-09-01 — Webhook verification handshake
**Action:** Meta sends a GET to `/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=YOUR_TOKEN&hub.challenge=12345`
**Expected:**
- Response: `200 "12345"` — the challenge value echoed back
- Nothing else happens

### TC-09-02 — Webhook verification with wrong token
**Action:** Same GET request but with a different `hub.verify_token`
**Expected:** `403 Forbidden`

---

### CHECKIN Flow

### TC-09-03 — CHECKIN command — happy path
**Action:** Send "CHECKIN" from a member's WhatsApp number to the platform number while a service is open
**Expected:**
- Webhook fires within 5 seconds
- Raw payload stored in `webhook_logs` (encrypted)
- `ProcessWhatsAppWebhookJob` dispatched to `notifications` queue
- Attendance record created: `method: whatsapp`, `checked_in_at` set
- Confirmation reply sent: "✅ Checked in to Sunday Morning Service — 25 Oct 2026. Welcome, Emmanuel! God bless you. 🙏"

### TC-09-04 — CHECKIN command — case insensitive
**Action:** Send "checkin" (lowercase) and "Checkin" (mixed case)
**Expected:** Both trigger the same flow as TC-09-03

### TC-09-05 — CHECKIN with service code
**Action:** Send "CHECKIN ABC123" where ABC123 is the service's checkin_code
**Expected:**
- Check-in to the specific service, not just the first open service
- Useful when two services are open simultaneously

### TC-09-06 — CHECKIN — unknown phone
**Action:** Send "CHECKIN" from a number not registered to any member
**Expected:** Reply: "We couldn't find your number in our records. Please contact the church office."
**Expected:** No attendance record created. No error in logs.

### TC-09-07 — CHECKIN — no open service
**Action:** Send "CHECKIN" when no service is open
**Expected:** Reply: "No service is currently open for check-in. Please contact the church admin."

### TC-09-08 — CHECKIN — duplicate (idempotency)
**Action:** Send "CHECKIN" twice from the same number for the same service
**Expected:**
- First: attendance record created, confirmation sent
- Second: no duplicate record created (database unique constraint catches it)
- Second reply: friendly confirmation ("You're already checked in!")

### TC-09-09 — Non-CHECKIN message is ignored
**Action:** Send "Hello" or "What time is service?" to the platform number
**Expected:**
- Message received by webhook
- No reply sent (or a static "This is an automated number" reply, if configured)
- No errors in logs

### TC-09-10 — Webhook returns 200 immediately
**Action:** Send a CHECKIN message and measure webhook response time
**Expected:**
- Webhook controller returns `200` within 100ms
- Processing (attendance record, reply) happens in the queue
- Meta's retry logic is not triggered (Meta retries if it doesn't get 200 within 20 seconds)

---

### Template Messages

### TC-09-11 — Contribution receipt via WhatsApp template
**Action:** Record a MoMo contribution for an opted-in member
**Expected:**
- WhatsApp template message `contribution_receipt` sent (not a free-form text message)
- Template variables populated correctly:
  - `{{1}}` = member's first name
  - `{{2}}` = GHS amount
  - `{{3}}` = fund type name
  - `{{4}}` = church name
  - `{{5}}` = month-to-date total

### TC-09-12 — Template sent only to opted-in members
**Action:** Record a contribution for a member who has NOT opted in to WhatsApp messages
**Expected:**
- No WhatsApp message sent
- Fallback to SMS receipt (if member has phone number)

### TC-09-13 — Session message vs template message
**Action:** A member sends "CHECKIN" (session opens). Within 24 hours, send a follow-up message.
**Expected:** Free-form reply is allowed (within 24-hour session window)

**Action:** 25 hours after the last inbound message, send a message to this member
**Expected:** Template message used (not free-form) — or message deferred until next session

### TC-09-14 — WhatsApp broadcast to opted-in members
**Action:** Send a broadcast via WhatsApp channel to all opted-in members
**Expected:**
- Template message `broadcast_message` used
- Only opted-in members receive the message
- `message_logs` records created with `channel: whatsapp`

---

### Opt-In Management

### TC-09-15 — Opt-in recorded when member messages the platform
**Action:** A member sends any message to the platform WhatsApp number (including CHECKIN)
**Expected:**
- `members.whatsapp_opted_in = true` set for this member (if not already)
- `members.whatsapp_opted_in_at` timestamp recorded

### TC-09-16 — Opt-out via STOP command
**Action:** Member sends "STOP" to the platform WhatsApp number
**Expected:**
- `members.whatsapp_opted_in = false`
- No further WhatsApp messages sent to this member
- CHECKIN still works (check-in is a service, not marketing)

---

## Pass / Fail Criteria

TC-09-01 and TC-09-02 (webhook verification) must pass before Sprint 3 can begin. TC-09-08 (idempotency) is **P0**. TC-09-10 (200 immediately) is **P1** — Meta will flood us with retries if we're slow.

**If Meta is not approved:** Stub the webhook handler and test TC-09-03 through TC-09-10 with a local POST to the webhook endpoint.
