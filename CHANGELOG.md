# Changelog

All notable changes to this project are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

> Changes staged but not yet in a versioned release.

### Added
- Youth age limit: the youth pastor's view goes by date of birth (younger than the church's `youth_age_limit` setting, default 25), falling back to educational level when there is no birth date (D-031)
- Member photo upload (story 2.4): `POST /api/v1/members/{id}/photo` checks the file's real type and size, rotates, crops and re-encodes it (dropping EXIF/GPS data), stores it in the private bucket and returns a signed link; `photo_url` is now on the member record and directory. Adds `intervention/image` and the `gd` and `exif` PHP extensions (D-032)
- Member milestones (story 2.9): `POST /api/v1/members/{id}/milestones`; milestones show on the member record only for roles with the new `milestones.view` permission
- Department endpoints (story 2.8): list, create and update departments, add and remove members; adding twice only changes the role, removing keeps the history
- Family endpoints (story 2.7): list, show, create and rename families, with an optional first head; counts and member lists respect each role's directory view
- Member directory API (stories 2.2, 2.3, 2.5, 2.6): `GET/POST /api/v1/members`, `GET/PUT /api/v1/members/{id}` and `PATCH /api/v1/members/{id}/status`, with `MemberPolicy`, form requests, `MemberService`, and list/full resources. Search, filters, sort and pagination; role-scoped visibility for department heads and youth pastors; one head per family; departments set with the member; every create, edit and status change in the change log with the reason (D-031)
- `members.deactivate` permission, held by the church admin only
- Sprint 2 groundwork (D-030): per-church member number prefix and a locked sequence so numbers like `GRC-0042` are unique and never reused, an append-only `audit_logs` table with an `AuditLogger` for member change history, and `members.edit` for the pastor role
- Docker and Coolify deployment (D-029): one image from `deploy/Dockerfile` with `web`, `worker` and `scheduler` roles chosen by `CONTAINER_ROLE`, a start script (env check, migrate, seed, optimize), role-aware health check, Supervisor config for the six queues, `.dockerignore`, a CI `image` job (hadolint, build, smoke check) and Dependabot for Docker; Coolify setup documented in `docs/16`
- Laravel 13 application scaffold in the repo root, alongside `docs/` (D-022)
- PostgreSQL migrations for all 16 v1 tables plus Sanctum, Spatie Permission and Telescope, all with ULID keys and working `down()` methods
- Pest, Pint (strict types enforced) and Larastan level 8; tests run against PostgreSQL (`chms_test`)
- 22 backed enums for every fixed-value column, Eloquent models (ULID keys, soft deletes, encrypted casts, `BelongsToChurch` tenancy trait) and factories
- `ResolveTenant` middleware (subdomain or custom domain, 404 when unknown or suspended), run before auth so tokens only resolve within their own church
- API is versioned from day one under `/api/v1` (D-024)
- OTP sign-in: `POST /api/v1/auth/otp/request`, `POST /api/v1/auth/otp/verify`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`. Codes are bcrypt-hashed in Redis per church, locked out after 5 misses, and sent by a queued `SendSmsJob` on the new `critical` queue (D-025)
- Tokens are revoked immediately when a staff member is deactivated or changes phone number; `php artisan otp:reset-lockout {church} {phone}` for support
- `SmsProvider` contract with a development-only log driver; the PayPerlez and Africa's Talking adapters come next
- Roles and permissions seeder, ULID-keyed `Role` and `Permission` models, `PhoneNumber` value object
- Tenancy and database-constraint tests (cross-tenant, partial unique indexes, double check-in)
- React 19 SPA in `resources/js` served through one Blade shell (`/{any?}` catch-all, 404 for unknown churches): passwordless OTP sign-in (React Hook Form + Zod), auth guard, sidebar app layout, church context and a dashboard placeholder. Sessions are httpOnly Sanctum cookies, so a refresh keeps the user signed in; bearer tokens remain for non-browser clients (D-027)
- Vitest + Testing Library suite (16 tests), ESLint 9 with jsx-a11y and react-hooks, Prettier, strict TypeScript
- shadcn/ui primitives (button, input, label, card) and the Tailwind v4 theme
- `GET /api/health` readiness probe (database, cache, queue, storage, disk) and Laravel's `/up` liveness probe; `X-Request-ID` on every response with the id, church and user added to log context and queued jobs; JSON log format for production; `php artisan env:check` and a fail-fast guard so a misconfigured deploy stops at start-up
- `ARCHITECTURE.md`: the backend and frontend layers, tenancy rules, auth, queues, providers and observability as built, with a checklist of where new code goes (D-028)
- GitHub Actions CI/deploy workflow (`.github/workflows/ci.yml`): backend and frontend checks on every PR, migration round-trip, dependency audits, Coolify deploys with a health smoke test, manual approval for production; weekly Dependabot PRs
- Cloudflare R2 disks (`r2`, `r2-backup`): private, throw on failure, no public URL, tested offline with locally signed URLs. The AWS SDK is pruned to S3 only (1 GB to 37 MB). `docs/14-integrations.md` corrected: R2 does not implement ACLs, and a real upload, read-back, signed link, tamper and expiry test against a dev bucket confirmed R2 ignores the ACL header and keeps files private
- Laravel Boost MCP server (`.mcp.json`, `boost.json`) with Laravel, testing, Telescope, Tailwind and convention-inference skills for Claude Code; the project's MCP usage is documented in `CLAUDE.md`
- `.env.example` rewritten for this stack (PostgreSQL, Redis, tenancy, SMS, health)
- `scripts/setup-dev-db.sh` for local Postgres role and databases

### Fixed
- Telescope is now registered only locally and excluded from auto-discovery, as the Laravel docs prescribe for a `--dev` package. Before, `bootstrap/providers.php` always loaded it, so a `composer install --no-dev` production build would have crashed on boot
- Behind Coolify's HTTPS proxy Laravel believed every request was plain HTTP. `config/trustedproxy.php` (default: the private Docker ranges, `TRUSTED_PROXIES`) makes it trust only the proxy
- `env:check` and the start-up guard also require an `https://` APP_URL, secure session cookies and no blanket proxy trust
- A session cookie from one church could be replayed on another church's host, because Sanctum loaded the user before the church was resolved. The tenant middleware now runs first, `AuthenticateSession` is off, and a test fails if the fix is removed (D-027)

### Changed
- API responses send a `_label` beside every enum value, nest Resources for related records, page lists through `MemberCollection`, `FamilyCollection` and `DepartmentCollection`, and show money through the new `Money` value object with a `_formatted` sibling (D-034)
- Opening a member's full record is written to the change log as `viewed`, because it shows the encrypted address (D-034)
- Sign-in, sign-out, the current-user endpoint, the OTP lockout command and the health check now go through Actions, DTOs and Resources too; every remaining direct model query (tenant lookup, OTP user lookup, message log) moved behind a Repository; repositories call `Model::query()->create()`, never static `Model::create()`; unit-test helpers build models with factories
- Members, families and departments now follow the Universal layer stack and the Laravel addendum in full (D-033): single-action controllers, DTOs, one Action per intent that owns the transaction and fires events, reusable Services, and a Repository interface per model. The member audit line is now written by a listener to `MemberCreated`, `MemberUpdated` and `MemberStatusChanged`. Form Requests authorise through Policies. Adds unit tests for every Action and Service using mocked repositories
- Member, family and department endpoints are now single-action controllers (one invokable class per route), with response shaping moved into resources and "member not in department" raised by the service
- OTP errors and lockout now follow QA-01 ("Invalid code"; the 5th wrong code returns 429); the ops runbook and queue lists include the `critical` queue
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
