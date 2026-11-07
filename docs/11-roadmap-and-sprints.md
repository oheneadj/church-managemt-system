# Roadmap & Sprints

> The plan for what ships and when. Sprints are two weeks. This document is updated at the end of every sprint — what moved, what slipped, and why.

---

## Product Roadmap

```
Q4 2026          Q1 2027              Q2 2027             Q3 2027
────────────     ─────────────────    ────────────────    ────────────────
[MVP — v1]       [v1 stabilisation]   [v2 — Rel. Layer]   [v3 — Scale]
  Sprint 1–5       Sprint 6–7           Sprint 8–12          Sprint 13+
  Scaffold &        Bug fixes,           Pastoral care,        Volunteers,
  Core modules      onboarding UX,       youth tracking,       events,
                    first 10 churches    small groups,         denomination
                                         expenditure log       multi-branch
```

---

## v1 MVP — Sprint Breakdown

**Goal:** A fully functional ChMS covering member management, attendance, giving, and communications. Ready for real churches to use.

**Total sprints:** 5 × 2 weeks = **10 weeks**

---

### Sprint 1 — Foundation
*Weeks 1–2*

**Theme:** Get the machine running. No feature code until infrastructure is solid.

| # | Story | Owner | Points | Notes |
|---|---|---|---|---|
| 1.1 | Scaffold Laravel API project (latest stable, Sanctum, Spatie, Pint, Telescope) | Backend | 2 | `composer create-project laravel/laravel` |
| 1.2 | Scaffold React 19 SPA in `resources/js` (Vite, Zustand, React Router v7, TanStack Query v5, Tailwind CSS v4, shadcn/ui) | Frontend | 2 | Same repo as the API (D-022); one Blade shell view, no Inertia |
| 1.3 | Configure Oracle ARM instance + Coolify | DevOps | 3 | Install, secure, point domain |
| 1.4 | Set up wildcard SSL for `*.yourapp.com` | DevOps | 1 | Let's Encrypt via Coolify |
| 1.5 | Configure GitHub CI (Pint + PHPStan + tests on PR) | Backend | 2 | Actions workflow |
| 1.6 | Auto-deploy: GitHub → Coolify pipeline | DevOps | 2 | Staging first, then prod |
| 1.7 | Write and run migrations for all v1 tables | Backend | 3 | See migration order in `07-data-model.md` |
| 1.8 | Configure Redis (cache + queues) | Backend | 1 | |
| 1.9 | Configure Cloudflare R2 (filesystems.php) | Backend | 1 | |
| 1.10 | Set up queue workers via Coolify process supervisor | DevOps | 2 | |
| 1.11 | `ResolveTenant` middleware + `BelongsToChurch` trait | Backend | 2 | The foundation of multi-tenancy |
| 1.12 | Sanctum auth scaffold (OTP request + verify endpoints) | Backend | 3 | With Africa's Talking SMS |
| 1.13 | Base React layout: sidebar nav, auth guard, church context (Zustand) | Frontend | 3 | Shell only, no feature pages yet |

**Sprint 1 exit criteria:** A developer can spin up a local environment, run migrations, log in via OTP, and see the authenticated shell. CI passes. Staging deploys automatically on push to `staging`.

---

### Sprint 2 — Member Directory
*Weeks 3–4*

**Theme:** The church can manage its people.

| # | Story | Owner | Points | Notes |
|---|---|---|---|---|
| 2.1 | Church onboarding flow (name, slug, admin setup) | Full-stack | 3 | Simple form, no payment yet |
| 2.2 | `MemberController` — CRUD endpoints | Backend | 3 | List, get, create, update, status patch |
| 2.3 | `MemberPolicy` — role-based access | Backend | 2 | Cross-tenant test required |
| 2.4 | Member profile photo upload → Cloudflare R2 | Backend | 2 | Signed URL on response |
| 2.5 | `MemberResource` — full serialisation | Backend | 2 | Including conditional `giving_summary` |
| 2.6 | `StoreMemberRequest` + `UpdateMemberRequest` | Backend | 1 | |
| 2.7 | Family grouping endpoints (create family, link members) | Backend | 2 | |
| 2.8 | Department endpoints (list, create, assign members) | Backend | 2 | The screen is built too: `/departments` for staff with `members.edit`, reached from the Members page and from Settings: add, change (name, what it does) and retire or bring back; retiring asks first and members already in it stay in it. Members are placed in departments on the member form. The head is chosen from the church's active staff (`GET /staff/directory`) and named on the list; saving without changing the head never sends it |
| 2.9 | Member milestone endpoints | Backend | 1 | |
| 2.10 | Member directory page (React) — list with search + filters | Frontend | 4 | Phone-first layout |
| 2.11 | Member profile page (React) — full record view | Frontend | 3 | Tabbed: Profile / Giving / Attendance |
| 2.12 | Add/edit member form (React) | Frontend | 3 | Multi-step, mobile friendly |
| 2.13 | Family view page (React) | Frontend | 2 | |

**Sprint 2 exit criteria:** An admin can add a member, upload their photo, assign them to a department and family, and view the full directory with search and filters.

---

### Sprint 3 — Attendance
*Weeks 5–6*

**Theme:** The church can track who shows up.

| # | Story | Owner | Points | Notes |
|---|---|---|---|---|
| 3.1 | Service CRUD endpoints | Backend | 2 | |
| 3.2 | Attendance manual recording endpoint (bulk) | Backend | 2 | |
| 3.3 | WhatsApp CHECKIN webhook handler | Backend | 4 | Meta webhook verify + parse + match + respond |
| 3.4 | QR code check-in (generate QR, handle web form submission) | Backend | 3 | |
| 3.5 | `AttendanceRecord` with idempotency (no double check-in) | Backend | 1 | Unique constraint on service+member |
| 3.6 | Visitor capture endpoint | Backend | 2 | |
| 3.7 | Attendance report endpoint (per-service + per-member) | Backend | 2 | |
| 3.8 | Services list + create page (React) | Frontend | 2 | |
| 3.9 | Live check-in dashboard (React) — open service, see real-time count | Frontend | 4 | Poll every 10s or SSE |
| 3.10 | Manual attendance entry (React) — bulk mark members present | Frontend | 3 | Search-as-you-type member select |
| 3.11 | QR code display page (React) — shown at church entrance | Frontend | 2 | Large QR, auto-refresh, no nav chrome |
| 3.12 | Attendance report page (React) | Frontend | 2 | |
| 3.13 | Absent members alert job (3+ consecutive absences) | Backend | 2 | Queued, configurable threshold. Screen built: `/attendance/absent` (members who have gone absent with a phone link and when they were last at church; the number of services is a choice, default the church setting), reached from a dashboard card and a link on the attendance report. Built (D-067): off until the church switches it on, once for each stretch of absence |

**Sprint 3 exit criteria:** A service can be created, opened for check-in, and members can check in via WhatsApp CHECKIN command. Manual bulk entry works. A report shows who attended.

---

### Sprint 4 — Giving & Contributions
*Weeks 7–8*

**Theme:** The church can receive and record money.

| # | Story | Owner | Points | Notes |
|---|---|---|---|---|
| 4.1 | Paystack subaccount creation during church onboarding | Backend | 3 | API call, store `subaccount_code` |
| 4.2 | `FundType` CRUD endpoints | Backend | 1 | Seed default fund types. The screen is built too: `/settings/funds` for the church admin (`fund-types.manage`), reached from Settings: add, change (name, what it is for, place in the list) and switch off or on; switching off asks first |
| 4.3 | Cash contribution recording endpoint | Backend | 2 | |
| 4.4 | MoMo / card payment initiation (Paystack charge) | Backend | 3 | |
| 4.5 | Paystack webhook handler (`charge.success`, `charge.failed`) | Backend | 4 | Signature verify + idempotency + job |
| 4.6 | `ProcessPaystackWebhookJob` — create contribution, fire event | Backend | 2 | |
| 4.7 | `ContributionReceived` event + `SendContributionReceiptListener` | Backend | 2 | |
| 4.8 | `SendSmsJob` — Africa's Talking, credit deduction, message log | Backend | 2 | |
| 4.9 | Pledge CRUD endpoints | Backend | 2 | |
| 4.10 | Pledge `amount_paid` observer (update on contribution save) | Backend | 2 | |
| 4.11 | Contribution void endpoint | Backend | 1 | |
| 4.12 | Sunday offering report endpoint | Backend | 2 | |
| 4.13 | Contribution list + filters page (React) | Frontend | 3 | Done: filters in the address, void with a reason (`giving.edit`), menu item for `giving.view` |
| 4.14 | Record cash contribution form (React) | Frontend | 2 | Done: a dialog on the Giving page (member search or anonymous, fund, amount, day, optional service, notes); D-057 |
| 4.15 | Initiate MoMo payment flow (React) | Frontend | 3 | Paystack inline / redirect |
| 4.16 | Sunday offering report page (React) — printable | Frontend | 2 | Done: `/services/:id/offering`, opened from the service page; print or save as PDF from the browser; D-058 |
| 4.17 | Pledge management page (React) | Frontend | 2 | Done: `/pledges` with filters, progress, New pledge and Change dialogs; D-059 |
| 4.18 | SMS credit balance display + top-up link | Frontend | 1 | Done: in the header for everyone, a low-credit warning at 50, and a Top up button that says buying is coming (Sprint 5, story 5.4); D-061 |

**Sprint 4 exit criteria:** A finance secretary can record cash contributions, initiate MoMo payments, view the Sunday offering report, and track pledges. Members receive SMS receipts. Paystack subaccount is live and receiving split payouts.

---

### Sprint 5 — Communications, Reports & Polish
*Weeks 9–10*

**Theme:** The church can reach its members. The system is production-ready.

| # | Story | Owner | Points | Notes |
|---|---|---|---|---|
| 5.1 | SMS broadcast endpoint | Backend | 2 | Filter-based recipient selection, credit check. Done: `POST /messages/broadcast/estimate` and `POST /messages/broadcast` (D-065): 202, a `broadcasts` record, one text for each member made by a worker, credits taken text by text |
| 5.2 | WhatsApp broadcast endpoint | Backend | 2 | Opted-in members only |
| 5.3 | Message log endpoint | Backend | 1 | Done: `GET /messages` for `sms.send`, filters channel, type and dates; sign-in and check-in codes are left out |
| 5.4 | SMS top-up endpoint (Paystack payment → credit ledger) | Backend | 2 | |
| 5.5 | Attendance trends report endpoint | Backend | 1 | Done: `GET /reports/attendance`, by week (default) or by service; weeks 1 to 52 |
| 5.6 | Giving trends report endpoint | Backend | 1 | Done: `GET /reports/giving`, monthly with a split by fund and a summary against the months before; totals only |
| 5.7 | Member growth report endpoint | Backend | 1 | Done: `GET /reports/members`, monthly new, lost, net and active total, read from the change history (D-063) |
| 5.8 | Export members (CSV, async, emailed download link) | Backend | 2 | Done: `POST /exports/members`, built on the `exports` queue, 1-hour signed link by email (D-064) |
| 5.9 | Export contributions (CSV, async) | Backend | 1 | Done: `POST /exports/contributions`, same worker, private file and emailed 1-hour link as the member export (D-064) |
| 5.10 | Dashboard home page (React) — role-specific summary cards | Frontend | 4 | Done except the activity feed (open item A9): four cards (the members who have gone absent too), each a link to the page behind it. Active members for every role; the last service's attendance for `reports.view`; this month's giving for `giving.summary`. A card a role may not read is not drawn |
| 5.11 | Broadcast compose page (React) — SMS + WhatsApp | Frontend | 3 | Recipient preview + credit estimate. Done for SMS: `/messages/new` (a New broadcast button on the Messages page): audience by member status and department, the cost worked out by the server as the message is typed, a question before sending; WhatsApp waits for Meta (B4) |
| 5.12 | Message log page (React) | Frontend | 2 | Done: `/messages` for staff with `sms.send`, filters by how it was sent, kind and dates in the address |
| 5.13 | Reports page (React) — attendance + giving charts | Frontend | 3 | Done (attendance, member growth and giving): `/reports` (menu link for `reports.view`) with the attendance tab: average, highest and lowest, a column chart by week or by service over 4 to 52 weeks, and a table view of the same figures. The giving tab (`giving.summary`): the period against the months before and a stacked column chart by month with a block for each fund, by period and fund, with a table view. The member growth tab (`reports.view`): active members today, new, lost and net for the period, and a chart with new above a baseline and lost below it, with a table view. The export buttons are built too: Export list on the Members page and Export gifts on the Giving page |
| 5.14 | Settings page (React) — church profile, service defaults, comms prefs | Frontend | 2 | Backend done: `GET` and `PUT /church/settings` (youth age limit, absence threshold, cash receipts) and `GET` and `PUT /church` (name, denomination, address, city, region, phone, email). The page is built (`/settings`: church details and preferences, for the church admin); the logo upload is still to build |
| 5.15 | Role management page (React) — invite staff, assign roles | Frontend | 2 | Done: `/staff` for the church admin (menu link shown only with `staff.manage`): invite, change name, phone and role, deactivate (asks first) and reactivate; extra permissions are given and taken away from the Permissions button on each person: what their role gives is ticked and fixed, only the extras change, and only a change is sent |
| 5.16 | Member portal (React) — OTP login, own profile + giving history | Frontend | 3 | Separate layout |
| 5.17 | Error pages (401, 403, 404, 500) | Frontend | 1 | |
| 5.18 | Performance audit — fix any endpoint > 500ms | Full-stack | 3 | Query optimisation |
| 5.19 | Full test suite pass — fix any failing or missing tests | Full-stack | 3 | |
| 5.20 | Production deployment + DNS cutover | DevOps | 2 | |

**Sprint 5 exit criteria:** All MVP features live in production. A church can sign up, onboard, and run a full Sunday service end-to-end on the platform. 0 known critical bugs. Test suite green.

---

## Story Point Reference

| Points | Complexity | Example |
|---|---|---|
| 1 | Trivial — 2–4 hours | Add a field to an existing endpoint |
| 2 | Small — half a day | New CRUD endpoint with tests |
| 3 | Medium — 1 day | Feature with multiple states/edge cases |
| 4 | Large — 1.5–2 days | Complex integration (webhook handler, live check-in UI) |
| 5+ | Too big — split it | — |

Sprint velocity target: **30–35 points per sprint** for a 2-person team (1 backend + 1 frontend). Adjust after Sprint 1 retrospective.

---

## v2 — Relationship Layer (Q2 2027)

Sprint planning for v2 happens at the end of Sprint 5, based on real feedback from the first 10 churches. Below is the working backlog — not yet sprint-assigned.

| Feature | Estimated Sprints |
|---|---|
| Pastoral care (visit logs, prayer requests, welfare flags) | 1.5 |
| Ghana youth lifecycle tracking + birthday alerts | 1 |
| Small groups management | 1 |
| Basic expenditure log | 1 |
| Automated WhatsApp weekly report to pastor | 0.5 |
| **Total** | **~5 sprints** |

---

## v3 — Scale Layer (Q3 2027+)

Not sprint-planned until v2 is stable. Gated on market demand and revenue.

| Feature | Trigger |
|---|---|
| Volunteer management | 3+ churches request it |
| Event management + ticketing | 3+ churches request it |
| Multi-branch / denomination | 1 denomination contract signed |
| Biometric attendance | 1 large church (500+) requesting it |
| Dedicated hosted instances | Enterprise contract signed |

---

## Slippage Policy

If a sprint story is not completed by the end of the sprint:

1. It moves to the top of the next sprint backlog (not buried at the bottom).
2. The retrospective discusses what blocked it — is the estimate wrong, was there a dependency, was the scope unclear?
3. If the same story slips twice, it is split into smaller stories.

Stories do not "almost done" carry over — they are either done (all DoD items checked) or they are not done.

---

## Sprint Ceremonies

| Ceremony | When | Duration | Who |
|---|---|---|---|
| Sprint Planning | Monday of sprint start | 1.5 hours | All team |
| Daily Standup | Daily (async via WhatsApp) | 5 min | All team |
| Sprint Review | Friday of sprint end | 45 min | All team + PO |
| Retrospective | Friday of sprint end (after review) | 30 min | All team |

**Async standups (WhatsApp format):**
```
Done: [what I completed yesterday]
Doing: [what I'm working on today]
Blocked: [anything blocking me / none]
```

---

## Current Sprint

> **Sprint 4** — Giving & Contributions
> Started: 2026-10-28 · Updated: 2026-11-07
> Sprint 3 (Attendance) is complete. The order of work for Sprint 4 is in D-046: cash giving first, digital payments after the legal and provider questions in D-018 are closed.

| Done | Stories |
|---|---|
| Backend | 4.2 fund types (list, create, change, switch off; seven defaults for a new church), 4.8 `SendSmsJob` (done in Sprint 1, on PayPerlez) |
| | 4.3 cash contributions (record, list, show; `giving.view` added), 4.11 void, the `Idempotency-Key` for writes that record money |
| | 4.9 pledges (make, list, read, change), 4.10 the paid amount and status that follow the gifts and voids (recalculated, D-047), the daily `pledges:refresh` job |
| | 4.12 Sunday offering report (by fund and payment method, voided gifts left out; gifts with no service reported with the day's first service; fund names kept on the gift, D-048) |
| | 4.7 receipts (the `ContributionReceived` event and a queued listener that texts the member), and SMS credits charged for church-paid texts (D-051) |
| Frontend | 4.13 gift list with filters and void, 4.14 record a cash gift (D-057), 4.16 offering report page (D-058), 4.17 pledges page (D-059) |
| | The pledge a gift pays can be chosen in the record-a-gift form (D-060) |
| | 4.18 SMS credit balance in the header (D-061) |
| Next | The cash slice of Sprint 4 is finished. What is left waits on the legal opinion and PayPerlez's event names: 4.1, 4.4, 4.5, 4.6 and 4.15 (see `19-open-items.md`) |
| Waiting | 4.1, 4.4, 4.5, 4.6: digital payments and the payout (legal opinion, PayPerlez event names, Paystack account) |

> **Sprint 3** — Attendance (finished). The design is in D-044 and D-045. Added later: weekly service schedules and cancelling a service (D-050, screens included).

| Done | Stories |
|---|---|
| Backend | 3.1 services, 3.2 manual bulk check-in, 3.3 WhatsApp CHECKIN (D-045; the Meta driver is built from the docs and not yet tried against Meta), 3.4 QR check-in, 3.5 idempotent check-in, 3.6 visitor capture, 3.7 attendance reports (per service and per member), 3.13 absent members (computed on request) |
| Frontend | 3.8 services list and create, 3.9 live check-in (polls every 10 seconds), 3.10 manual check-in with member search, 3.11 QR display page and the page a member lands on, 3.12 attendance report (the Attendance tab) |
| Still open | Nothing in the sprint. Waiting on the Meta account: try the `meta` driver against a sandbox number (R2) |

Update this section at the start of each sprint.
