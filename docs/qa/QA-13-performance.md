# QA-13 — Performance & 3G Simulation

**Scope:** API response times, database query counts (N+1), large dataset handling, export performance, 3G mobile network simulation for the React SPA.
**Sprint:** 5 (API-level), Sprint 6 (SPA / 3G)
**Risk:** Poor performance on Ghanaian 3G networks makes the product unusable.

> The majority of church staff will access this system on a phone over a 3G or 4G connection. "Works fine on my laptop" is not a pass.

---

## Performance Budgets

| Context | Budget |
|---|---|
| API: simple GET (single resource) | < 100ms |
| API: list with pagination (50 rows) | < 200ms |
| API: member search (1,000 members) | < 500ms |
| API: Sunday report generation | < 2,000ms |
| API: report trend (12 months) | < 3,000ms |
| API: webhook endpoint (return 200) | < 100ms |
| SPA: initial page load (cold, 3G) | < 5,000ms |
| SPA: route navigation (warm) | < 500ms |
| SPA: member list render (50 rows) | < 300ms |

---

## Test Cases

### API Response Times

### TC-13-01 — Member list — 1,000 member church
**Setup:** Church with exactly 1,000 active members
**Action:** `GET /api/members?page=1&per_page=50`
**Measure:** Response time, query count (Telescope or query log)
**Expected:**
- Response time: < 200ms
- SQL queries: ≤ 5 (no N+1: members + departments in 1-2 queries max)

### TC-13-02 — Member search — 1,000 member church
**Action:** `GET /api/members?search=Mensah`
**Measure:** Response time
**Expected:** < 500ms (indexed `last_name` column)

### TC-13-03 — Attendance list for a large service
**Setup:** Service with 750 attendees
**Action:** `GET /api/services/{id}/attendance?page=1&per_page=50`
**Expected:** < 200ms, no N+1

### TC-13-04 — Giving — Sunday report
**Setup:** 200 contributions for a single Sunday
**Action:** `GET /api/contributions/sunday-report?service_id={id}`
**Expected:**
- Response: < 2,000ms
- Grand totals correct (validated against TC-11-06)
- Fund-type breakdown loaded without N+1

### TC-13-05 — Giving — 12-month trend
**Setup:** 12 months × 4 Sundays × 200 contributions = ~9,600 rows
**Action:** `GET /api/reports/giving-trends?period=12m`
**Expected:** < 3,000ms (query uses date range index)

### TC-13-06 — Webhook controller response time
**Action:** POST a valid Paystack webhook and measure time-to-200
**Expected:** < 100ms (job is dispatched async, controller returns immediately)

---

### N+1 Detection

### TC-13-07 — Members list has no N+1 queries
**Action:** Fetch a page of 50 members with departments loaded. Count SQL queries using Laravel Telescope or `DB::getQueryLog()`
**Expected:**
- SELECT members: 1 query
- SELECT departments: 1 query (eager-loaded, not 50 separate queries)
- Total: ≤ 5 queries regardless of page size

### TC-13-08 — Contributions list has no N+1 queries
**Action:** Fetch 50 contributions with member name and fund type
**Expected:** ≤ 5 queries (member + fund_type eager-loaded)

### TC-13-09 — Attendance list has no N+1 queries
**Action:** Fetch 50 attendance records with member names
**Expected:** ≤ 3 queries

---

### Export Performance

### TC-13-10 — Member CSV export — 1,000 members
**Action:** Trigger a member export for a church with 1,000 members
**Measure:** Time from job dispatch to CSV available in R2
**Expected:**
- Export completes within 60 seconds
- CSV file size: < 2MB (plain text)
- No memory error or timeout in queue worker

### TC-13-11 — Contribution export — 12 months of data
**Action:** Export contributions for a full year (~9,600 rows)
**Expected:**
- Export completes within 120 seconds
- CSV file size: < 10MB
- Chunk processing used (no full-table load into RAM)

---

### 3G / Mobile Simulation

> Use Chrome DevTools (Network tab → Throttling → "Slow 3G" preset: 400Kbps down, 400Kbps up, 2000ms RTT) for all SPA tests.

### TC-13-12 — SPA initial load on 3G
**Setup:** Clear browser cache. Set Chrome to Slow 3G.
**Action:** Navigate to `grace-assembly.yourapp.com`
**Measure:** Time to interactive (TTI) — when the app is usable, not just visually complete
**Expected:**
- TTI: < 5,000ms on Slow 3G
- Initial JS bundle < 300KB gzipped (React + Zustand + Router)
- Largest Contentful Paint (LCP): visible content < 3,000ms

### TC-13-13 — Route navigation on 3G (warm cache)
**Action:** Already logged in. Navigate from member list to a member profile.
**Expected:** < 500ms (data already chunked; only a single API call needed)

### TC-13-14 — Member list loads on 3G
**Action:** Open the member list page
**Expected:**
- Loading skeleton shown immediately (< 200ms)
- First 50 members visible < 1,500ms
- No layout shift as images load (avatar uses placeholder)

### TC-13-15 — CHECKIN flow on low bandwidth
**Scenario:** Member sends WhatsApp CHECKIN on a slow connection
**Action:** The WhatsApp webhook fires regardless of member's bandwidth (server-side). The confirmation reply is sent by the server.
**Expected:** CHECKIN works as normal — it's server-to-server. Member receives WhatsApp reply within 5 seconds of sending.

### TC-13-16 — Offline detection
**Action:** While using the SPA, disconnect from network. Attempt to load a new page.
**Expected:**
- App shows offline banner: "No internet connection"
- No uncaught errors
- Previous pages still display cached data
- Banner disappears when connection is restored

---

### Pagination & Infinite Scroll

### TC-13-17 — All list endpoints are paginated
**Action:** Call `GET /api/members` with no pagination params on a church with 1,000 members
**Expected:**
- Response contains 50 members (default page size)
- Response includes `meta.total`, `meta.per_page`, `meta.current_page`, `links.next`
- Never returns all 1,000 records in a single response

### TC-13-18 — Page size cannot be exploited
**Action:** `GET /api/members?per_page=10000`
**Expected:** Response returns max 100 records (hard cap), not 10,000

---

## Pass / Fail Criteria

**P0:** TC-13-07 through TC-13-09 (N+1 detection) and TC-13-17 (pagination required). Unbounded queries crash the server at scale.

**P1:** TC-13-12 (3G load time) and TC-13-01 (basic list performance). These directly affect usability for the target user.

**Note:** Performance tests should be run against a staging environment seeded with realistic data volumes, not against a dev database with 10 records.
