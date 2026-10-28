# QA Execution Tracker

> Update this file after every QA run. One row per plan per sprint.

---

## How to Use

1. Before each sprint's QA phase, copy the **Sprint Template** row below for each plan you're running.
2. As you execute, mark each test case in the plan itself with `[PASS]`, `[FAIL]`, or `[SKIP reason]`.
3. Log the overall result here with a date and tester name.
4. File a GitHub issue for every `[FAIL]` with label `qa-failure` and the severity (P0/P1/P2/P3).

---

## Status Legend

| Symbol | Meaning |
|---|---|
| ✅ | All test cases passed |
| ❌ | One or more failures |
| ⚠️ | Passed with known skips (note reason) |
| 🔄 | In progress |
| — | Not yet run |

---

## Sprint 1 — Authentication & Foundation

| Plan | Version | Tested By | Date | Status | Issues Filed |
|---|---|---|---|---|---|
| QA-01 Authentication | v0.1 | — | — | — | — |
| QA-02 Multi-Tenancy | v0.1 | — | — | — | — |
| QA-10 Permissions (roles foundation) | v0.1 | — | — | — | — |
| QA-12 Security (auth controls) | v0.1 | — | — | — | — |

**Sprint 1 Sign-off:** [ ] Not signed off

---

## Sprint 2 — Members & Onboarding

| Plan | Version | Tested By | Date | Status | Issues Filed |
|---|---|---|---|---|---|
| QA-03 Onboarding | v0.1 | — | — | — | — |
| QA-04 Members | v0.1 | — | — | — | — |
| QA-02 Multi-Tenancy | v0.2 | — | — | — | — |
| QA-12 Security (IDOR) | v0.2 | — | — | — | — |

**Sprint 2 Sign-off:** [ ] Not signed off

---

## Sprint 3 — Attendance & WhatsApp CHECKIN

| Plan | Version | Tested By | Date | Status | Issues Filed |
|---|---|---|---|---|---|
| QA-05 Attendance | v0.1 | — | — | — | — |
| QA-09 WhatsApp (CHECKIN only) | v0.1 | — | — | — | — |
| QA-02 Multi-Tenancy (attendance) | v0.3 | — | — | — | — |
| QA-13 Performance (attendance) | v0.1 | — | — | — | — |

**Sprint 3 Sign-off:** [ ] Not signed off

---

## Sprint 4 — Giving & Paystack

| Plan | Version | Tested By | Date | Status | Issues Filed |
|---|---|---|---|---|---|
| QA-06 Giving | v0.1 | — | — | — | — |
| QA-07 Paystack | v0.1 | — | — | — | — |
| QA-08 SMS (receipts) | v0.1 | — | — | — | — |
| QA-02 Multi-Tenancy (contributions) | v0.4 | — | — | — | — |
| QA-12 Security (webhooks) | v0.3 | — | — | — | — |

**Sprint 4 Sign-off:** [ ] Not signed off

---

## Sprint 5 — Reports, Broadcasts & Full Flow

| Plan | Version | Tested By | Date | Status | Issues Filed |
|---|---|---|---|---|---|
| QA-08 SMS (broadcasts) | v0.2 | — | — | — | — |
| QA-09 WhatsApp (broadcasts) | v0.2 | — | — | — | — |
| QA-10 Permissions (full matrix) | v0.2 | — | — | — | — |
| QA-11 Reports & Exports | v0.1 | — | — | — | — |
| QA-13 Performance (full) | v0.2 | — | — | — | — |
| QA-14 E2E Sunday Flow | v0.1 | — | — | — | — |

**Sprint 5 Sign-off:** [ ] Not signed off

---

## Pre-Launch / MVP Release

> All 14 plans must show ✅ or ⚠️ (no P0 or P1 skips) before launch.

| Plan | Final Status | Sign-off By | Date |
|---|---|---|---|
| QA-01 Authentication | — | — | — |
| QA-02 Multi-Tenancy | — | — | — |
| QA-03 Onboarding | — | — | — |
| QA-04 Members | — | — | — |
| QA-05 Attendance | — | — | — |
| QA-06 Giving | — | — | — |
| QA-07 Paystack | — | — | — |
| QA-08 SMS | — | — | — |
| QA-09 WhatsApp | — | — | — |
| QA-10 Permissions | — | — | — |
| QA-11 Reports & Exports | — | — | — |
| QA-12 Security | — | — | — |
| QA-13 Performance | — | — | — |
| QA-14 E2E Sunday Flow | — | — | — |

**MVP Launch Sign-off:** [ ] Not signed off — requires all rows filled.

---

## Open QA Issues

> Copy from GitHub issues. Keep this in sync with the issue tracker.

| Issue # | Plan | TC | Severity | Description | Status |
|---|---|---|---|---|---|
| — | — | — | — | — | — |

---

## Notes

- All QA runs must use the Africa's Talking **sandbox** and Paystack **test** environment. Never run against production.
- Multi-tenancy (QA-02) and Security (QA-12) are run every sprint, not just once. Both have a version column for this reason.
- After any significant refactor, re-run QA-02, QA-06, and QA-12 at minimum.
