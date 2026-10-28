# QA-12 — Security Controls

**Scope:** Input validation, auth controls, rate limiting, field encryption, SQL injection, IDOR, cross-tenant data leakage, security headers.
**Sprint:** Re-run every sprint. Full suite before any production deploy.
**Risk:** Highest priority plan — failures here are public incidents.

> Security test failures are not defects — they are vulnerabilities. Treat them as P0 regardless of how minor they look in isolation.

---

## Setup

- Two independent test churches with known data (Church A and Church B)
- Test users for each church across all roles
- An attacker user with a valid token for Church B attempting to reach Church A data
- Raw HTTP client (Postman or curl) to bypass any client-side constraints
- Browser DevTools to inspect response headers

---

## Test Cases

### Authentication & Token Security

### TC-12-01 — Expired token rejected
**Action:** Use a Sanctum token that is older than the configured expiry (e.g. 30 days)
**Expected:** `401 { "message": "Unauthenticated." }`

### TC-12-02 — Revoked token rejected
**Action:**
1. Log in and save the token
2. `POST /api/auth/logout` (revokes token)
3. Use the saved token on any protected endpoint
**Expected:** `401` — revoked tokens are invalid immediately, not just expired

### TC-12-03 — Token does not contain sensitive data
**Action:** Base64-decode the Sanctum token (it is an opaque token, not a JWT)
**Expected:** The token is an opaque random string — no church_id, user_id, or permissions embedded in it

### TC-12-04 — OTP brute force lockout
**Action:** Submit 5 incorrect OTPs for the same phone number in sequence
**Expected:**
- After the 5th failure: `429 { "message": "Too many attempts. Try again in 15 minutes." }`
- Further attempts for this phone (from any IP) return `429` for 15 minutes
- After 15 minutes: fresh OTP can be requested

### TC-12-05 — OTP cannot be reused
**Action:**
1. Request OTP, receive code `382910`
2. Verify with `382910` (success — token issued)
3. Verify with `382910` again (same code)
**Expected:** Second verification fails — OTP was deleted from Redis on first use

### TC-12-06 — OTP rate limit
**Action:** Request OTP for the same phone 4 times in under 1 minute
**Expected:** After 3 requests, the 4th returns `429 { "message": "Please wait before requesting another OTP." }`

---

### Multi-Tenancy / IDOR

### TC-12-07 — Cannot access another church's member by ULID
**Action:** Church B's admin takes a known member ULID from Church A (via out-of-band knowledge) and calls `GET /api/members/{church_a_member_ulid}`
**Expected:**
- `404 Not Found`
- Response body: `{ "message": "Not found." }`
- NOT `403` (which would reveal the resource exists)
- No data from Church A in the response

### TC-12-08 — Cannot update another church's member
**Action:** Church B's admin sends `PATCH /api/members/{church_a_member_ulid}` with valid update data
**Expected:** `404` — same reasoning as TC-12-07

### TC-12-09 — Cannot list another church's contributions
**Action:** Church B's finance secretary calls `GET /api/contributions`
**Expected:** Response contains only Church B's contributions — Church A's data is absent even if ULIDs are guessed correctly

### TC-12-10 — church_id cannot be injected via request body
**Action:** Send `POST /api/members` with an extra field `"church_id": "<church_a_ulid>"` in the body
**Expected:**
- Member is created under the authenticated user's church (Church B)
- The injected `church_id` field is ignored by the Form Request (it's not in `validated()`)
- No member created under Church A

### TC-12-11 — Filter parameters cannot escape tenant scope
**Action:** `GET /api/members?church_id=<church_a_ulid>`
**Expected:**
- The query param is ignored
- Response contains only Church B's members (global scope is applied before filters)

---

### Input Validation / Injection

### TC-12-12 — SQL injection attempt
**Action:** `GET /api/members?search='; DROP TABLE members; --`
**Expected:**
- No database error
- No data leakage
- Returns `200` with empty or filtered results (ORM parameterised query handles it safely)
- Check: `members` table still exists after the request

### TC-12-13 — XSS in member name
**Action:** Create a member with `first_name: "<script>alert('xss')</script>"`
**Expected:**
- Value stored as plain string (no execution)
- When returned in API response: JSON-encoded, e.g. `"<script>alert('xss')<\/script>"`
- API is JSON-only — no HTML rendering, so XSS is not possible via the API

### TC-12-14 — Oversized payload rejected
**Action:** Submit a `POST /api/members` with a `notes` field containing 100,000 characters
**Expected:** `422 { "errors": { "notes": ["The notes may not be greater than 5000 characters."] } }`

---

### Field Encryption

### TC-12-15 — National ID is encrypted at rest
**Action:**
1. Create a member with `national_id_number: "GHA-000000000-0"`
2. Query the database directly: `SELECT national_id_number FROM members WHERE id = '...'`
**Expected:**
- Database value is an encrypted ciphertext string (not the plain value)
- Running a database search for `GHA-000000000-0` returns zero rows

### TC-12-16 — Pastoral notes are encrypted at rest
**Action:** Create a pastoral note. Check the database.
**Expected:** `pastoral_notes.content` column stores ciphertext, not plain text

### TC-12-17 — Encrypted fields cannot be filtered or sorted
**Action:** `GET /api/members?national_id=GHA-000000000-0` (even if this param were accepted)
**Expected:**
- The endpoint does not support searching by encrypted fields (by design)
- If the param is passed: it is ignored or returns a 422

---

### Security Headers

### TC-12-18 — Security headers present on all API responses
**Action:** Make any API request and inspect the response headers
**Expected:**
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: no-referrer`
- `Content-Security-Policy: default-src 'none'` (API — no content served)
- `Strict-Transport-Security: max-age=63072000; includeSubDomains` (production only)

### TC-12-19 — Server version not disclosed
**Action:** Inspect response headers
**Expected:**
- `X-Powered-By` header: absent
- `Server` header: absent or generic (Nginx configured to hide version)

---

### Webhook Security

### TC-12-20 — Paystack webhook rejected without valid signature
**Action:** POST to `/api/webhooks/paystack` without the `x-paystack-signature` header
**Expected:** `401` — no processing, no job dispatched

### TC-12-21 — WhatsApp webhook rejected with wrong verify token
**Action:** GET `/api/webhooks/whatsapp?hub.verify_token=wrong_token&hub.challenge=123`
**Expected:** `403`

---

## Pass / Fail Criteria

All test cases are **P0**. There is no "P1 security issue." Any failure blocks deployment to production immediately. TC-12-07 through TC-12-11 (IDOR and tenant isolation) and TC-12-15 through TC-12-16 (encryption) are especially critical. These tests must be run manually — do not rely solely on unit tests.
