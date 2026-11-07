# Open Items

> One list of everything that is **decided but not built**, **waiting on someone outside**, or **still to be decided**. It exists so nothing agreed in a conversation is lost. When an item is built or settled, move it to "Closed" with the commit or decision. Last reviewed: 7 November 2026 (A1 closed).

Decisions live in `13-decision-log.md`; risks and product questions live in `12-risks-and-open-questions.md`. This file is the work list that connects them.

---

## A. Decided, not built

| # | Item | Decision | What is needed | Blocked by |
|---|---|---|---|---|
| A3 | **Digital giving: MoMo through PayPerlez with automatic payout** | D-018, D-020, D-053 | Migration for `contributions.status`; `POST /contributions/initiate`; `PaymentProvider` and `DisbursementProvider` drivers; webhook and polling fallback; `disbursements` flow, platform fee, GHS 1 minimum balance; every existing sum, list, pledge recalculation, report and receipt filters on `completed`; church payout setup and account-name check (story 4.1); rewrite QA-06 TC-06-06 to 10, QA-14 E2E-09; run QA-15 | B1, B2, B3 |
| A4 | **Card giving through Paystack** | D-020 | Card driver, subaccount, webhook, `charge.success` completing the pending row; rewrite QA-07 | B5, and A3 first |
| A5 | **Staff management** | D-055 | **Built:** all five endpoints, the React staff page (5.15) and the `/choose-password` page where an invited person uses their invitation code. **The extra permissions screen is built too** (a Permissions button on each staff member). **Left:** only a deny list, if a church ever needs to take a role permission from one person without changing their role (decided against in D-055) | Nothing |
| A6 | **Reports, exports, broadcasts, settings endpoints (Sprint 5)**, including **buying SMS credits (story 5.4)**, which replaces the Top up notice in the header (D-061) | docs/08, QA-10, QA-11 | (the three reports, `reports/attendance`, `reports/giving` and `reports/members`, are built) (both CSV exports, `exports/members` and `exports/contributions`, are built, D-064), SMS broadcasts (`messages/broadcast` and its estimate, D-065, and the `GET /messages` log are built; WhatsApp broadcasts, 5.2, wait for Meta, B4), (`church/settings` and the church profile `GET` and `PUT /church` are built; the logo upload is not); the automatic text to absent members is built, D-067; the Settings page (5.14) is built | Nothing |
| A9 | **Recent activity feed on the dashboard** | docs/02 Module 6 ("recent activity feed") | **The endpoint is built** (`GET /activity`, D-068: plain sentences from the change history, each kind shown only to roles that hold its permission). **Left:** the feed on the home page. The summary cards are built | Nothing |
| A7 | **Africa's Talking as the SMS fallback** | D-020 | A second `SmsProvider` driver | Nothing (not urgent) |
| A8 | **USSD giving and Moolre** | D-019 | Revisit when a church asks | A church asking |

---

## B. Waiting on something outside the code

| # | Item | Source | Owner |
|---|---|---|---|
| B1 | **Legal opinion** on the Bank of Ghana position for receiving and forwarding third-party funds, even briefly | D-018 open item 1, D-046 | Product Owner |
| B2 | **PayPerlez webhook event names and payload shape** (polling covers us until then) | D-018 open item 5 | Product Owner, ask PayPerlez |
| B3 | **PayPerlez live collection fee schedule**, to show a fee estimate before the giver pays (until then: "plus a small processing fee") | D-018 open item 3 | Product Owner |
| B4 | **Meta approval** of the WhatsApp account; the `meta` driver is untested against Meta | D-045, R2 | Product Owner |
| B5 | **Card split and fee design** (can the GHS 10.00 cap and 0.10 floor apply to cards?) | D-018, `14-integrations.md` | Product Owner |
| B6 | **Written confirmation from PayPerlez** of immediate fund availability (confirmed by phone) | D-018 | Product Owner |

---

## C. Still to decide

| # | Question | Notes |
|---|---|---|
| C1 | **Platform fee rounding mode** | D-018 says "round to 2 places" only; QA-15 assumes half up. Record the mode in D-018 when the fee code is written, and set it in one place |
| C2 | Product name, domain, subscription prices, SMS credits per plan, custom sender ID | Q1 to Q8 and DD-001 to DD-006 in docs/12 and docs/13 |
| C3 | Cash receipts on by default or opt-in | Q9; D-051 set on by default and says the owner may still change it |
| C4 | Language of the screens | DD-009 |
| C5 | Delivery status of text messages (polling or webhook) | DD-008 |
| C6 | Signing the new admin in straight after sign-up | DD-007 |
| C7 | The default-on SMS agreement for new members | D-041 point 3 is an owner-accepted exception to agreement section 36; revisit with the privacy policy text |

---

## D. Known gaps to close before launch

| # | Gap | Source |
|---|---|---|
| D1 | No general feature-flag system; each risky feature (broadcasts, payments) needs its own off switch like `sms:pause` | D-043 |
| D2 | The runbooks and the full database restore have not been rehearsed (no staging yet); Coolify rollback untried | D-043 |
| D3 | The React app is not instrumented for error reporting; CSP violation reporting (`report-to`) is not set up | D-040, D-056 |
| D4 | The credit-low email to the church admin, the read-only grace period and "your data will be deleted on…" notice | D-051, D-042 (with billing, Sprint 5) |
| D5 | Church-wide CSV exports, the 90-day purge notice and the self-service cancel button | D-039, D-042 |
| D6 | `npm audit`: 12 moderate and low findings in dev tools (`@typescript-eslint`, the `shadcn` CLI); below the CI gate of high | CHANGELOG, 6 November |
| D7 | Anonymising visitor records on erasure (a visitor with a name and phone is personal data) | D-039 point 6 |
| D8 | Payout, fee and reconciliation tests are written (QA-15) but have no code behind them | QA-15 |
| D11 | **Email deliverability set-up**, outside the code: a dedicated sending subdomain, SPF, DKIM and DMARC in DNS, the domain verified in the Resend account, and `MAIL_FROM_ADDRESS` on it. Required before the first real email | D-062, docs/14 section 4 |
| D12 | **Bounce and complaint handling**: a Resend webhook, a suppression list checked before every send, and the bounce-rate alert in docs/14 | D-062, the email pattern sections 4 and 7 |
| D13 | The string queue names in older code (`'critical'`, `'notifications'`) can move to the `QueueName` enum when those files are next touched | D-062 |
| D10 | The local `public/hot` file points at port 5175 where nothing runs, so a developer who opens the site without `npm run dev` gets a blank page and no hint why | found 6 November |

---

## E. Standing deviations from the Universal agreement

Accepted by the Product Owner and recorded, so they are not mistaken for oversights. Revisit only if the owner says so.

| Agreement rule | What we do | Decision |
|---|---|---|
| Section 5: integer primary key plus an external ULID | ULID `CHAR(26)` as the only key | D-016 |
| Section 12: money as integers in the smallest unit | `DECIMAL(12,2)` in GHS, decimal strings in the API, a `Money` value object for display | D-017, D-034 |
| Section 36: consent must not be pre-ticked | New members are texted by default, recorded with time and policy version; WhatsApp is off by default | D-041 |

---

## Closed

Move finished items here with the commit hash.

| Item | Closed by |
|---|---|
| A10: setting a department's head (a read-only staff directory for `members.edit`, the head's name on departments, and a head choice on the Departments screen that never changes the head unless it is changed) | Commits `527ef38` and `2bc33b0`, 9 November 2026 |
| D9: every screen scrolled sideways at 375px (the menu links did not wrap) | The menu wraps; `e2e/mobile-layout.spec.ts` checks five pages in real Chrome, 6 November 2026 |
| A1: staff sign in with email, password and an emailed code (migrations, the four auth endpoints and limiters, sign-up fields, React sign-in and forgot-password screens, demo admin, e2e, removal of the phone and SMS staff endpoints, `staff:reset-lockout`, QA-01 and QA-12 rewritten, `users.email` required) | D-054, 7 November 2026 |
| A2: the email sender (`QueueEmailAction`, `SendEmailJob`, circuit breaker, `email:pause`, runbook, `env:check`) | D-062, 7 November 2026 |
| Seven consistency issues (frontend framework, IDs and money, providers, data model vs API vs QA, authentication, permissions, smaller items) | Commits `4ec5191` to `d98e0fb` on 6 November 2026 |
