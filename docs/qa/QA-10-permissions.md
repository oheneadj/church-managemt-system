# QA-10 — Roles & Permissions

**Scope:** Role access matrix, permission gates on every module, UI hiding, and API enforcement.
**Sprint:** 2 (foundation), re-run every sprint as new features land
**Risk:** Security

> Every row in the access matrix must be tested. "The UI doesn't show the button" is not sufficient — the API must also return 403.

---

## Roles Under Test

| Role | Abbrev |
|---|---|
| church-admin | CA |
| pastor | P |
| finance-secretary | FS |
| department-head | DH |
| youth-pastor | YP |

---

## Access Matrix

Test each cell: ✅ = should succeed (200/201) | ❌ = should be denied (403)

### Member Management

| Endpoint | CA | P | FS | DH | YP |
|---|---|---|---|---|---|
| GET /api/members | ✅ | ✅ | ✅ | ✅ | ✅ |
| GET /api/members/{id} | ✅ | ✅ | ✅ | ✅ | ✅ |
| POST /api/members | ✅ | ❌ | ❌ | ❌ | ❌ |
| PATCH /api/members/{id} | ✅ | ❌ | ❌ | ❌ | ❌ |
| PATCH /api/members/{id}/status | ✅ | ❌ | ❌ | ❌ | ❌ |

### Attendance

| Endpoint | CA | P | FS | DH | YP |
|---|---|---|---|---|---|
| GET /api/services | ✅ | ✅ | ✅ | ✅ | ✅ |
| POST /api/services | ✅ | ✅ | ❌ | ❌ | ❌ |
| PATCH /api/services/{id}/open | ✅ | ✅ | ❌ | ❌ | ❌ |
| POST /api/services/{id}/attendance | ✅ | ✅ | ❌ | ❌ | ❌ |
| GET /api/services/{id}/attendance | ✅ | ✅ | ✅ | ✅ | ✅ |

### Giving

| Endpoint | CA | P | FS | DH | YP |
|---|---|---|---|---|---|
| GET /api/contributions | ✅ | ❌ | ✅ | ❌ | ❌ |
| GET /api/contributions/{id} | ✅ | ❌ | ✅ | ❌ | ❌ |
| POST /api/contributions (cash) | ✅ | ❌ | ✅ | ❌ | ❌ |
| POST /api/contributions/momo | ✅ | ❌ | ✅ | ❌ | ❌ |
| PATCH /api/contributions/{id}/void | ✅ | ❌ | ✅ | ❌ | ❌ |
| GET /api/reports/giving-trends | ✅ | ✅ | ✅ | ❌ | ❌ |
| GET /api/contributions/sunday-report | ✅ | ✅ | ✅ | ❌ | ❌ |

### Communications

| Endpoint | CA | P | FS | DH | YP |
|---|---|---|---|---|---|
| POST /api/communications/broadcast | ✅ | ✅ | ❌ | ❌ | ❌ |
| GET /api/communications/logs | ✅ | ✅ | ❌ | ❌ | ❌ |

### Reports & Exports

| Endpoint | CA | P | FS | DH | YP |
|---|---|---|---|---|---|
| GET /api/reports/attendance-trends | ✅ | ✅ | ✅ | ❌ | ❌ |
| GET /api/reports/member-growth | ✅ | ✅ | ❌ | ❌ | ❌ |
| POST /api/exports/members | ✅ | ❌ | ❌ | ❌ | ❌ |
| POST /api/exports/contributions | ✅ | ❌ | ✅ | ❌ | ❌ |

### Settings

| Endpoint | CA | P | FS | DH | YP |
|---|---|---|---|---|---|
| GET /api/church/settings | ✅ | ✅ | ✅ | ❌ | ❌ |
| PATCH /api/church/settings | ✅ | ❌ | ❌ | ❌ | ❌ |
| POST /api/church/users (invite staff) | ✅ | ❌ | ❌ | ❌ | ❌ |

---

## Test Cases

### TC-10-01 through TC-10-N — Matrix tests
Each cell in the matrix above is one test case. For each cell:
1. Create a user with ONLY the specified role (no other roles)
2. Authenticate as that user
3. Make the API call
4. Assert the expected HTTP status code

**For ✅ cells:** Assert `200` or `201`. Assert response contains expected data.
**For ❌ cells:** Assert `403`. Assert response body is `{ "message": "This action is unauthorized." }`.

### TC-10-N+1 — UI hides restricted actions
**Action:** Log in as a Finance Secretary. Navigate to the member directory.
**Expected:**
- "Add Member" button is NOT visible in the UI
- "Edit Member" option is NOT available on member profile

**Action:** Log in as a Pastor. Navigate to the giving section.
**Expected:**
- Sunday Offering Report IS visible
- Individual contribution list is NOT visible
- Record Contribution button is NOT visible

### TC-10-N+2 — Role assignment by church admin
**Action:** Church admin assigns the `finance-secretary` role to a user
**Expected:**
- User immediately gains `giving.record`, `giving.edit`, `giving.export` permissions
- User retains those permissions on next login (token re-issue)
- Changing role from `finance-secretary` to `pastor` removes giving permissions

### TC-10-N+3 — Deactivated user cannot authenticate
**Action:** Deactivate a staff member's account. They attempt OTP login.
**Expected:**
- OTP is sent (we don't reveal account status in the OTP request response)
- On verification: `403 { "message": "Your account has been deactivated. Contact your church admin." }`

### TC-10-N+4 — Permission check is on the API, not just the UI
**Action:** Manually craft an API request as a Finance Secretary to `POST /api/members` (add member)
**Expected:** `403` — the API enforces permissions regardless of what the UI shows

---

## Pass / Fail Criteria

Every matrix cell must match expected. Any ❌ cell that returns 200 is a **P0 security vulnerability**. Any ✅ cell that returns 403 is a **P1** (broken feature for a valid user).
