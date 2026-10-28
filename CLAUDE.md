# CLAUDE.md — AI Context for This Repository

> This file tells Claude Code (and any AI assistant working in this repo) what this project is, how to work in it, and what the non-obvious rules are. Read this before writing any code.

---

## What This Project Is

A **multi-tenant SaaS** for Ghanaian churches (100–800 members). Churches pay a monthly subscription. All churches share one database, isolated by `church_id`. The platform handles member management, attendance (including WhatsApp CHECKIN), digital giving via Paystack MoMo/card, and SMS/WhatsApp communications.

**Backend:** Laravel (latest stable) — REST API only. The only Blade view is the SPA shell (`resources/views/app.blade.php`); no Inertia.
**Database:** PostgreSQL. **Dev:** native (no Docker). **Staging/production:** Docker via Coolify.
**Frontend:** React 19 SPA in `resources/js` of this same repo (D-022). TypeScript, Vite, React Router v7, Zustand, TanStack Query v5, Tailwind CSS v4, shadcn/ui.

---

## The Most Important Rules

These are non-negotiable. Getting any of these wrong is a critical bug:

### 1. Every model with `church_id` must use `BelongsToChurch` trait
```php
use App\Traits\BelongsToChurch;

class Member extends Model
{
    use BelongsToChurch; // This adds the global scope + auto-sets church_id
}
```
Without this, queries leak data across churches.

### 2. Every new API endpoint requires a cross-tenant isolation test
```php
// Tests that Church B cannot see Church A's data
// Returns 404, not 403 — we don't reveal the resource exists
```
This test is a hard requirement in the Definition of Done. No exceptions.

### 3. All outbound network calls are queued — never inline
```php
// Wrong:
AfricasTalking::sms()->send([...]);

// Right:
SendSmsJob::dispatch($phone, $message)->onQueue('notifications');
```
HTTP calls in a request lifecycle cause timeouts and slow responses.

### 4. No business logic in controllers
Controllers are thin. They: validate (via Form Request), authorise (via Policy), call a Service, return a Resource. That's it.

### 5. No hard deletes. Ever.
All models use `SoftDeletes`. Contributions use `is_voided` flag, not deletion. Void requires a reason.

### 6. All amounts are GHS, stored as `DECIMAL(12,2)`
No cents, no kobo, no multi-currency. `200.00` = GHS 200. Never do money arithmetic with PHP floats — use `bcmath` or `brick/math` (see D-017). Paystack requires pesewas in its API (`amount * 100`), but we store and display GHS.

### 7. `APP_DEBUG=false` in production. Always.

---

## Project Structure (Laravel API)

```
app/
├── Console/Commands/       — Artisan commands (backup, prune, monitor)
├── Events/                 — Domain events (ContributionReceived, etc.)
├── Exceptions/             — Custom exception classes
├── Http/
│   ├── Controllers/        — Thin. One public method per action.
│   ├── Middleware/         — ResolveTenant, SecurityHeaders
│   ├── Requests/           — Form Request validation classes
│   └── Resources/          — API Resource serialisation classes
├── Jobs/                   — Queue jobs (SendSmsJob, ProcessWebhookJob, etc.)
├── Listeners/              — Event listeners
├── Mail/                   — Mailable classes
├── Models/                 — Eloquent models
├── Notifications/          — Laravel Notifications
├── Policies/               — Authorisation policies (one per model)
├── Services/               — Business logic (MemberService, GivingService, etc.)
└── Traits/
    └── BelongsToChurch.php — Multi-tenancy global scope
```

---

## Key Patterns

### Controller → Form Request → Service → Resource
```php
public function store(StoreMemberRequest $request): JsonResponse
{
    $this->authorize('create', Member::class);
    $member = $this->memberService->create($request->validated());
    return new MemberResource($member);
}
```

### Service Class
```php
class MemberService
{
    public function create(array $data): Member
    {
        return Member::create($data); // church_id auto-set by BelongsToChurch
    }
}
```

### Policy (always check church_id)
```php
public function update(User $user, Member $member): bool
{
    return $user->church_id === $member->church_id
        && $user->hasPermissionTo('members.edit');
}
```

---

## Database Conventions

- **ULID primary keys** — `CHAR(26)` on all tables, the only identifier (no integer key). Use `HasUlids` trait; validate with the `ulid` rule.
- **`church_id`** — on every tenant-scoped table. `CHAR(26)`, indexed, NOT NULL.
- **No native `ENUM` columns** — use a `VARCHAR(30)` column cast to a PHP backed enum in `app/Enums/`. Validate with `Rule::enum()`.
- **Soft deletes** — `deleted_at` on all models. Never call `->forceDelete()`.
- **Field encryption** — `national_id_number`, `address`, `pastoral_notes.content`, `webhook_logs.payload` use `'encrypted'` cast. Cannot be queried.
- **No raw SQL** — Eloquent or Query Builder only. Exception: complex reporting queries use Query Builder, never raw string interpolation.
- **Migrations** — always write a working `down()` method. Always backward-compatible.

---

## Authentication & Permissions

**Auth:** Laravel Sanctum personal access tokens. OTP-only (no passwords). Phone number is the identity.

**Roles:** `church-admin`, `pastor`, `finance-secretary`, `department-head`, `youth-pastor`

**Key permissions:** `members.view`, `members.edit`, `giving.summary`, `giving.record`, `giving.edit`, `giving.export`, `sms.send`, `reports.view`, `reports.export`

**Tenant resolution:** `ResolveTenant` middleware resolves the church from the subdomain (`grace-assembly.yourapp.com` → Church with slug `grace-assembly`). Sets `app('current.church')`. Returns 404 on unknown subdomain.

---

## Queues

Six named queues, in priority order:

| Queue | Used for | Workers |
|---|---|---|
| `critical` | OTP SMS, nothing else (D-025) | 1 |
| `payments` | Paystack webhook processing | 2 (high priority) |
| `notifications` | SMS, WhatsApp, email sends | 2 |
| `reports` | Report generation | 1 |
| `exports` | CSV export jobs | 1 |
| `scheduled` | Cron-triggered jobs | 1 |

Always specify the queue when dispatching:
```php
SendSmsJob::dispatch(...)->onQueue('notifications');
ProcessPaystackWebhookJob::dispatch(...)->onQueue('payments');
```

---

## Integrations (Quick Reference)

| Service | Config key | SDK |
|---|---|---|
| Paystack | `services.paystack.*` | Official PHP SDK |
| Africa's Talking | `services.africastalking.*` | Official PHP SDK |
| Meta WhatsApp | `services.meta.*` | Laravel HTTP client (raw) |
| Resend | `MAIL_*` in `.env` | Laravel Mail (SMTP) |
| Cloudflare R2 | `filesystems.disks.r2` | `league/flysystem-aws-s3-v3` |

Full integration details: `docs/14-integrations.md`

---

## Testing

```bash
php artisan test                    # All tests
php artisan test --parallel         # Faster
php artisan test --filter=Giving    # Filter by name
```

**Required for every new endpoint:**
1. Happy path test
2. Validation failure test
3. Unauthenticated test (expects 401)
4. Unauthorised role test (expects 403)
5. **Cross-tenant isolation test (expects 404)**

**Required for financial code:** 100% test coverage — not optional.

Use `RefreshDatabase` or `LazilyRefreshDatabase` in test classes. Use factories for all model creation.

---

## Common Commands

```bash
# Development
php artisan serve                   # Start dev server
php artisan queue:work              # Process all queues
php artisan telescope:clear         # Clear Telescope data

# Database
php artisan migrate                 # Run pending migrations
php artisan migrate:fresh --seed    # Reset + seed (dev/staging only)
php artisan db:backup               # Manual backup

# Code quality
./vendor/bin/pint                   # Fix code style
./vendor/bin/pint --test            # Check without fixing
./vendor/bin/phpstan analyse        # Static analysis

# Queues
php artisan queue:work redis --queue=payments,notifications
php artisan queue:failed            # List failed jobs
php artisan queue:retry all         # Retry all failed jobs
php artisan queue:restart           # Restart workers (after deploy)

# Monitoring
php artisan monitor:queues          # Check queue depths
php artisan monitor:integrations    # Check third-party health
```

---

## MCP Servers and Skills

Use these instead of recalling framework APIs from memory (Universal agreement §1, §3a):

- **Laravel Boost** (`laravel-boost`, in `.mcp.json`; run `php artisan boost:mcp`). Call `search-docs` before using any Laravel, Pest, Livewire or Tailwind API you are not certain about for the installed version. Call `database-schema` before writing a migration and `database-query` to inspect data. Use `last-error` and `read-log-entries` when debugging, and `record-rule` to save a project rule that every future session should follow. Claude Code asks you to approve this server the first time (`/mcp`).
- **Context7** (`context7`, hosted at `https://mcp.context7.com/mcp`, added per developer with `claude mcp add --transport http --scope local context7 https://mcp.context7.com/mcp`). Use it for the React side that Boost does not cover: React Router 7, TanStack Query 5, Zod, React Hook Form, Vitest, shadcn/ui. State the version in the question. Send only generic library questions, never secrets or member data.
- **Boost skills** live in `.claude/skills` (configured in `boost.json`, Claude Code only). Refresh them with `php artisan boost:update --no-discover` after upgrading Laravel packages.

## What Not to Do

- **Don't hardcode church IDs** in queries — use the global scope
- **Don't return raw Eloquent models** from controllers — use Resource classes
- **Don't validate in controllers** — use Form Request classes
- **Don't put business logic in models** — use Service classes
- **Don't make HTTP calls in the request lifecycle** — queue them
- **Don't use `->withoutGlobalScopes()`** unless you're writing a system-level command with a very good reason
- **Don't log PII** — names, phones, amounts — use IDs and opaque identifiers in logs
- **Don't commit `.env`** — ever
- **Don't leave `dd()` or `console.log()` in committed code**
- **Don't write migrations without a working `down()` method**

---

## Docs Reference

Everything is documented. When in doubt, read the relevant doc:

- Architecture decisions → `docs/13-decision-log.md`
- Data model → `docs/07-data-model.md`
- API contract → `docs/08-api.md`
- Engineering patterns → `docs/09-engineering-principles.md`
- Security controls → `docs/15-security.md`
- Integrations → `docs/14-integrations.md`
- Definition of Done → `docs/10-definition-of-done.md`
