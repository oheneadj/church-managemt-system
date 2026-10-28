# QA-03 — Church Onboarding

**Scope:** New church signup, slug selection, first admin setup, Paystack subaccount creation.
**Sprint:** 2 (onboarding flow)
**Risk:** R5 (admin turnover), R8 (platform fee)

---

## Setup

- Staging environment with no existing church named "Victory Chapel"
- A valid Paystack test bank account (use Paystack test data)
- Africa's Talking sandbox for OTP delivery

---

## How v1 differs from these cases (D-037)

- **TC-03-01:** the phone is proven *before* the church exists, so there is no `pending_onboarding` church. After creating it the admin signs in on the new address with a new code instead of being signed in straight away. The field is `admin_phone`, and the sign-up page is on the platform host.
- **TC-03-02:** the error is `422` on `slug` with free alternatives such as `grace-assembly-gh`.
- **TC-03-05, TC-07-01:** the Paystack subaccount and payout details come with story 4.1, not sign-up.
- **TC-03-06:** the sign-up result recommends a second admin; inviting one is story 5.15.

---

## Test Cases

### TC-03-01 — Successful church signup
**Action:** Complete the onboarding form:
- Church name: "Victory Chapel International"
- Slug: `victory-chapel`
- Admin name: Kofi Mensah
- Admin phone: `+233244300001`

**Expected:**
- Church record created with `status: pending_onboarding`
- OTP SMS sent to `+233244300001`
- After OTP verification: admin is logged in at `victory-chapel.yourapp.com`
- Onboarding wizard starts

### TC-03-02 — Slug uniqueness validation
**Action:** Attempt to create a church with slug `grace-assembly` when one already exists
**Expected:**
- `422 { "errors": { "slug": ["This church name is already taken. Try grace-assembly-gh or grace-assembly-international."] } }`
- Suggestion offered (not just rejection)

### TC-03-03 — Slug format validation
**Action:** Submit slug `Grace Assembly!!` (spaces, special chars)
**Expected:** Validation error — slug must be lowercase letters, numbers, and hyphens only

### TC-03-04 — Minimum required fields
**Action:** Submit onboarding with church name only (no admin phone)
**Expected:** Validation error on `admin_phone` field

### TC-03-05 — Paystack subaccount created during onboarding
**Action:** Complete onboarding including bank account details
**Expected:**
- `churches.paystack_subaccount_code` is populated (format: `ACCT_*`)
- Test a payment to verify split routing works (GHS 100 → church receives GHS 80 if fee is 20%)
- If Paystack subaccount creation fails, church is flagged: `onboarding_status: paystack_pending`, and giving is disabled until resolved

### TC-03-06 — Two admin accounts required
**Action:** Try to complete onboarding with only one phone number
**Expected:** Warning shown: "We recommend adding a second admin in case of staff changes." (Not a hard block in MVP — a soft warning.)

### TC-03-07 — Subdomain is accessible immediately after onboarding
**Action:** After completing onboarding, navigate to `victory-chapel.yourapp.com`
**Expected:**
- Login page loads
- Subdomain resolves correctly (DNS is wildcard — no DNS change needed)
- SSL is active (padlock shown in browser)

### TC-03-08 — Church settings are pre-populated with defaults
**Action:** After onboarding, check church settings page
**Expected:**
- Default fund types are seeded: Tithe, Offering, Building Fund, Missions, Special Offering
- Cash receipt SMS is enabled by default
- WhatsApp CHECKIN is disabled (pending Meta setup)

### TC-03-09 — Onboarding state persists across sessions
**Action:** Start onboarding, close the browser halfway through, return later
**Expected:**
- Returning to the onboarding URL resumes where you left off
- Data entered previously is pre-filled

### TC-03-10 — Plan is assigned on onboarding
**Action:** Complete onboarding on the Starter plan
**Expected:**
- `churches.plan = 'starter'`
- SMS credit allocation matches the Starter plan
- Feature flags match Starter tier (e.g. broadcast limited to 200 recipients)

---

## Pass / Fail Criteria

TC-03-01, TC-03-05, and TC-03-07 are the critical path. Failure on these blocks Sprint 2. TC-03-06 is advisory only.
