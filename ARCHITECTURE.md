# Architecture

> The layers of this codebase, what each owns, and what it must never do. Read this before adding code, and reference it in every AI session. It describes what exists today; when the structure changes, change this file in the same commit. Longer background lives in `docs/06-architecture.md`; decisions and their reasons live in `docs/13-decision-log.md`.

## The shape of the system

One Laravel 13 application serves two things from one repository (D-022):

- **The API**, under `/api/v1/*`: JSON only, versioned from day one (D-024).
- **The React app** in `resources/js`, served through one Blade view (`resources/views/app.blade.php`) and a catch-all route in `routes/web.php`. The app only ever talks to the API; there is no Inertia.

One PostgreSQL database holds every church. A row belongs to a church through `church_id` (D-007, D-021). Redis carries queues, cache and sessions. Files live in Cloudflare R2. Staging and production run in Docker behind Coolify; development is native (D-023).

## Backend layers

```
Route                routes/api.php, routes/health.php, routes/web.php
  ↓
Middleware           tenant, auth, request id, rate limits
  ↓
Form Request         app/Http/Requests      validates and normalises input
  ↓
Controller           app/Http/Controllers   thin: receive, call one service, return a Resource
  ↓
Service              app/Services           the business logic; owns DB::transaction()
  ↓
Model + Scope        app/Models, app/Scopes structure, relationships, casts, tenant scope
  ↓
PostgreSQL

Response shaping     app/Http/Resources     every API body goes through a Resource
Side effects         app/Jobs, app/Events, app/Listeners   anything slow or external is queued
Outside world        app/Contracts + app/Services/<Area>   third parties sit behind an interface
```

| Layer | Owns | Must never contain |
|---|---|---|
| Controller | HTTP in and out, authorisation call, one service call | Business rules, queries, validation, `$request->validate()` |
| Form Request | Validation, input normalisation (phone numbers), `authorize()` | Business logic |
| Service | Business rules, transactions, dispatching jobs and events | `$request`, HTTP responses, provider SDKs |
| Model | Casts, relationships, scopes, fillable list | Business logic, HTTP, queues |
| Resource | The exact shape of each API response | Queries (use `whenLoaded`), authorisation decisions |
| Job | One unit of async work with `tries`, `timeout`, `backoff`, `failed()` | Anything that must finish before the response |
| Contract + driver | One third-party capability behind an interface, chosen by config | Provider-specific errors or shapes leaking out |
| Policy | Authorisation decisions only | Everything else |

**Services, not Actions or Repositories (D-028).** The Universal agreement describes Action and Repository layers. This project uses Controller → Form Request → Service, as `docs/09-engineering-principles.md` specifies. Services own the transaction boundary. A Repository is added only when a second data source exists, and an Action only when one operation is triggered from several entry points.

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
| An endpoint | `routes/api.php` (inside the `tenant` group), a Form Request, a thin controller, a Service, a Resource, a Policy, and tests including cross-tenant |
| A table | A migration with a working `down()`, a model with `BelongsToChurch` + `HasUlids` + `SoftDeletes`, a factory, and `docs/07-data-model.md` |
| A fixed set of values | An enum in `app/Enums` with `label()`, mirrored in `resources/js/types` |
| A call to an outside service | A contract, a driver, a queued job, and a row in `message_logs` or the relevant log table |
| A screen | A lazy page in `pages/`, a route in `router/index.tsx` and `constants/routes.ts`, hooks for its data, tests |

## Checks that must pass before anything is done

`composer run lint:check` (Pint and PHPStan level 8), `php artisan test`, `npm run lint:check`, `npm run typecheck`, `npm test`, `npm run build`, `composer audit`, `npm audit --audit-level=high`, a `CHANGELOG.md` entry, and a Conventional Commit on a feature branch. CI runs the same list (`.github/workflows/ci.yml`).
