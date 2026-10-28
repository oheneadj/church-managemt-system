# Changelog

All notable changes to this project are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

> Changes staged but not yet in a versioned release.

### Changed
- Database is now PostgreSQL instead of MySQL; data model, CI, backup and restore docs updated (D-021)
- React SPA now lives in the Laravel repo (`resources/js`) behind one Blade shell view, no Inertia; replaces the separate `chms-frontend` repo (D-022, amends D-008)
- Local development is native (PostgreSQL 18, Redis 8, no Docker); staging and production stay on Docker via Coolify (D-023)
- Primary keys are now ULIDs (`CHAR(26)`) instead of UUIDs (`CHAR(36)`) across the data model, API reference, security and QA docs (D-016)
- Fixed-value fields are now `VARCHAR(30)` columns backed by PHP enums instead of native MySQL `ENUM` columns; money stays `DECIMAL(12,2)` in GHS (D-017)
- MoMo/USSD gifts are now paid out to churches automatically per transaction by the platform, with the platform fee deducted; cards keep the Paystack split. Added the `disbursements` table and church payout fields (D-018)
- Platform fee on MoMo/USSD gifts set to 1% capped at GHS 10.00 with a GHS 0.10 floor; minimum digital gift GHS 1.00, maximum GHS 30,000 per transaction (D-018)
- USSD giving (and the Moolre integration) deferred past the MVP; MoMo through PayPerlez is the only MoMo provider at launch (D-019)
- Provider abstraction reworked: PayPerlez is the default for SMS, OTP, MoMo and payouts, Paystack is cards only, Africa's Talking is the SMS fallback. Amounts crossing provider interfaces are decimal strings (D-020)
- README now describes the React 19 frontend (was still listing Vue 3)

### Planning
- Completed full documentation set (docs/01 through docs/17, qa/, reference/)
- Defined 5-sprint MVP plan covering Foundation through Communications & Polish
- Finalised tech stack, data model, and API contract

---

## Pre-release History

### [0.0.1] — 2026-10-23 — Project initialised

#### Added
- Project documentation scaffolded (all 17 planning docs)
- Product vision, personas, and success metrics defined
- Full data model designed (16 tables, UUID PKs, multi-tenancy via `church_id`)
- REST API contract documented (auth, members, attendance, giving, communications, reports)
- Architecture decisions logged (D-001 through D-014)
- Risk register created (R1–R10) and open questions catalogued (Q1–Q9)
- Sprint backlog defined (Sprint 1–5, 77 stories, ~165 story points)
- QA test plan templates created
- Definition of Done established
- Engineering principles and code standards documented
- Security controls and threat model documented
- Deployment runbooks and monitoring thresholds established

---

## Versioning Convention

Once the platform ships, versions follow this pattern:

```
v1.0.0 — MVP launch (Sprint 5 complete, first church live)
v1.x.x — Bug fixes and minor improvements (v1 stabilisation sprints)
v2.0.0 — Relationship Layer launch (pastoral care, youth tracking, small groups)
v2.x.x — Relationship Layer improvements
v3.0.0 — Scale Layer launch (volunteers, events, multi-branch)
```

**API versioning:** Breaking API changes increment the major version. The API version is embedded in the URL (`/api/v1/...`) — clients that haven't updated continue to work on the old version for 6 months after a breaking change.

---

## How to Add a Changelog Entry

When you merge a PR that ships a feature, bug fix, or change:

1. Add an entry under `[Unreleased]`
2. Use one of these change type headings:
   - **Added** — new feature or capability
   - **Changed** — change to existing behaviour
   - **Fixed** — bug fix
   - **Removed** — removed feature or capability
   - **Security** — security fix or improvement
   - **Deprecated** — feature that will be removed
3. Write one line per change, in plain language that a church admin could understand
4. Reference the issue or PR number where possible

When a sprint ends and we cut a release, the `[Unreleased]` section is tagged with the version number and date, and a new empty `[Unreleased]` section is added at the top.
