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
**Action:** `GET /api/reports/attendance-trends?period=12w`
**Expected:**
- Returns 12 data points (one per week)
- Each point: `{ "week": "2026-W42", "total": 87, "first_timers": 3 }`
- Weeks with no service: `"total": 0` (not omitted)
- Average attendance shown in summary

### TC-11-02 — Attendance trend accuracy
**Action:** For a specific Sunday with 45 check-ins, verify the report shows 45
**Expected:** Report count matches exact attendance record count (no off-by-one)

### TC-11-03 — Attendance trend respects voided/removed records
**Action:** Delete (soft-delete) an attendance record that was added in error. Check the report.
**Expected:** Report total decreases by 1 — soft-deleted records excluded

---

### Giving Trends Report

### TC-11-04 — Giving trend — monthly view
**Action:** `GET /api/reports/giving-trends?period=12m`
**Expected:**
- 12 data points (one per month)
- Each point: `{ "month": "2026-10", "total": 15450.00, "by_fund": { "tithe": 8000.00, "offering": 5000.00, ... } }`
- Voided contributions excluded from totals

### TC-11-05 — Giving trend by fund type
**Action:** `GET /api/reports/giving-trends?fund_type_id={tithe_id}&period=6m`
**Expected:** Only tithe contributions in the totals

### TC-11-06 — Giving trend accuracy against raw data
**Action:** Sum all non-voided contribution amounts for October manually. Compare to report.
**Expected:** Report total matches manual sum exactly (to 2 decimal places)

### TC-11-07 — Giving trend: pastor sees summary only
**Action:** As a pastor (giving.summary permission) → `GET /api/reports/giving-trends`
**Expected:** Summary totals returned — no breakdown by member, no individual amounts

---

### Member Growth Report

### TC-11-08 — Member growth — monthly view
**Action:** `GET /api/reports/member-growth?period=12m`
**Expected:**
- Shows new members added per month
- Shows members who became inactive/transferred per month
- Net growth calculation: `new - lost`

---

### CSV Exports

### TC-11-09 — Member CSV export — async flow
**Action:** `POST /api/exports/members`
**Expected:**
- Response: `202 { "export_id": "ulid", "status": "processing", "message": "Export is being prepared. You will receive an SMS when it is ready." }`
- Background job dispatched to `exports` queue
- Once complete: CSV uploaded to R2
- Admin receives SMS: "Your member export is ready. Download it here: [signed URL]"
- Signed URL is time-limited (1 hour)

### TC-11-10 — Member CSV content
**Action:** Download and inspect the members CSV
**Expected:**
- Headers: `id, first_name, last_name, phone, email, status, gender, date_of_birth, department, family, joined_date, educational_level`
- All 50 active members present (inactive excluded unless requested)
- Phone numbers in E.164 format
- Dates in `YYYY-MM-DD` format
- National ID NOT exported (PII — excluded by design)

### TC-11-11 — Contribution CSV export
**Action:** `POST /api/exports/contributions?start_date=2026-10-01&end_date=2026-10-31`
**Expected:**
- CSV headers: `id, date, member_name, fund_type, amount_ghs, payment_method, reference, is_voided, recorded_by`
- Only October contributions included
- Voided contributions included but flagged `is_voided: true` (for audit purposes)
- Amounts are GHS values (not pesewas)

### TC-11-12 — Export rate limit
**Action:** Submit 6 export requests within one hour
**Expected:** The 6th request returns `429 { "message": "You can export at most 5 times per hour." }`

### TC-11-13 — Export download link expires
**Action:** Wait for the signed URL to expire (1 hour) and click the link
**Expected:** `403` or redirect to an expired link page — file is not publicly accessible indefinitely

### TC-11-14 — Export for church with no data
**Action:** Request a member export for a brand-new church with no members
**Expected:**
- Export completes successfully
- CSV file contains only the header row (no data rows)
- No error returned

---

## Pass / Fail Criteria

TC-11-06 (accuracy) and TC-11-10 (no national ID in export) are **P0**. Leaking national ID numbers in an export is a data breach. TC-11-09 (async flow) is **P1** — synchronous exports would time out for large churches.
