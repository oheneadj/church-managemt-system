# QA-10 — Roles & Permissions

**Scope:** Role access matrix, permission gates on every module, UI hiding, and API enforcement.
**Sprint:** 2 (foundation), re-run every sprint as new features land
**Risk:** Security

> Every row in the access matrix must be tested. "The UI doesn't show the button" is not sufficient — the API must also return 403.

> **Source of truth (D-055).** This matrix is worked out from `StaffRole::permissions()`, the policies and `docs/08-api.md`. If a cell here disagrees with those, fix this file. A row marked *not built* is tested when its endpoint ships. All paths are under `/api/v1`.

---

## Roles Under Test

| Role | Abbrev | Permissions it carries |
|---|---|---|
| church-admin | CA | all |
| pastor | P | `members.view`, `members.edit`, `milestones.view`, `giving.summary`, `reports.view`, `sms.send` |
| finance-secretary | FS | `members.view`, `members.export`, `giving.summary`, `giving.view`, `giving.record`, `giving.edit`, `giving.export`, `reports.view`, `reports.export` |
| department-head | DH | `members.view` |
| youth-pastor | YP | `members.view`, `milestones.view` |

---

## Access Matrix

Test each cell: ✅ = should succeed (200/201/204) | ❌ = should be denied (403)

### Member Management

| Endpoint | CA | P | FS | DH | YP |
|---|---|---|---|---|---|
| GET /members | ✅ | ✅ | ✅ | ✅ (own departments) | ✅ (youth only) |
| GET /members/{id} | ✅ | ✅ | ✅ | ✅ (own departments; else 403) | ✅ (youth only; else 403) |
| POST /members | ✅ | ✅ | ❌ | ❌ | ❌ |
| PUT /members/{id} | ✅ | ✅ | ❌ | ❌ | ❌ |
| POST /members/{id}/photo | ✅ | ✅ | ❌ | ❌ | ❌ |
| POST /members/{id}/milestones | ✅ | ✅ | ❌ | ❌ | ❌ |
| PATCH /members/{id}/status → `prospect` or `active` | ✅ | ✅ | ❌ | ❌ | ❌ |
| PATCH /members/{id}/status → `inactive`, `transferred`, `deceased`, `archived` | ✅ | ❌ | ❌ | ❌ | ❌ |
| GET /members/{id}/data-export (`members.data-export`) | ✅ | ❌ | ❌ | ❌ | ❌ |
| POST /members/{id}/erasure (`members.erase`) | ✅ | ❌ | ❌ | ❌ | ❌ |
| GET /families, GET /departments | ✅ | ✅ | ✅ | ✅ | ✅ |
| POST/PUT families and departments, department members | ✅ | ✅ | ❌ | ❌ | ❌ |

Milestones appear on `GET /members/{id}` only for CA, P and YP (`milestones.view`); the key is absent for FS and DH.

### Attendance

| Endpoint | CA | P | FS | DH | YP |
|---|---|---|---|---|---|
| GET /services, GET /services/{id} | ✅ | ✅ | ✅ | ✅ | ✅ |
| POST /services, PUT /services/{id}, DELETE /services/{id} | ✅ | ✅ | ❌ | ❌ | ❌ |
| GET, POST, PUT /service-schedules | ✅ | ✅ | ❌ | ❌ | ❌ |
| PATCH /services/{id}/checkin | ✅ | ✅ | ❌ | ❌ | ❌ |
| POST /services/{id}/attendance | ✅ | ✅ | ❌ | ❌ | ❌ |
| POST /services/{id}/visitors | ✅ | ✅ | ❌ | ❌ | ❌ |
| GET /services/{id}/qr | ✅ | ✅ | ❌ | ❌ | ❌ |
| GET /services/{id}/attendance | ✅ | ✅ | ✅ | ✅ | ✅ (visitor names only for CA and P; others see only members in their directory view) |
| GET /members/{id}/attendance | ✅ | ✅ | ✅ | ✅ (own departments) | ✅ (youth) |
| GET /attendance/absent (`reports.view`) | ✅ | ✅ | ✅ | ❌ | ❌ |

### Giving

| Endpoint | CA | P | FS | DH | YP |
|---|---|---|---|---|---|
| GET /contributions (`giving.view`) | ✅ | ❌ | ✅ | ❌ | ❌ |
| GET /contributions/{id} | ✅ | ❌ | ✅ | ❌ | ❌ |
| POST /contributions (cash, `giving.record`) | ✅ | ❌ | ✅ | ❌ | ❌ |
| POST /contributions/initiate (*not built*) | ✅ | ❌ | ✅ | ❌ | ❌ |
| PATCH /contributions/{id}/void (`giving.edit`) | ✅ | ❌ | ✅ | ❌ | ❌ |
| GET /pledges, GET /pledges/{id} (`giving.view`) | ✅ | ❌ | ✅ | ❌ | ❌ |
| POST /pledges (`giving.record`) | ✅ | ❌ | ✅ | ❌ | ❌ |
| PUT /pledges/{id} (`giving.edit`) | ✅ | ❌ | ✅ | ❌ | ❌ |
| GET /services/{id}/offering-report (`giving.summary`) | ✅ | ✅ | ✅ | ❌ | ❌ |
| GET /reports/giving (*not built*) | ✅ | ✅ | ✅ | ❌ | ❌ |
| GET /fund-types | ✅ | ✅ | ✅ | ✅ | ✅ |
| POST/PUT/PATCH /fund-types (`fund-types.manage`) | ✅ | ❌ | ❌ | ❌ | ❌ |

### Communications (*not built*)

| Endpoint | CA | P | FS | DH | YP |
|---|---|---|---|---|---|
| POST /messages/broadcast (`sms.send`) | ✅ | ✅ | ❌ | ❌ | ❌ |
| GET /messages (`sms.send`, D-055) | ✅ | ✅ | ❌ | ❌ | ❌ |

### Reports & Exports (*not built*)

| Endpoint | CA | P | FS | DH | YP |
|---|---|---|---|---|---|
| GET /reports/attendance (`reports.view`) | ✅ | ✅ | ✅ | ❌ | ❌ |
| GET /reports/members (`reports.view`, D-055) | ✅ | ✅ | ✅ | ❌ | ❌ |
| POST /exports/members (`members.export`, D-055) | ✅ | ❌ | ✅ | ❌ | ❌ |
| POST /exports/contributions (`giving.export`) | ✅ | ❌ | ✅ | ❌ | ❌ |

### Settings & Staff

| Endpoint | CA | P | FS | DH | YP |
|---|---|---|---|---|---|
| GET /church/settings | ✅ | ✅ | ✅ | ✅ | ✅ |
| PUT /church/settings (`church.settings`) | ✅ | ❌ | ❌ | ❌ | ❌ |
| GET /staff, POST /staff, PUT /staff/{id} (`staff.manage`) | ✅ | ❌ | ❌ | ❌ | ❌ |
| PATCH /staff/{id}/status, PUT /staff/{id}/permissions (`staff.manage`) | ✅ | ❌ | ❌ | ❌ | ❌ |

---

## Test Cases

### TC-10-01 through TC-10-N — Matrix tests
Each cell in the matrix above is one test case. For each cell:
1. Create a user with ONLY the specified role (no other roles)
2. Authenticate as that user
3. Make the API call
4. Assert the expected HTTP status code

**For ✅ cells:** Assert `200`, `201` or `204`. Assert the response contains the expected data (and, where the cell names a limit, that only the allowed records come back).
**For ❌ cells:** Assert `403`. Assert the body is `{ "message": "This action is unauthorized." }`. A record of another church is always `404`, never `403`.

### TC-10-N+1 — UI hides restricted actions
**Action:** Log in as a Finance Secretary. Navigate to the member directory.
**Expected:**
- "Add Member" button is NOT visible in the UI
- "Edit Member" option is NOT available on member profile

**Action:** Log in as a Pastor. Navigate to the giving section.
**Expected:**
- Sunday Offering Report IS visible
- The Giving menu item and the individual gift list are NOT visible
- Record Contribution button is NOT visible

**Action:** Log in as a Pastor. Open a member and change their status.
**Expected:** only Prospect and Active are offered; Inactive, Transferred, Deceased and Archived are not.

### TC-10-N+2 — Role and permission changes by the church admin
**Action:** Church admin changes a user's role from `finance-secretary` to `pastor` (`PUT /staff/{id}`)
**Expected:**
- The user's next request already uses the new permissions (permissions are read on every request; no token re-issue)
- The giving permissions are gone and `GET /contributions` is `403`

**Action:** Church admin grants `members.export` to a department head (`PUT /staff/{id}/permissions`), then removes it
**Expected:** `POST /exports/members` is `202` while granted and `403` after (the export endpoint is *not built*; until then `GET /staff` shows the extra permission and `can('members.export')` follows it).

**Action:** Church admin tries to remove `sms.send` from a pastor
**Expected:** `422` on `revoke.0`: the role gives that permission; change the role instead.

**Action:** Church admin deactivates, or removes the church admin role from, the only active church admin
**Expected:** `409`; nothing changes.

### TC-10-N+3 — Deactivated user cannot authenticate
**Action:** Deactivate a staff member's account. They enter their email and the right password (D-054).
**Expected:**
- `403 { "message": "Your account has been deactivated. Contact your church admin." }` and no code is sent
- A wrong password for the same account is the generic `422 "Invalid email or password"`
- A user deactivated while signed in is signed out on their next request (`EnsureStaffIsCurrent`)

### TC-10-N+4 — Permission check is on the API, not just the UI
**Action:** Manually craft an API request as a Finance Secretary to `POST /members` (add member)
**Expected:** `403` — the API enforces permissions regardless of what the UI shows

### TC-10-N+5 — The most sensitive export is its own permission (D-055)
**Action:** As a Finance Secretary, `GET /members/{id}/data-export`
**Expected:** `403`. The Finance Secretary can still `POST /exports/members` (the list, with no national ID).

---

## Pass / Fail Criteria

Every matrix cell must match expected. Any ❌ cell that returns 200 is a **P0 security vulnerability**. Any ✅ cell that returns 403 is a **P1** (broken feature for a valid user).
