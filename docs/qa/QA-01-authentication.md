# QA-01 — Authentication

**Scope:** OTP request and verification, lockout, token expiry, logout, phone number enumeration prevention.
**Sprint:** 1 (foundation auth scaffold)
**Risk:** R9 (data breach), D-012 (OTP-only auth decision)

---

## Setup

- A test church with slug `testchurch` exists on staging
- A registered staff user with phone `+233244000001`
- A member with phone `+233244000002`
- Redis accessible and empty for test keys

---

## Test Cases

### TC-01-01 — Successful OTP request
**Action:** `POST /api/auth/otp/request` with `{ "phone": "+233244000001" }`
**Expected:**
- Response: `200 { "message": "OTP sent" }`
- SMS arrives on the phone within 30 seconds
- OTP is 6 digits
- Redis key `otp:+233244000001` exists with a hashed value
- Redis key expires in ≤ 600 seconds

### TC-01-02 — Successful OTP verification
**Action:** `POST /api/auth/otp/verify` with `{ "phone": "+233244000001", "otp": "<received_code>" }`
**Expected:**
- Response: `200 { "token": "...", "user": { ... } }`
- Token is a 40-character string
- Redis key `otp:+233244000001` is deleted after successful verify
- `personal_access_tokens` table has a new row for this user

### TC-01-03 — Wrong OTP
**Action:** Submit verify with an incorrect OTP code
**Expected:**
- Response: `422 { "message": "Invalid code" }`
- Error message is the SAME as for an expired code (no distinguishing)
- Redis key `otp_attempts:+233244000001` is incremented

### TC-01-04 — Expired OTP
**Action:** Wait 11 minutes after requesting OTP, then verify with the correct code
**Expected:**
- Response: `422 { "message": "Invalid code" }`
- Same error message as wrong OTP

### TC-01-05 — Lockout after 5 failed attempts
**Action:** Submit 5 incorrect OTP verifications in a row
**Expected:**
- 5th attempt response: `429 { "message": "Too many attempts. Try again in 15 minutes." }`
- A 6th request within 15 minutes also returns `429`
- After 15 minutes, a new OTP can be requested and verified

### TC-01-06 — Phone number enumeration prevention
**Action:** Request OTP for a phone that is NOT registered: `+233244999999`
**Expected:**
- Response: `200 { "message": "OTP sent" }` — same as a valid phone
- No SMS is actually sent (or a "no account" SMS is sent — either way, no different response to the API caller)
- The response time should be similar to a valid phone (no timing attack)

### TC-01-07 — Authenticated endpoint without token
**Action:** `GET /api/members` with no `Authorization` header
**Expected:** `401 { "message": "Unauthenticated." }`

### TC-01-08 — Authenticated endpoint with invalid token
**Action:** `GET /api/members` with `Authorization: Bearer invalidtoken123`
**Expected:** `401 { "message": "Unauthenticated." }`

### TC-01-09 — Token expiry
**Action:** Use a token that was created more than 8 hours ago
**Expected:** `401 { "message": "Token expired." }`
**Setup:** Manually set `created_at` on the token record in DB to 9 hours ago

### TC-01-10 — Logout
**Action:** `POST /api/auth/logout` with a valid token
**Expected:**
- Response: `200 { "message": "Logged out" }`
- Token is deleted from `personal_access_tokens`
- A subsequent request with the same token returns `401`

### TC-01-11 — OTP is single-use
**Action:** Verify successfully with a valid OTP. Then immediately try to verify again with the same OTP.
**Expected:** Second verify returns `422` — OTP is consumed on first use.

### TC-01-12 — Concurrent OTP requests
**Action:** Request OTP twice in quick succession for the same phone number
**Expected:**
- Both requests return `200`
- Only the most recent OTP is valid (earlier one is overwritten in Redis)
- Rate limit on the OTP endpoint prevents abuse (3 requests per minute)

### TC-01-13 — Member portal authentication
**Action:** A member (not staff) logs in via the member portal OTP flow
**Expected:**
- Successfully receives OTP, verifies, gets token
- Token gives access to member portal endpoints only (own profile, own giving history)
- Token does NOT give access to admin endpoints (`/api/members`, `/api/contributions` list, etc.)

---

## Pass / Fail Criteria

All 13 test cases must pass. Any failure in TC-01-04, TC-01-05, TC-01-06, or TC-01-11 is a **P0 blocker** (security vulnerability).
