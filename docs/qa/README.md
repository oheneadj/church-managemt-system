# QA Test Plans

> This folder contains test plans for every module and concern in the platform. Each plan covers what to test, how to test it, and what pass/fail looks like.

---

## How to Use These Plans

**Before a sprint ships:** The feature tester works through the relevant plan(s) for all stories in the sprint.

**Before production deployment:** The full suite (or at minimum, the E2E Sunday Service plan) is executed on staging.

**After an incident:** The plan covering the affected area is re-executed on production to confirm the fix.

---

## Test Plan Index

| # | Plan | Scope | Key Risks Covered |
|---|---|---|---|
| QA-01 | [Authentication](QA-01-authentication.md) | OTP flow, lockout, token expiry | R9 (data breach), D-012 (OTP-only auth) |
| QA-02 | [Multi-Tenancy Isolation](QA-02-multi-tenancy.md) | Cross-church data leakage | R9, D-007 (single DB) |
| QA-03 | [Church Onboarding](QA-03-onboarding.md) | Signup, slug, Paystack subaccount | R5 (admin turnover) |
| QA-04 | [Member Management](QA-04-members.md) | CRUD, lifecycle, search, photo | Sprint 2 exit criteria |
| QA-05 | [Attendance](QA-05-attendance.md) | Manual, WhatsApp CHECKIN, QR | Sprint 3 exit criteria |
| QA-06 | [Giving & Contributions](QA-06-giving.md) | Cash, MoMo, void, Sunday report | Sprint 4 exit criteria, R1 (MoMo rejection) |
| QA-07 | [Paystack Integration](QA-07-paystack.md) | Webhook, signature, idempotency | R10 (API changes), financial integrity |
| QA-08 | [SMS / Africa's Talking](QA-08-sms.md) | OTP delivery, receipts, credits, broadcast | R3 (sender ID) |
| QA-09 | [WhatsApp / Meta API](QA-09-whatsapp.md) | CHECKIN flow, templates, opt-in | R2 (Meta approval) |
| QA-10 | [Roles & Permissions](QA-10-permissions.md) | Role access matrix, permission gates | Security |
| QA-11 | [Reports & Exports](QA-11-reports.md) | Attendance trends, giving trends, CSV export | Sprint 5 |
| QA-12 | [Data Safety & Security](QA-12-security.md) | Encryption, headers, rate limits, OWASP | R9 (breach) |
| QA-13 | [Performance](QA-13-performance.md) | Response times, 3G simulation, N+1 queries | R7 (connectivity) |
| QA-14 | [End-to-End: Sunday Service](QA-14-e2e-sunday.md) | Full Sunday flow from open to report | Launch gate |

---

## Test Environments

| Environment | URL | Data |
|---|---|---|
| Local | `http://localhost:8000` | Developer seed data |
| Staging | `https://staging-api.yourapp.com` | Realistic fake data (never real PII) |
| Production | `https://api.yourapp.com` | Live data — test with extreme care |

**Rule:** Destructive tests (delete, void, deactivate) run on local or staging only. Never on production.

**Provider note (Oct 2026, D-018 / D-020):** several plans (QA-06, QA-07, QA-08, QA-09) were written when Paystack and Africa's Talking were the only providers. MoMo collections, payouts, SMS and OTP now run on PayPerlez, so run those cases against the PayPerlez **test** environment. Paystack cases (QA-07) apply to card payments only. Payout and fee-calculation cases (disbursement success, retry, minimum balance, platform fee clamp) still need to be added.

---

## Pass / Fail Standard

A test **passes** when:
- The described action produces the described result, on the first attempt, without workarounds
- No console errors or unexpected log entries appear during the test
- The result is the same on a real mobile device (not just a desktop browser)

A test **fails** when:
- The result differs from what's described — even if the difference seems minor
- An error appears in the UI or API response that isn't the expected error
- The test requires a workaround to complete

---

## Defect Severity

| Severity | Definition | Must fix before |
|---|---|---|
| P0 — Blocker | Platform unusable; data loss or security breach | Immediately |
| P1 — Critical | Core feature broken; no workaround | Before production deploy |
| P2 — Major | Feature degraded; workaround exists | Before sprint closes |
| P3 — Minor | Cosmetic; edge case | Next sprint |

---

## Test Execution Log

When executing a plan, record results in `QA-Tracker.md` (in this folder). Each execution entry includes:
- Date and tester name
- Environment tested
- Sprint / release version
- Pass / fail count
- Defects found (with severity)
- Sign-off
