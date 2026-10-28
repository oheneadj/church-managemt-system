# Architecture

> The layers of this codebase, what each owns, and what it must never do. Read this before adding code, and reference it in every AI session. It describes what exists today; when the structure changes, change this file in the same commit. Longer background lives in `docs/06-architecture.md`; decisions and their reasons live in `docs/13-decision-log.md`.

## The shape of the system

One Laravel 13 application serves two things from one repository (D-022):

- **The API**, under `/api/v1/*`: JSON only, versioned from day one (D-024).
- **The React app** in `resources/js`, served through one Blade view (`resources/views/app.blade.php`) and a catch-all route in `routes/web.php`. The app only ever talks to the API; there is no Inertia.

One PostgreSQL database holds every church. A row belongs to a church through `church_id` (D-007, D-021). Redis carries queues, cache and sessions. Files live in Cloudflare R2. Staging and production run in Docker behind Coolify; development is native (D-023).

## Backend layers

This is the Universal agreement's layered stack (section 6) and the Laravel addendum's layer rules, followed in full (D-033).

```
Route                routes/api.php, routes/health.php, routes/web.php
  ↓
Middleware           tenant, auth, request id, rate limits
  ↓
Controller           app/Http/Controllers   single-action: receive, build a DTO, call one Action, return a Resource
  ↓
Form Request         app/Http/Requests      validates and normalises input; authorize() asks a Policy
  ↓
DTO                  app/DTOs               typed input for one operation, built with fromRequest()
  ↓
Action               app/Actions            one user intent; execute(DTO); owns the transaction; fires events
  ↓
Service              app/Services           reusable rules shared by several Actions; no transactions
  ↓
Repository           app/Repositories       every query for one model, behind an interface
  ↓
Model + Scope        app/Models, app/Scopes structure, relationships, casts, scopes, tenant scope
  ↓
PostgreSQL

Response shaping     app/Http/Resources     every API body goes through a Resource
Authorisation        app/Policies           decisions only, reached through Form Request authorize()
Side effects         app/Events, app/Listeners, app/Jobs   events are fired by Actions; slow or external work is queued
Outside world        app/Contracts + app/Services/<Area>   third parties sit behind an interface
```

| Layer | Owns | Must never contain |
|---|---|---|
| Controller | HTTP in and out: one `__invoke`, one Action call | Business rules, queries, validation, authorisation, `$request->validate()` |
| Form Request | Validation, input normalisation (phone numbers), `authorize()` delegating to a Policy | Business logic, hard-coded role checks |
| DTO | The typed data one operation needs, built by `fromRequest()` | Behaviour beyond mapping its own fields |
| Action | One operation, `final`, a single public `execute()`, `DB::transaction()`, firing events | `$request`, HTTP responses, queries (use a Repository), calls to other Actions |
| Service | Rules reused by several Actions (member numbers, family rules, photo processing) | Transactions, `$request`, HTTP, provider SDKs |
| Repository | All Eloquent queries for one model; interface plus `Eloquent…` class, bound in `RepositoryServiceProvider` | Business rules |
| Model | Casts, relationships, scopes, fillable list | Business logic, HTTP, queues |
| Resource | The exact shape of each API response | Queries (use `whenLoaded`), authorisation decisions |
| Event / Listener | One side effect per listener; the audit line for a member change | Anything that must finish before the Action returns, unless it runs inside the transaction on purpose |
| Job | One unit of async work with `tries`, `timeout`, `backoff`, `failed()` | Anything that must finish before the response |
| Contract + driver | One third-party capability behind an interface, chosen by config | Provider-specific errors or shapes leaking out |
| Policy | Authorisation decisions only | Everything else |

Names follow the addendum: `CreateMemberAction`, `CreateMemberDTO`, `MemberRepositoryInterface` and `EloquentMemberRepository`. A controller never calls a Repository, and an Action never calls another Action; shared steps become a Service. A model reaches an Action through explicit route model binding (`AppServiceProvider::boot`), and another church's id is a 404 because the church scope hides it. Unit tests for Actions and Services use mocked repositories and no database (`tests/Unit`); Feature tests cover each endpoint end to end.

Value objects (`app/ValueObjects`, for example `PhoneNumber`) replace primitive strings that carry rules. DTOs (`app/DTOs`, for example `OutboundSms`) move typed data into queued jobs. Enums (`app/Enums`) back every fixed-value column; the column is a plain string, never a database enum (D-017).

## Tenancy (the highest-risk rule)

- Every model with a `church_id` uses `BelongsToChurch`, which applies `ChurchScope` and fills `church_id` on create.
- `ResolveTenant` finds the church from the host (`{slug}.{TENANT_BASE_DOMAIN}` or a custom domain) and binds `current.church`. It is **first** in the middleware priority list, ahead of Sanctum, so no user is ever loaded before the church is known. An unknown or suspended church is a plain 404.
- Never call `withoutGlobalScopes()` in application code. Console commands and seeders may, with a comment saying why.
- Queued jobs have no request, so they carry the church id explicitly (`OutboundSms->churchId`) and write rows with an explicit `church_id`.
- Cache keys, Redis keys and file paths include the church id: `church:{id}:otp:...`, `churches/{church_id}/...`.
- Every new endpoint ships with a cross-tenant test that expects 404, not 403.

## Authentication

- Phone number plus a one-time code; there are no passwords (D-012). `OtpService` stores only a bcrypt hash, per church, in Redis, with lockout.
- The **web app** is signed in by an httpOnly, host-only session cookie (Sanctum stateful, D-027). Other clients get an 8-hour bearer token from the same endpoint. `AuthSessionService` starts and ends both.
- `EnsureStaffIsCurrent` ends a session the moment a user is deactivated or changes phone number; `UserObserver` deletes their tokens.
- Cost-triggering endpoints (sending an SMS) have their own rate limits (D-025).

## Asynchronous work

Six named queues, highest priority first: `critical` (OTP SMS only), `payments`, `notifications`, `reports`, `exports`, `scheduled`. Always pass a queue name when dispatching. A job declares `$tries`, `$timeout`, `$backoff` and a `failed()` handler. No outbound network call runs inside a request.

## Third-party providers

An interface in `app/Contracts` (`SmsProvider`), a driver in `app/Services/<Area>` (`LogSmsProvider` for development), and a binding in `ProviderServiceProvider` chosen by config (`SMS_PRIMARY_PROVIDER`). Switching provider is a config change plus a worker restart. Money crossing a provider interface is a decimal string in GHS (D-017, D-020).

## Observability

- `AssignRequestId` gives every request an id (`X-Request-ID`); it goes into the log context and into queued jobs along with `church_id` and `user_id`.
- Production logs are JSON (`LOG_FORMAT=json`). Never log phone numbers, names, amounts or secrets; log ULIDs.
- `GET /up` is liveness; `GET /api/health` is readiness (database, cache, queue, storage, disk) and reveals only ok or error.
- `php artisan env:check` and a start-up guard refuse to run a deployed environment that is misconfigured.

## Frontend layers (`resources/js`)

```
pages/          one component per route; thin, reads stores and hooks
components/     layout/ (shell, guard) and ui/ (shadcn/ui primitives); render and emit events only
hooks/          reusable logic: useSignIn, useSignOut (TanStack Query mutations)
services/api/   the only place HTTP is called; typed functions returning unwrapped data
services/index  the one axios instance: cookies, CSRF handshake, error normalisation
stores/         Zustand: client state only (auth user, church); never server data
lib/            queryClient, restoreSession, small utilities
constants/      ROUTES and other values used in more than one place
types/          API shapes mirrored from the backend
router/         every route in one file, pages lazy loaded, guard wrapping private routes
```

Server data lives in TanStack Query. Filters and pagination belong in the URL. Money is formatted in one place only (a `MoneyDisplay` component, when money first appears). The session is a cookie the browser holds; JavaScript never sees a credential.

## Where things go

| You are adding | It goes in |
|---|---|
| An endpoint | `routes/api.php` (inside the `tenant` group), a single-action controller, a Form Request that authorises through a Policy, a DTO, an Action, a Repository method if it needs a new query, a Resource, and tests: a unit test for the Action and Feature tests including cross-tenant |
| A table | A migration with a working `down()`, a model with `BelongsToChurch` + `HasUlids` + `SoftDeletes`, a factory, and `docs/07-data-model.md` |
| A fixed set of values | An enum in `app/Enums` with `label()`, mirrored in `resources/js/types` |
| A call to an outside service | A contract, a driver, a queued job, and a row in `message_logs` or the relevant log table |
| A screen | A lazy page in `pages/`, a route in `router/index.tsx` and `constants/routes.ts`, hooks for its data, tests |

## Checks that must pass before anything is done

`composer run lint:check` (Pint and PHPStan level 8), `php artisan test`, `npm run lint:check`, `npm run typecheck`, `npm test`, `npm run build`, `composer audit`, `npm audit --audit-level=high`, a `CHANGELOG.md` entry, and a Conventional Commit on a feature branch. CI runs the same list (`.github/workflows/ci.yml`).
