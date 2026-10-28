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
- The job is queued on the `critical` queue and the SMS reads "Your <app name> code is: NNNNNN. Valid for 10 minutes. Do not share."
- A Redis key `church:<church id>:otp:<sha256 of the phone>` exists holding a bcrypt hash (never the code)
- The key expires in ≤ 600 seconds
- `message_logs` records the send with the content `[otp redacted]`

### TC-01-02 — Successful OTP verification
**Action:** `POST /api/auth/otp/verify` with `{ "phone": "+233244000001", "otp": "<received_code>" }`
**Expected:**
- From the web app (browser request on the church's host): `200 { "user": {...}, "church": {...} }` with **no token**, and an httpOnly `laravel-session` cookie with no `Domain` attribute
- From any other client: `200 { "token": "...", "token_type": "Bearer", "user": {...}, "church": {...} }`
- The Redis code key and attempts key are deleted after successful verify
- `personal_access_tokens` table has a new row for this user

### TC-01-03 — Wrong OTP
**Action:** Submit verify with an incorrect OTP code
**Expected:**
- Response: `422 { "message": "Invalid code" }`
- Error message is the SAME as for an expired code (no distinguishing)
- The attempts counter for that church and phone is incremented

### TC-01-04 — Expired OTP
**Action:** Wait 11 minutes after requesting OTP, then verify with the correct code
**Expected:**
- Response: `422 { "message": "Invalid code" }`
- Same error message as wrong OTP

### TC-01-05 — Lockout after 5 failed attempts
**Action:** Submit 5 incorrect OTP verifications in a row
**Expected:**
- 4th wrong attempt: `422`. 5th wrong attempt: `429 { "message": "Too many attempts. Try again in 15 minutes." }` with `Retry-After: 900`
- A 6th request within 15 minutes also returns `429`, even with the correct code
- After 15 minutes (or `php artisan otp:reset-lockout`), a new OTP can be requested and verified

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
**Expected:** `401 { "message": "Unauthenticated." }`
**Setup:** Set `expires_at` on the token record in the DB to a past time (tokens are created with an 8-hour `expires_at`)

### TC-01-10 — Logout
**Action:** `POST /api/auth/logout` with a valid token
**Expected:**
- Response: `204` with an empty body (as documented in `08-api.md`)
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
- The second request within a minute returns `429`: the limit is 1 per minute and 5 per hour per phone (D-025), so "both return 200" only applies to requests more than a minute apart

### TC-01-14 — Session survives a page refresh
**Action:** Sign in through the web app, then reload the page (any private URL).
**Expected:** The user is still signed in and sees the same church; no login screen appears. `GET /api/v1/auth/me` returns the user and church.

### TC-01-15 — Session cannot cross to another church
**Action:** Copy the `laravel-session` cookie value from church A and send it to church B's host.
**Expected:** `401`. (This was once a real leak; a test fails if it returns).

### TC-01-16 — Access changes end the session
**Action:** While signed in, deactivate the user (or change their phone number) from another session.
**Expected:** The next request returns `401`.

### TC-01-17 — Logout ends the cookie session
**Action:** Sign out, then reload a private URL or replay the old cookie.
**Expected:** Login screen / `401`.

### TC-01-13 — Member portal authentication
**Action:** A member (not staff) logs in via the member portal OTP flow
**Expected:**
- Successfully receives OTP, verifies, gets token
- Token gives access to member portal endpoints only (own profile, own giving history)
- Token does NOT give access to admin endpoints (`/api/members`, `/api/contributions` list, etc.)

---

## Pass / Fail Criteria

All 13 test cases must pass. Any failure in TC-01-04, TC-01-05, TC-01-06, or TC-01-11 is a **P0 blocker** (security vulnerability).
