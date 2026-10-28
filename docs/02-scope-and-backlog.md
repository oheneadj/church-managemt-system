# Scope & Backlog

> This document defines what we are building, what we are explicitly not building, and the full feature backlog ordered by version. It is the contract between product and engineering.

---

## Versioning Philosophy

We ship in three major versions. Each version is a coherent, shippable product — not a partial feature set.

- **v1 (MVP):** The core loop. A church can onboard, manage members, record attendance, receive and track contributions, and communicate with members. This is enough to replace paper registers and WhatsApp chaos.
- **v2:** The relationship layer. Pastoral care, youth tracking, small groups, expenditure logging. Turns the admin tool into a pastoral tool.
- **v3:** The scale layer. Denominations, biometrics, volunteer scheduling, enterprise instances.

---

## v1 — MVP

**Goal:** Replace the paper register and the Sunday offering envelope. Everything a church needs to run its Sunday operations digitally.

**Target launch:** 8–10 weeks from scaffold.

---

### Module 1: Church Onboarding & Setup

| Story | Role | Acceptance Criteria |
|---|---|---|
| Register a new church | Super Admin / Self-serve | Church name, denomination, location, contact captured. Subdomain auto-assigned. Admin account created. Welcome email sent. |
| Configure church profile | Church Admin | Logo, address, service times, contact info editable. Changes reflect on church portal immediately. |
| Invite staff members | Church Admin | Admin enters name, phone, role. System sends OTP-based invitation. Staff sets up account on first login. |
| Manage subscription plan | Church Admin | Admin can view current plan, SMS credit balance, and link to upgrade. Plan stored as `starter`, `growth`, or `pro`. |

**Out of scope for v1:** Custom domain mapping, multi-branch setup, billing portal.

---

### Module 2: Member Directory

| Story | Role | Acceptance Criteria |
|---|---|---|
| Add a new member | Church Admin, Pastor | Name, phone, gender, date of birth, address, photo, marital status captured. Member assigned a unique ID. |
| Link members into a family | Church Admin | Two or more members can be grouped under a family record. Head of household designated. |
| Search and filter members | Church Admin, Pastor | Search by name, phone. Filter by gender, marital status, membership status, department. |
| View a member profile | All roles | Full profile visible including attendance history, giving history, and pastoral care notes (role-permissioned). |
| Edit a member record | Church Admin | All fields editable. Change log maintained. |
| Mark a member as inactive | Church Admin | Inactive members excluded from default views but remain in DB. Reactivatable. |
| Upload member photo | Church Admin | Photo stored in Cloudflare R2. Displayed on profile and directory. |
| Export one member's data / erase a member's personal data | Church Admin | JSON export of everything held (`members.export`); erasure removes personal details but keeps the record (`members.erase`, D-039). |
| Export member list | Church Admin, Finance Secretary | CSV export of filtered member list. `members.export` permission required. |

**Out of scope for v1:** Biometric enrollment, member self-service profile editing.

---

### Module 3: Attendance

Three check-in methods are supported so that every member is covered regardless of phone type:

| Method | Who it serves | Presence verified? |
|---|---|---|
| WhatsApp CHECKIN | Members with WhatsApp | No — trust-based. Member texts "CHECKIN" from anywhere. |
| QR code scan | Members with any smartphone camera | Yes — QR is displayed at the venue only. Requires physical presence. |
| Staff manual check-in | Members without smartphones, elderly, visitors | Yes — staff or usher at the door marks them present. |

No member is excluded. Staff check-in is the universal fallback.

| Story | Role | Acceptance Criteria |
|---|---|---|
| Create a service / event | Church Admin | Service name, date, time, type (Sunday, midweek, special). Service saved to calendar. |
| Record attendance manually | Church Admin | Admin selects service, marks members present by name or ID. Bulk entry supported. This is the fallback for members without smartphones. |
| WhatsApp CHECKIN | Member | Member sends "CHECKIN [code]" to church WhatsApp number. System matches by phone number, records attendance for the active service. Confirmation sent back. Trust-based — member does not need to be physically present to send the message. |
| QR code check-in | Member | Church displays a rotating QR code on screen at the venue. Member scans with phone camera. Attendance recorded. Requires physical presence at the venue. |
| View attendance report | Church Admin, Pastor | Per-service headcount broken down by check-in method. Per-member attendance rate over last 4/8/12 weeks. Members absent for 3+ consecutive weeks flagged. |
| First-time visitor capture | Church Admin | Visitor form collects name, phone, how they heard about the church. Visitor record created separately from member. |

**Out of scope for v1:** Biometric check-in, department-level attendance sub-tracking.

---

### Module 4: Giving & Contributions

| Story | Role | Acceptance Criteria |
|---|---|---|
| Record cash contribution | Finance Secretary | Select member (or anonymous), amount, fund type (tithe, offering, pledge, special), service/date. Saved immediately. |
| Initiate MoMo contribution | Finance Secretary, Member | Paystack payment link triggered. Member pays via MTN MoMo / Telecel / AirtelTigo / card. Webhook confirms payment. Record auto-created. |
| USSD giving **(deferred past MVP, D-019)** | Member | Member dials `*713*[churchcode]#`. Selects fund type, enters amount, confirms. USSD provider webhook creates contribution record. SMS receipt sent. Works on any phone — no internet required. |
| Create a pledge | Church Admin, Finance Secretary | Member commits to give X amount by a target date. Pledge tracked against contributions. |
| View giving summary | Finance Secretary, Pastor | Total giving by fund type for a date range. Top givers (anonymised for pastor view, named for finance view). |
| Sunday offering report | Finance Secretary | One-click report for a specific service: total cash, total MoMo, total by fund type. Exportable to PDF. |
| Export giving records | Finance Secretary | Full contribution history for a member or a date range, CSV. `giving.export` permission required. |
| Platform giving fee | Automatic | Paystack subaccount routes platform fee % to platform account on every processed transaction. Transparent to church. |

**Out of scope for v1:** Full pledge campaign management, recurring giving setup, expenditure recording, church accounting.

---

### Module 5: Communications

| Story | Role | Acceptance Criteria |
|---|---|---|
| Send SMS broadcast | Church Admin | Write message, select recipients (all members / by filter / by group). Africa's Talking sends. SMS credit deducted. Delivery status logged. |
| Send WhatsApp broadcast | Church Admin | Write message, send via Meta Business API to opted-in members. Delivery logged. |
| Send automated attendance alert | System | After each service, members absent for 3+ consecutive weeks automatically receive a pastoral care SMS/WhatsApp. Configurable on/off per church. |
| Send giving receipt | System | On every recorded contribution (MoMo or cash), member receives SMS confirmation: amount, fund, date, running total this month. |
| View message log | Church Admin | Full history of all outbound messages with status (sent, delivered, failed) and recipient. |
| Top up SMS credits | Church Admin | Admin purchases a top-up bundle via Paystack. Credits added to church balance immediately. |

**Out of scope for v1:** Email campaigns, scheduled broadcasts, two-way SMS conversations.

---

### Module 6: Reports & Dashboard

| Story | Role | Acceptance Criteria |
|---|---|---|
| Home dashboard | All roles | Role-appropriate summary: total members, last Sunday attendance, this month's giving total, recent activity feed. |
| Attendance trends chart | Church Admin, Pastor | Weekly attendance for last 12 weeks. Trend line. Highlight anomalies (e.g. unusual drop). |
| Giving trends chart | Finance Secretary | Monthly giving for last 12 months by fund type. Total vs. previous period. |
| Member growth report | Church Admin | New members added per month. Inactive members per month. Net active member count over time. |

---

### Module 7: Authentication & Access Control

| Story | Role | Acceptance Criteria |
|---|---|---|
| Staff login | All staff roles | Phone number + OTP via SMS. Token-based session (Sanctum). Session expires after inactivity. |
| Member login | Member | Phone number + OTP via SMS or WhatsApp. Limited portal: own profile, own giving history, check-in. |
| Role assignment | Church Admin | Admin assigns roles to staff. Roles: `church-admin`, `pastor`, `finance-secretary`, `department-head`, `youth-pastor`. |
| Granular permission override | Church Admin | Admin can grant or revoke specific permissions on top of role defaults. Powered by Spatie Laravel Permission. |
| Revoke access | Church Admin | Admin can deactivate a staff account. Token invalidated immediately. |

---

## v2 — Relationship Layer

**Target:** 3–4 months after MVP launch, based on user feedback.

| Module | Stories (Summary) |
|---|---|
| **Pastoral Care** | Log pastoral visits, record prayer requests, set follow-up reminders, track member welfare flags (sick, bereaved, in hospital). |
| **Youth Lifecycle Tracking** | Assign Ghana educational level (Crèche → KG → Primary → JHS → SHS → Tertiary) to members. Track confirmation, baptism, dedication. Birthday alerts. Age-group promotion. |
| **Small Groups** | Create cell groups / house fellowships. Assign members and leaders. Record small group attendance separately. |
| **Expenditure Log** | Record outgoing payments (type, amount, recipient, description, receipt photo). Basic income vs. expense summary. Not accounting — just a log. |
| **Automated WhatsApp Reports** | Pastor receives weekly WhatsApp digest: attendance summary, top giving categories, members flagged for follow-up. Configurable day/time. |

---

## v3 — Scale Layer

**Target:** 9–12 months post-MVP, driven by market demand.

| Module | Stories (Summary) |
|---|---|
| **Volunteer Management** | Roster volunteers per ministry/department. Scheduling (who serves this Sunday). Availability tracking. Automated reminders. |
| **Event Management** | Create events beyond regular services. Ticketing (free or paid via Paystack). RSVP tracking. Event-specific communications. |
| **Multi-Branch / Denomination** | One account for a denomination. Branch-level data. Central reporting across branches. Branch admin roles. |
| **Biometric Attendance** | Integration with fingerprint/face scanner hardware for large-church accountability. Premium tier feature. |
| **Dedicated Hosted Instances** | For Enterprise customers: isolated DB, isolated instance, custom domain, premium SLA. Still our infrastructure. |

---

## Explicitly Out of Scope (All Versions)

These will not be built. If a stakeholder requests them, refer to this document and the decision log.

| Feature | Reason |
|---|---|
| Double-entry church accounting | Liability risk. Churches have accountants. |
| Native iOS / Android app | PWA covers the use case. App stores slow us down. |
| Video / live streaming | Not a church management problem. |
| USD pricing or multi-currency | GHS only. Currency trust is a feature. |
| Self-hosted / packaged software (v1, v2) | SaaS only. Standalone is v3 Enterprise, contract-only. |
| Built-in payroll | Out of scope. Use a payroll tool. |
| Sermon library / media hosting | Out of scope. Use YouTube or SoundCloud. |

---

## Backlog Prioritisation Criteria

When adding new features to the backlog, score them against these four questions:

1. **Will this make a church admin's Monday morning easier?** — Direct operational value.
2. **Does this generate or protect revenue?** — Giving features, subscription features, retention.
3. **Does a competitor have this and we don't?** — Competitive parity where it matters.
4. **Can we build it in under 2 weeks?** — If not, is the scope right?

A feature must score positively on at least two of these to enter the active backlog.

---

## Sprint Structure

Each sprint is **two weeks**. Every sprint ends with a deployable build — no "almost done" carries over.

Sprint planning uses this sequence:
1. Review open bugs from the previous sprint (fix first)
2. Review items from the active version backlog
3. Pull items that fit the sprint capacity
4. Every sprint includes at least one item that improves developer experience (tooling, docs, test coverage)

See [`11-roadmap-and-sprints.md`](./11-roadmap-and-sprints.md) for the detailed sprint plan.
