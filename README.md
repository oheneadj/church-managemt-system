# Church Management System

A multi-tenant SaaS platform for mid-size churches in Ghana. Covers member management, attendance tracking, digital and cash giving, SMS/WhatsApp communications, and reporting — built specifically for the Ghanaian church context.

**Target:** Churches with 100–800 members. No IT staff. Works on a 3G connection.

---

## Quick Links

| What | Where |
|---|---|
| Product vision & personas | [docs/01-vision.md](docs/01-vision.md) |
| Feature scope & backlog | [docs/02-scope-and-backlog.md](docs/02-scope-and-backlog.md) |
| Data model (16 tables) | [docs/07-data-model.md](docs/07-data-model.md) |
| API reference | [docs/08-api.md](docs/08-api.md) |
| Architecture overview | [docs/06-architecture.md](docs/06-architecture.md) |
| Sprint plan | [docs/11-roadmap-and-sprints.md](docs/11-roadmap-and-sprints.md) |
| Definition of Done | [docs/10-definition-of-done.md](docs/10-definition-of-done.md) |
| Risks & open questions | [docs/12-risks-and-open-questions.md](docs/12-risks-and-open-questions.md) |
| Decision log | [docs/13-decision-log.md](docs/13-decision-log.md) |
| Deployment & ops | [docs/16-deployment-and-ops.md](docs/16-deployment-and-ops.md) |

---

## Tech Stack

| Layer | Choice |
|---|---|
| Backend | Laravel (latest stable) — REST API |
| Frontend | React 19 SPA in `resources/js` (same repo) — Vite, TypeScript, React Router v7, Zustand, TanStack Query v5, Tailwind CSS v4, shadcn/ui |
| Auth | Laravel Sanctum — OTP-only (no passwords) |
| Permissions | Spatie Laravel Permission |
| Database | PostgreSQL |
| Cache & Queues | Redis |
| File Storage | Cloudflare R2 |
| Payments | PayPerlez (MoMo collections + automatic church payouts); Paystack for cards (subaccount split) |
| SMS / OTP | PayPerlez (default), Africa's Talking (fallback) |
| WhatsApp | Meta Business API |
| Email | Resend → own SMTP |
| Infrastructure | Oracle ARM (Always Free) + Coolify |
| CI/CD | GitHub Actions → Coolify webhook |

---

## Repositories

```
chms   — Laravel API + React 19 SPA (resources/js). One repo, one deploy (D-022).
```

---

## Local Development Setup

### Prerequisites

- PHP 8.3+
- Composer
- Node 20+
- PostgreSQL 18 and Redis 8, installed natively (no Docker locally, D-023). Docker is used only for staging and production.
- A [PayPerlez test account](https://biz.payperlez.org/docs/introduction/) (SMS, OTP, MoMo collections and payouts)
- A [Paystack test account](https://dashboard.paystack.com/#/signup) (only needed once card payments are built)
- An [Africa's Talking sandbox account](https://account.africastalking.com/auth/register) (optional, SMS fallback)

### Backend (Laravel API) and frontend (React SPA)

```bash
# 1. Clone and install
git clone git@github.com:yourorg/chms.git
cd chms
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure .env — minimum required for local dev:
# DB_CONNECTION=pgsql, DB_DATABASE, DB_USERNAME, DB_PASSWORD
# REDIS_HOST=127.0.0.1
# PAYPERLEZ_API_KEY, PAYPERLEZ_WEBHOOK_SECRET (PayPerlez test key)
# PAYSTACK_SECRET_KEY (Paystack test key, cards only)
# AT_API_KEY, AT_USERNAME (Africa's Talking sandbox, optional fallback)

# 4. Database
php artisan migrate
php artisan db:seed   # Seeds demo church + admin user

# 5. Queue workers (run in a separate terminal)
php artisan queue:work redis --queue=critical,payments,notifications,reports,exports,scheduled

# 6. Start
php artisan serve
# API available at http://localhost:8000
```

### Frontend dev server

The SPA is in `resources/js` and is built by Vite through Laravel. Run it beside `php artisan serve`:

```bash
npm run dev
# Vite dev server on http://localhost:5173 with hot reload; open the app via http://demo.localhost:8000
npm run build   # production assets into public/build
```

### Subdomain Routing (Local)

The multi-tenant setup resolves churches by subdomain (e.g. `grace-assembly.yourapp.com`). For local development, add entries to `/etc/hosts`:

```
127.0.0.1  demo.localhost
127.0.0.1  testchurch.localhost
```

Then set `APP_URL=http://localhost:8000` and `SESSION_DOMAIN=.localhost` in `.env`.

---

## Running Tests

```bash
# All tests
php artisan test

# Parallel (faster)
php artisan test --parallel

# Specific suite
php artisan test --testsuite=Feature

# Single file
php artisan test tests/Feature/Giving/ContributionTest.php

# With coverage (requires Xdebug or PCOV)
php artisan test --coverage --min=80
```

**Every new endpoint requires a cross-tenant isolation test.** See [docs/09-engineering-principles.md](docs/09-engineering-principles.md).

---

## Code Standards

- **Style:** Laravel Pint (`./vendor/bin/pint`) — enforced in CI
- **Static analysis:** PHPStan level 6 — enforced in CI
- **Commits:** Conventional Commits — `feat(module): description`
- **Branches:** `feat/short-description`, `fix/short-description`
- **PRs:** Must pass CI. Requires review for financial/auth code.

See [docs/09-engineering-principles.md](docs/09-engineering-principles.md) for full standards.

---

## Project Documentation

```
docs/
├── PROJECT_CONTEXT.md          — One-page project brief
├── 01-vision.md                — Product vision & personas
├── 02-scope-and-backlog.md     — User stories & sprint backlog
├── 03-member-lifecycle.md      — Member data & lifecycle
├── 04-giving-and-contributions.md — Giving flows, fees & payouts
├── 05-data-safety.md           — Data protection & retention
├── 06-architecture.md          — System architecture
├── 07-data-model.md            — Database schema (16 tables)
├── 08-api.md                   — Full API reference
├── 09-engineering-principles.md — Code standards & patterns
├── 10-definition-of-done.md    — Ship checklist
├── 11-roadmap-and-sprints.md   — Sprint plan & roadmap
├── 12-risks-and-open-questions.md — Risk register
├── 13-decision-log.md          — Architecture decision log
├── 14-integrations.md          — Third-party integration guide
├── 15-security.md              — Security controls & threat model
├── 16-deployment-and-ops.md    — Deployment & ops runbooks
├── 17-monitoring-and-alerting.md — Monitoring & on-call guide
├── qa/                         — QA test plans & tracker
└── reference/                  — Onboarding guide & post-mortems
```

---

## Key Decisions

The full decision log is in [docs/13-decision-log.md](docs/13-decision-log.md). The short version:

- **SaaS only** — no self-hosted option in v1 or v2
- **Single-database multi-tenancy** — `church_id` on every table, global scopes
- **OTP-only auth** — no passwords; 6-digit SMS code, 10-minute expiry
- **GHS only** — all amounts in Ghana Cedis, no currency conversion
- **PayPerlez** for MoMo collections and automatic church payouts, platform fee deducted per transaction (D-018); **Paystack** for cards only
- **PayPerlez** for SMS and OTP (Africa's Talking as fallback); **Meta Business API** for WhatsApp
- USSD giving (Moolre) is deferred past the MVP (D-019)
- **Oracle ARM Always Free** + **Coolify** for zero-cost infrastructure

---

## Contributing

1. Pick a story from the sprint backlog ([docs/11-roadmap-and-sprints.md](docs/11-roadmap-and-sprints.md))
2. Check the Definition of Done ([docs/10-definition-of-done.md](docs/10-definition-of-done.md)) before you start
3. Create a branch: `feat/module-short-description`
4. Write code + tests (cross-tenant test required for every new endpoint)
5. Run `./vendor/bin/pint` and `php artisan test` before pushing
6. Open a PR — CI must pass before merge

---

## Open Questions

See [docs/12-risks-and-open-questions.md](docs/12-risks-and-open-questions.md) for the full list. The blockers before Sprint 1:

- **Q1:** Product name (needed for domain, sender ID, branding)
- **Q2:** Primary domain
- **R2:** Apply for Meta WhatsApp Business account NOW — approval takes days to weeks
- **R3:** Start SMS sender ID registration (PayPerlez / Africa's Talking) NOW — takes 2–6 weeks
