# QA-02 — Multi-Tenancy Isolation

**Scope:** Verify that Church B can never access, modify, or even detect the existence of Church A's data through any API endpoint.
**Sprint:** 1 (foundation), re-run every sprint
**Risk:** R9 (data breach), D-007 (single-DB multi-tenancy decision)

> This is the most critical QA plan in the project. A single failure here is a P0 data breach.

---

## Setup

Create two completely separate test churches:
- **Church A:** slug `church-alpha`, admin user phone `+233244100001`
- **Church B:** slug `church-bravo`, admin user phone `+233244200001`
- Seed Church A with: 5 members, 2 services with attendance records, 3 contributions, 1 pledge, 1 message log

All tests are performed as an authenticated Church B admin.

---

## Test Cases

### TC-02-01 — Members: Church B cannot list Church A's members
**Action:** As Church B admin → `GET /api/members`
**Expected:** Response contains only Church B's members (zero, since none were seeded)

### TC-02-02 — Members: Church B cannot fetch a Church A member by ID
**Action:** As Church B admin → `GET /api/members/{church_a_member_id}`
**Expected:** `404` — not `403`. (403 reveals the resource exists.)

### TC-02-03 — Members: Church B cannot update a Church A member
**Action:** As Church B admin → `PATCH /api/members/{church_a_member_id}` with valid payload
**Expected:** `404`

### TC-02-04 — Members: Church B cannot delete/archive a Church A member
**Action:** As Church B admin → `PATCH /api/members/{church_a_member_id}/status` with `{ "status": "archived" }`
**Expected:** `404`

### TC-02-05 — Contributions: Church B cannot list Church A's contributions
**Action:** As Church B admin (finance-secretary role) → `GET /api/contributions`
**Expected:** Empty list — Church A's contributions not included

### TC-02-06 — Contributions: Church B cannot fetch a Church A contribution
**Action:** As Church B admin → `GET /api/contributions/{church_a_contribution_id}`
**Expected:** `404`

### TC-02-07 — Contributions: Church B cannot void a Church A contribution
**Action:** As Church B admin → `PATCH /api/contributions/{church_a_contribution_id}/void`
**Expected:** `404`

### TC-02-08 — Attendance: Church B cannot see Church A's services
**Action:** As Church B admin → `GET /api/services`
**Expected:** Empty list

### TC-02-09 — Attendance: Church B cannot check a member into Church A's service
**Action:** As Church B admin → `POST /api/services/{church_a_service_id}/attendance`
**Expected:** `404`

### TC-02-10 — Families: Church B cannot access Church A's families
**Action:** As Church B admin → `GET /api/families/{church_a_family_id}`
**Expected:** `404`

### TC-02-11 — Reports: Church B cannot see Church A's report data
**Action:** As Church B admin → `GET /api/reports/giving-trends`
**Expected:** Report data reflects Church B only (zeros if no Church B data)

### TC-02-12 — Subdomain routing enforces isolation
**Action:** Authenticate on `church-alpha.yourapp.com` (get a token). Then use that token to make a request to `church-bravo.yourapp.com/api/members`.
**Expected:** `401` or `404` — the token is scoped to Church A's subdomain; it must not work on Church B's subdomain.

### TC-02-13 — ULID collision attempt
**Action:** As Church B admin, attempt to request a resource using a ULID that is known to belong to Church A (obtained from Church A's API responses during setup).
**Expected:** `404` — the ULID is valid but belongs to a different church.

### TC-02-14 — Mass assignment cannot override church_id
**Action:** As Church B admin → `POST /api/members` with payload including `"church_id": "{church_a_id}"`
**Expected:**
- Request succeeds (201) — but the created member belongs to Church B, not Church A
- Verify: `GET /api/members/{new_member_id}` from Church B returns the member
- Verify: `GET /api/members/{new_member_id}` from Church A returns `404`

### TC-02-15 — Global scope cannot be bypassed via filter parameters
**Action:** As Church B admin → `GET /api/members?church_id={church_a_id}`
**Expected:** Empty list — the filter parameter is ignored or the global scope takes precedence

---

## Pass / Fail Criteria

All 15 test cases must pass. **Any failure is a P0 blocker — do not deploy.** The isolation guarantee is the foundation of the entire product.

---

## Automation Note

These tests must also exist as automated PHPUnit tests (see `docs/10-definition-of-done.md` §Multi-Tenancy). Manual execution here is a double-check, not a replacement for the automated suite.
