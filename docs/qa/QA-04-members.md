# QA-04 — Member Management

**Scope:** Member CRUD, lifecycle transitions, search and filtering, family grouping, photo upload, milestones.
**Sprint:** 2
**Target:** Search < 500ms for a church with 1,000 members.

---

## Setup

- Test church `testchurch` with 50 seeded members (varied statuses, departments, families)
- Admin user with `members.edit` permission
- Pastor user with `members.view` only

---

## Test Cases

### TC-04-01 — Add a new member (full record)
**Action:** Create a member with all fields filled:
- Name: Emmanuel Asante
- Phone: `+233201234567`
- Date of birth: 1988-03-15
- Status: Active
- Department: Choir
- Educational level: Tertiary
- National ID: GHA-123456789-0

**Expected:**
- Member created with ULID
- `church_id` auto-set (not in payload)
- `status: active`
- `national_id_number` is stored encrypted (not visible in DB as plaintext)
- Response is a full `MemberResource` JSON

### TC-04-02 — Duplicate phone number validation
**Action:** Try to add a second member with a phone number already used by another member in the same church
**Expected:** `422 { "errors": { "phone": ["A member with this phone number already exists."] } }`

### TC-04-03 — Phone number from another church is allowed
**Action:** Add a member with a phone number already used by a member in Church B (not testchurch)
**Expected:** Succeeds — phone uniqueness is per church, not global

### TC-04-04 — Member lifecycle: Active → Inactive
**Action:** `PATCH /api/members/{id}/status` with `{ "status": "inactive", "reason": "Relocated to Kumasi" }`
**Expected:**
- Member status is `inactive`
- `status_reason` is saved
- Member still appears in the directory (with inactive badge) unless explicitly filtered out

### TC-04-05 — Member lifecycle: Active → Transferred
**Action:** `PATCH /api/members/{id}/status` with `{ "status": "transferred", "transferred_to": "Lighthouse Chapel Kumasi" }`
**Expected:**
- Status updated to `transferred`
- Record preserved (soft delete rules apply)
- Member does NOT appear in default active directory listing

### TC-04-06 — Member lifecycle: Archived
**Action:** Archive a member who was previously inactive
**Expected:**
- Status is `archived`
- Member does NOT appear in directory at all unless `?include_archived=true` is passed
- Contribution history is still accessible (financial records never disappear)

### TC-04-07 — Member search by name
**Action:** `GET /api/members?search=Emmanuel` in a church with 1,000 members
**Expected:**
- Returns all members with "Emmanuel" in first or last name
- Response time < 500ms (check via browser DevTools Network tab)

### TC-04-08 — Member search by phone
**Action:** `GET /api/members?search=0201234567` (local format without country code)
**Expected:** Finds the member (search normalises phone format)

### TC-04-09 — Filter by department
**Action:** `GET /api/members?department_id={choir_id}`
**Expected:** Only Choir members returned

### TC-04-10 — Filter by status
**Action:** `GET /api/members?status=inactive`
**Expected:** Only inactive members returned

### TC-04-11 — Member photo upload
**Action:** Upload a 2MB JPEG photo for a member
**Expected:**
- Photo stored in R2 at `churches/{church_id}/members/{member_id}/photo.jpg`
- `member.photo_url` in response is a signed URL (includes `?X-Amz-Expires=` or similar)
- Signed URL expires (test: does it become inaccessible after 1 hour?)
- Non-image file upload is rejected: `422`

### TC-04-12 — Family grouping
**Action:** Create a family "The Asante Family", add Emmanuel Asante as head, add his wife and two children
**Expected:**
- Family record created
- Emmanuel is `is_head: true`
- Family members visible on Emmanuel's profile and on the family page
- Children's ages computed from date of birth

### TC-04-13 — Milestone: Baptism
**Action:** Add a baptism milestone for a member: `{ "type": "baptism", "date": "2024-06-02" }`
**Expected:**
- Milestone saved
- Visible on member profile
- Date stored and displayed correctly (no timezone shift)

### TC-04-14 — Role restriction: Pastor cannot edit members
**Action:** As a user with only the `pastor` role → `PATCH /api/members/{id}` with valid payload
**Expected:** `403 Forbidden`

### TC-04-15 — Role restriction: Pastor can view members
**Action:** As a user with only the `pastor` role → `GET /api/members`
**Expected:** `200` — full member list returned (pastors have `members.view`)

### TC-04-16 — Visitor record
**Action:** Record a visitor (not a member): name, phone, how they heard about the church
**Expected:**
- Saved in `visitor_records`, not `members`
- Can be converted to a member (Prospect status) in one action
- Visitor record is retained after conversion

---

## Pass / Fail Criteria

TC-04-07 (search performance) is a mandatory benchmark. Failure = P2. All data isolation tests (TC-04-03) are mandatory. TC-04-01 through TC-04-06 are the critical path for Sprint 2.
