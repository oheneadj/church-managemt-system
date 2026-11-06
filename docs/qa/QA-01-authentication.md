# QA-01 — Authentication

**Scope:** Staff sign-in with email, password and an emailed code (D-054); forgotten password; lockouts; token expiry; logout; account enumeration prevention.
**Sprint:** 1 (foundation auth scaffold), rewritten for D-054
**Risk:** R9 (data breach), D-012 (OTP-only auth decision, superseded for staff by D-054)

> **Source of truth (D-054, D-062).** The expected results come from `docs/08-api.md` (Auth Endpoints), D-054 and `config/otp.php` / `config/limits.php`. All paths are under `/api/v1` on a church's own host. Members, the QR check-in and the WhatsApp check-in still use phone and SMS codes with no passwords; the code rules here (hashed, 10 minutes, 5 tries, 15-minute lockout) are the same for both. The QR check-in is tested in QA-05 and the sign-up codes in QA-03.

---

## Setup

- A test church with slug `testchurch` exists on staging
- A staff user `akosua@example.com` with a password (`a long passphrase here`), role `church-admin`, active
- A second, deactivated staff user `esi@example.com` with a password
- A third staff user with no password yet
- Mail goes to a catch-all inbox, or `MAIL_MAILER=log` locally (the code is the six digits on their own line of the email)
- The `critical` queue worker is running; Redis accessible and empty for test keys

---

## Test Cases

### TC-01-01 — Step 1: the right password emails a code
**Action:** `POST /auth/login` with `{ "email": "akosua@example.com", "password": "a long passphrase here" }`
**Expected:**
- `200 { "message": "Code sent", "expires_in": 600 }` and nothing else (no user details)
- An email arrives within 30 seconds, from the platform, naming the church, with a 6-digit code and "expires in 10 minutes"; the plain-text part carries the same code
- The job ran on the `critical` queue
- A Redis key `church:<church id>:email-code:sign_in:<sha256 of the email>` holds a bcrypt hash (never the code), expiring in ≤ 600 seconds
- `message_logs` records the email with a summary that does not contain the code

### TC-01-02 — Step 2: the right code signs in
**Action:** `POST /auth/login/verify` with `{ "email": "akosua@example.com", "code": "<emailed code>" }`
**Expected:**
- From the web app (a browser request on the church's host): `200 { "user": {...}, "church": {...} }` with **no token**, and an httpOnly `laravel-session` cookie with no `Domain` attribute
- From any other client: `200 { "token": "...", "token_type": "Bearer", "user": {...}, "church": {...} }`
- The Redis code key and attempts key are deleted after a successful verify
- The user's `email_verified_at` is set (first time only); `personal_access_tokens` has a new row for a non-browser client

### TC-01-03 — One message for every wrong password
**Action:** Step 1 with (a) a wrong password for `akosua@example.com`, (b) an email that has no account, (c) the staff user with no password.
**Expected:** all three: `422 { "message": "Invalid email or password" }`; no email is sent; the response time is similar for all three (no timing difference).

### TC-01-04 — A wrong or expired code
**Action:** Verify with an incorrect code, and (separately) with the right code 11 minutes after it was sent.
**Expected:** both: `422 { "message": "Invalid code" }`. The message is the same for wrong, expired, used, another email's and another church's codes. The attempts counter for that email and church goes up.

### TC-01-05 — Lockout after 5 wrong passwords
**Action:** Submit 5 wrong passwords for the same email.
**Expected:**
- 1st to 4th: `422`. 5th: `429 { "message": "Too many attempts. Try again in 15 minutes." }` with `Retry-After: 900`
- A 6th request within 15 minutes, even with the right password, is also `429` and sends no email
- Another email in the same church is not affected
- After 15 minutes, or `php artisan staff:reset-lockout testchurch akosua@example.com`, the right password works

### TC-01-06 — Lockout after 5 wrong codes
**Action:** Request a code, then submit 5 wrong codes.
**Expected:** the 5th is `429` with `Retry-After: 900`; the right code is `429` too until the lockout ends or `staff:reset-lockout` is run (which also drops the pending code, so ask for a new one).

### TC-01-07 — Emailed codes are limited, and a mistyped password does not use the limit up
**Action:** Ask for codes repeatedly with the right password; separately, send many wrong passwords.
**Expected:** a second code within a minute is `429`; a sixth in an hour is `429`; more than 20 an hour from one IP is `429`, each with `Retry-After`. Wrong passwords never count against these (only against the lockout in TC-01-05).

### TC-01-08 — A deactivated account
**Action:** Step 1 for `esi@example.com` with (a) the right password, (b) a wrong password.
**Expected:** (a) `403 { "message": "Your account has been deactivated. Contact your church admin." }` and no email; (b) the generic `422` of TC-01-03, so nobody learns an account exists without the password.

### TC-01-09 — A code is single-use
**Action:** Sign in successfully, then verify again with the same code.
**Expected:** the second verify is `422 "Invalid code"`.

### TC-01-10 — A code works only for its own email, church and purpose
**Action:** Use a sign-in code for another email; a code from church B on church A's host; a password reset code on the sign-in step.
**Expected:** each is `422 "Invalid code"`.

### TC-01-11 — Forgot password gives the same answer for everyone
**Action:** `POST /auth/password/forgot` for (a) an active staff email, (b) an unknown email, (c) a deactivated staff email.
**Expected:** all three: `200 { "message": "If that email belongs to a staff member, a code has been sent.", "expires_in": 600 }`; an email is sent only for (a); the same limits on emailed codes apply to all three, so a `429` reveals nothing.

### TC-01-12 — Choose a new password
**Action:** `POST /auth/password/reset` with the emailed code, a new password and its confirmation.
**Expected:**
- `204` with no body; the person is **not** signed in
- Every earlier session and token of that user stops working at once; the old password no longer works and the new one does
- A password shorter than 12 characters, longer than 72 bytes, equal to the email, or not matching the confirmation is `422` on `password` and does **not** use the code up
- A wrong code is `422 "Invalid code"`; five wrong codes lock it for 15 minutes (`429`)
- Within a few minutes, if the password is in the known-breach list, an email tells the person to choose another (the check never delays or blocks the request); if the list is unreachable nothing happens to the account
- The same flow sets the first password of a staff member who has none

### TC-01-13 — Authenticated endpoint without token
**Action:** `GET /members` with no `Authorization` header
**Expected:** `401 { "message": "Unauthenticated." }`

### TC-01-14 — Authenticated endpoint with invalid token
**Action:** `GET /members` with `Authorization: Bearer invalidtoken123`
**Expected:** `401 { "message": "Unauthenticated." }`

### TC-01-15 — Token expiry
**Action:** Use a token that was created more than 8 hours ago
**Expected:** `401 { "message": "Unauthenticated." }`
**Setup:** Set `expires_at` on the token record in the DB to a past time (tokens are created with an 8-hour `expires_at`)

### TC-01-16 — Logout
**Action:** `POST /auth/logout` with a valid token
**Expected:**
- Response: `204` with an empty body (as documented in `08-api.md`)
- Token is deleted from `personal_access_tokens`
- A subsequent request with the same token returns `401`

### TC-01-17 — Session survives a page refresh
**Action:** Sign in through the web app, then reload the page (any private URL).
**Expected:** The user is still signed in and sees the same church; no login screen appears. `GET /auth/me` returns the user and church.

### TC-01-18 — Session cannot cross to another church
**Action:** Copy the `laravel-session` cookie value from church A and send it to church B's host.
**Expected:** `401`. (This was once a real leak; a test fails if it returns).

### TC-01-19 — Access changes end the session
**Action:** While signed in, from another session: (a) deactivate the user, (b) change their email, (c) change their password. Separately: change only their phone number.
**Expected:** (a), (b) and (c): the next request returns `401`. Changing only the phone number does not sign anyone out (the phone is a contact number, D-054).

### TC-01-20 — Logout ends the cookie session
**Action:** Sign out, then reload a private URL or replay the old cookie.
**Expected:** Login screen / `401`.

### TC-01-21 — The sign-in screens
**Action:** In a browser on the church's address: open `/login`, enter a wrong password, then the right one and the emailed code; open "Forgot your password?" and choose a new password.
**Expected:** a wrong password shows "Invalid email or password" beside the form and no code box; after the right password the page says where the code was sent and the password box is gone; a wrong code shows "Invalid code"; the forgot page moves to "Choose a new password" for any email, without saying if the account exists; password rules are shown before anything is sent. Every box has a visible label and every error is announced to a screen reader.

### TC-01-22 — Member portal authentication *(not built)*
**Action:** A member (not staff) logs in via the member portal OTP flow
**Expected:**
- Successfully receives OTP by SMS, verifies, gets token
- Token gives access to member portal endpoints only (own profile, own giving history)
- Token does NOT give access to admin endpoints (`/members`, `/contributions` list, etc.)

---

## Pass / Fail Criteria

All 22 test cases must pass (TC-01-22 when the member portal is built). Any failure in TC-01-03, TC-01-04, TC-01-05, TC-01-08, TC-01-09, TC-01-10, TC-01-11 or TC-01-18 is a **P0 blocker** (security vulnerability).
