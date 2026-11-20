# QA-11 — Reports & Exports

**Scope:** Attendance trends, giving trends, member growth, CSV export (members and contributions), async export flow.
**Sprint:** 5

---

## Setup

- Test church with 12 months of seeded data:
  - 52 services (weekly Sundays) with attendance records
  - Monthly contributions (varying amounts per fund type)
  - Member joins and status changes over the 12 months
- Church admin user with `reports.view` and `reports.export` permissions

---

## Test Cases

### Attendance Trends Report

### TC-11-01 — Attendance trend — 12-week view
**Action:** `GET /api/v1/reports/attendance?weeks=12` (docs/08: `weeks`, default 12, `group_by=week` by default)
**Expected:**
- Returns 12 rows, newest first, one per ISO week (Monday to Sunday, in the church's time zone)
- Each row: `{ "week": "2026-W42", "week_start": "2026-10-12", "total": 87, "first_timers": 3, "services": 1 }` (`first_timers` counts visitors whose first visit date is in that week)
- Weeks with no service: `"total": 0` (not omitted)
- `summary` has `average` (rounded), `highest` and `lowest` over the weeks in which a service was recorded

### TC-11-01a — Attendance trend by service
**Action:** `GET /api/v1/reports/attendance?group_by=service&weeks=12`
**Expected:** one row per recorded service in those weeks, newest first: `{ "service_id", "service_date", "service_name", "total" }`; a paper headcount replaces the digital count; services nobody recorded and services still to come are not listed; `weeks=0`, `weeks=53` and an unknown `group_by` are `422`

### TC-11-02 — Attendance trend accuracy
**Action:** For a specific Sunday with 45 check-ins, verify the report shows 45
**Expected:** Report count matches exact attendance record count (no off-by-one)

### TC-11-03 — Attendance trend respects voided/removed records
**Action:** Delete (soft-delete) an attendance record that was added in error. Check the report.
**Expected:** Report total decreases by 1 — soft-deleted records excluded

---

### Giving Trends Report

### TC-11-04 — Giving trend — monthly view
**Action:** `GET /api/v1/reports/giving?months=12` (docs/08: `months`)
**Expected:**
- 12 rows, newest first, one per calendar month in the church's time zone; a month with nothing given is `"0.00"`, not omitted
- Each row: `{ "month": "2026-10", "month_start": "2026-10-01", "total": "15450.00", "total_formatted": "GHS 15,450.00", "by_fund": [ { "fund_type_id", "name": "Tithe", "total": "8000.00", "total_formatted" }, ... ] }`
- Voided contributions excluded from totals
- `summary`: `total`, `previous_total` (the same number of months before) and `change_percent` (one decimal, `null` when the months before were empty)

### TC-11-05 — Giving trend by fund type
**Action:** `GET /api/v1/reports/giving?fund_type_id={tithe_id}&months=6`
**Expected:** Only tithe contributions in the totals, in `by_fund` and in the summary

### TC-11-06 — Giving trend accuracy against raw data
**Action:** Sum all non-voided contribution amounts for October manually. Compare to report.
**Expected:** Report total matches manual sum exactly (to 2 decimal places)

### TC-11-07 — Giving trend: pastor sees summary only
**Action:** As a pastor (giving.summary permission) → `GET /api/v1/reports/giving`
**Expected:** Summary totals returned — no breakdown by member, no individual amounts

---

### Member Growth Report

### TC-11-08 — Member growth — monthly view
**Action:** `GET /api/v1/reports/members?months=12` (docs/08, D-063)
**Expected:**
- 12 rows, newest first, one per calendar month in the church's time zone; a quiet month is zeros, not omitted
- Each row: `{ "month": "2026-10", "month_start": "2026-10-01", "new": 4, "lost": 1, "net": 3, "active_total": 212 }`
- `new` counts people who became active that month: added as active, a prospect confirmed, or a returning member (a prospect is not counted until confirmed)
- `lost` counts active members who left active membership that month (inactive, transferred, deceased, archived, or back to prospect); a status change that leaves the member active counts as neither
- `net = new - lost`; this month's `active_total` equals the active member count today, and each earlier month is worked back from the next
- `summary` adds up `new`, `lost` and `net` over the months asked for; `months=0` and `months=25` are `422`

---

### CSV Exports

### TC-11-09 — Member CSV export — async flow
**Action:** `POST /api/v1/exports/members` (optionally with the filters of `GET /members`)
**Expected:**
- Response `202 { "export_id": "ulid", "message": "Export queued. You will receive a download link by email when it is ready." }` (D-064: email, not SMS)
- An `exports` row (status `queued`) records who asked, the filters and when; a job is queued on the `exports` queue, nothing is built in the request
- Once complete the row is `completed` with `row_count`, the CSV is in private storage at `churches/{church_id}/exports/{export_id}/members-{date}.csv`
- The staff member who asked is emailed a signed link that works for 1 hour; the message log keeps a summary and never the link
- Running the job again does nothing; a job that fails for good marks the export `failed` and sends nothing

### TC-11-10 — Member CSV content
**Action:** Download and inspect the members CSV
**Expected:**
- Headers: `id, member_number, first_name, last_name, phone, email, status, gender, date_of_birth, department, family, joined_date, educational_level`
- Members in name order; the same default as the list (prospects and active members; other statuses only when `status` is asked for)
- Phone numbers in E.164 format; dates in `YYYY-MM-DD` format; several departments joined with `; `
- National ID, address, landmark and pastoral notes NOT exported (PII — excluded by design)
- A text starting with `=`, `+`, `-` or `@` has a leading `'` (the phone column excepted)
- Another church's members and removed members are never in the file

### TC-11-11 — Contribution CSV export
**Action:** `POST /api/v1/exports/contributions` with the same `from` and `to` filters as the list (`2026-10-01`, `2026-10-31`)
**Expected:**
- `202` and the same async flow as TC-11-09 (an `exports` row, a job on the `exports` queue, a 1-hour link by email); the file is `contributions-{date}.csv`
- CSV headers: `id, date, member_name, fund_type, amount_ghs, payment_method, reference, is_voided, recorded_by`
- Only October contributions included, oldest first; the other gift-list filters (`member_id`, `service_id`, `fund_type_id`, `payment_method`) work too
- Voided contributions included but flagged `is_voided` = `true` (for audit purposes), even when the list's voided switch is not sent
- Amounts are GHS values to two decimals (not pesewas), exact; `date` is in the church's time zone; `fund_type` is the name the gift was recorded under
- A gift with no member shows `Anonymous`; an erased member shows `[deleted]`; no notes, void reason or phone number; a text starting `=`, `+`, `-` or `@` has a leading `'`
- Only `giving.export` (church admin, finance secretary): the pastor, department head and youth pastor get `403`; the 5-an-hour limit is shared with the member export

### TC-11-12 — Export rate limit
**Action:** Submit 6 member export requests within one hour as the same staff member
**Expected:** The 6th request returns `429 { "message": "You can export at most 5 times per hour." }` with `Retry-After`; another staff member is not limited; after the hour it works again

### TC-11-13 — Export download link expires
**Action:** Wait for the signed URL to expire (1 hour) and click the link
**Expected:** `403` or redirect to an expired link page — file is not publicly accessible indefinitely. The file itself is deleted after 7 days (`exports:prune-files`), the `exports` record stays.

### TC-11-14 — Export for church with no data
**Action:** Request a member export for a brand-new church with no members
**Expected:**
- Export completes successfully
- CSV file contains only the header row (no data rows)
- No error returned

---

## Pass / Fail Criteria

TC-11-06 (accuracy) and TC-11-10 (no national ID in export) are **P0**. Leaking national ID numbers in an export is a data breach. TC-11-09 (async flow) is **P1** — synchronous exports would time out for large churches.
