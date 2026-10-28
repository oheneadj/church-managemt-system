# Security

> Application security controls, threat model, and hardening checklist. Every control here is a required part of the launch Definition of Done. This document is reviewed before every production deployment and after every incident.

---

## Threat Model

We handle three categories of sensitive data that make us a target:

1. **Member PII** — names, phones, addresses, national ID numbers, family structure
2. **Financial records** — contribution history, giving totals, pledge status
3. **Pastoral data** — prayer requests, welfare concerns, pastoral notes

**Who might attack us:**

| Threat Actor | Goal | Likelihood |
|---|---|---|
| Curious church member | See another member's giving total | Medium |
| Malicious ex-staff | Access after account deactivated | Medium |
| Automated credential stuffers | Bulk account access | High |
| Competitor | Steal member data or destabilise platform | Low |
| Ransomware bot | Encrypt DB for ransom | Low |
| Nation-state / data broker | Bulk PII harvest | Very Low |

**What we are NOT defending against:**
- A compromised Oracle data centre (physical layer — Oracle's responsibility)
- A compromised Cloudflare PoP (CDN layer — Cloudflare's responsibility)
- A church admin colluding with an attacker (insider at the church level — policy, not tech)

---

## Authentication Security

### OTP Controls

No passwords exist in this system. All authentication is OTP-based.

```
OTP length:       6 digits (1,000,000 possible values)
OTP expiry:       10 minutes
OTP attempts:     Max 5 before lockout
Lockout duration: 15 minutes
OTP storage:      bcrypt hash in Redis (never plaintext)
OTP use:          Single-use — deleted immediately on verification
```

**Implementation:**
```php
// Generate
$otp  = random_int(100000, 999999);
$hash = bcrypt((string) $otp);
$key  = "otp:{$phone}";
Redis::setex($key, 600, $hash);

// Attempt tracking
$attemptsKey = "otp_attempts:{$phone}";
$attempts    = (int) Redis::get($attemptsKey);

if ($attempts >= 5) {
    throw new TooManyOtpAttemptsException();
}

Redis::incr($attemptsKey);
Redis::expire($attemptsKey, 900); // 15-min lockout window

// Verify
if (!Hash::check($otp, $hash)) {
    // Increment attempts, do NOT reveal whether OTP exists
    throw new InvalidOtpException();
}

// Success — clean up
Redis::del($key);
Redis::del($attemptsKey);
```

**What we do NOT do:**
- Tell the caller whether the phone number exists in the system (enumeration prevention)
- Return different error messages for "OTP wrong" vs "OTP expired" (timing attack prevention)
- Log the OTP value, even in debug mode

### Browser Sessions (the web app, D-027)

The church's web app is signed in with a Laravel session, not a token:

- The session id is in an httpOnly, host-only cookie (`SESSION_DOMAIN=null`), so it is never readable by JavaScript and never sent to another church's subdomain.
- 8 hours of inactivity (`SESSION_LIFETIME=480`), stored in Redis; a fresh session id is issued on sign-in.
- Writes need a CSRF token (`/sanctum/csrf-cookie` then `X-XSRF-TOKEN`).
- `EnsureStaffIsCurrent` signs a session out on the next request if the user was deactivated or their phone number changed.
- The tenant middleware runs before Sanctum so a user is never loaded without the church scope (tested).

### API Tokens (other clients)

Sanctum personal access tokens, for clients that are not the web app:

- Generated with 64 bytes of random data (Laravel default: `Str::random(40)`)
- Stored as SHA-256 hash in `personal_access_tokens` table (never plaintext)
- Expiry: configurable per user type — staff tokens expire in 8 hours (renewed on active use), member portal tokens expire in 24 hours
- Revoked immediately on logout
- All tokens for a user revoked immediately on phone number change

```php
// Token expiry check (in AuthenticateWithToken middleware)
if ($token->created_at->diffInHours(now()) > 8) {
    $token->delete();
    return response()->json(['message' => 'Token expired'], 401);
}
```

---

## Multi-Tenancy Security

This is the most critical security concern. A bug here means Church A sees Church B's data.

### The Three-Layer Defence

**Layer 1: `BelongsToChurch` Global Scope**
Every model with a `church_id` uses the `BelongsToChurch` trait, which adds a global scope filtering to the current church. This fires on *every* query automatically.

```php
trait BelongsToChurch
{
    protected static function bootBelongsToChurch(): void
    {
        static::addGlobalScope('church', function (Builder $builder) {
            if (app()->bound('current.church')) {
                $builder->where(
                    (new static)->getTable() . '.church_id',
                    app('current.church')->id
                );
            }
        });

        static::creating(function ($model) {
            if (app()->bound('current.church') && empty($model->church_id)) {
                $model->church_id = app('current.church')->id;
            }
        });
    }
}
```

**Layer 2: Policy `church_id` ownership check**
Every Policy method checks both the permission *and* that the resource belongs to the current church. A correctly scoped query makes this check redundant — it's here as a safety net.

```php
class ContributionPolicy
{
    public function view(User $user, Contribution $contribution): bool
    {
        return $user->church_id === $contribution->church_id
            && $user->hasPermissionTo('giving.summary');
    }
}
```

**Layer 3: Mandatory cross-tenant tests**
Every new API endpoint ships with a test that proves Church B cannot read Church A's data:

```php
/** @test */
public function church_b_cannot_access_church_a_members(): void
{
    $churchA = Church::factory()->create();
    $churchB = Church::factory()->create();
    
    $member = Member::factory()->for($churchA)->create();
    $userB  = User::factory()->for($churchB)->create();
    $userB->givePermissionTo('members.view');

    $this->actingAs($userB)
         ->getJson("/api/members/{$member->id}")
         ->assertStatus(404); // Not 403 — we don't reveal the resource exists
}
```

**Why 404 and not 403?** A 403 tells an attacker "this resource exists but you can't see it." A 404 reveals nothing.

---

## Authorization

### Middleware Stack

Every authenticated route goes through:
```
web → api → auth:sanctum → ResolveTenant → [RouteMiddleware]
```

`ResolveTenant` resolves the church from the subdomain and sets `app('current.church')`. If the subdomain matches no church, returns 404. Never 500.

### Default-Deny

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    // All authenticated routes here — explicit middleware required
});

// Nothing outside this group is authenticated "by accident"
```

### Permission Checks

Use `$this->authorize()` in controllers, not manual `if (!$user->can(...))` checks. The Policy is the single source of truth.

```php
// Good
public function store(StoreMemberRequest $request): JsonResponse
{
    $this->authorize('create', Member::class);
    // ...
}

// Bad — bypasses the Policy
public function store(StoreMemberRequest $request): JsonResponse
{
    if (!auth()->user()->hasPermissionTo('members.edit')) {
        abort(403);
    }
    // ...
}
```

---

## Input Validation

All input validation happens in Form Request classes. No validation in controllers, no validation in service classes.

### Rules for Sensitive Fields

```php
// Phone numbers — E.164 format required
'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],

// GHS amounts — positive, max 2 decimal places, reasonable max
'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],

// ULIDs — validate format to prevent injection via route parameters
'member_id' => ['required', 'ulid', 'exists:members,id'],

// Free text — strip HTML, limit length
'note' => ['nullable', 'string', 'max:2000', new NoHtml()],
```

### Paystack Webhooks

Webhook payloads are from the internet — treat them as untrusted:

1. Verify HMAC signature *before* reading the payload
2. Store raw payload in `webhook_logs` encrypted
3. Process from `webhook_logs` via job — never process inline in the controller
4. Validate event type against a known-good list before acting

```php
$allowedEvents = ['charge.success', 'charge.failed', 'refund.processed'];
if (!in_array($event, $allowedEvents, true)) {
    Log::warning('Unknown Paystack event type', ['event' => $event]);
    return; // Ignore unknown events
}
```

---

## Data Protection

### Field-Level Encryption

The following fields use Laravel's `encrypted` cast (AES-256-CBC, key from `APP_KEY`):

```php
// app/Models/Member.php
protected $casts = [
    'national_id_number' => 'encrypted',
    'address'            => 'encrypted',
];

// app/Models/PastoralNote.php
protected $casts = [
    'content' => 'encrypted',
];

// app/Models/WebhookLog.php
protected $casts = [
    'payload' => 'encrypted',
];
```

**Consequence:** These fields cannot be queried or indexed at the database level. Never attempt to `WHERE national_id_number = ?` — decrypt in application code after fetching.

### PII in Logs

**Never log:**
- Phone numbers
- Names
- Email addresses
- Contribution amounts
- National ID numbers
- Auth tokens

```php
// Bad
Log::info("OTP sent to {$phone}");

// Good
Log::info('OTP sent', ['member_id' => $member->id]);

// Good — structured logging for debugging without PII
Log::info('Contribution recorded', [
    'contribution_id' => $contribution->id,
    'church_id'       => $contribution->church_id,
    'amount_band'     => $this->amountBand($contribution->amount), // "0-100", "100-500", etc.
]);
```

### Data Minimisation

Collect only what the church needs. Fields that are optional in the data model must be genuinely optional in the UI — no defaulting to required. The member record documents which fields are required vs optional.

---

## Transport Security

- **HTTPS enforced everywhere.** Coolify forces HTTPS redirect at the Nginx level. No HTTP endpoints exist.
- **HSTS enabled.** `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- **Wildcard SSL via Let's Encrypt.** Auto-renewed by Coolify.
- **API responses include `X-Content-Type-Options: nosniff` and `X-Frame-Options: DENY`.**

```php
// app/Http/Middleware/SecurityHeaders.php
public function handle(Request $request, Closure $next): Response
{
    $response = $next($request);
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'DENY');
    $response->headers->set('X-XSS-Protection', '0'); // Let browser defaults handle it
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    return $response;
}
```

---

## Rate Limiting

Configured in `RouteServiceProvider`:

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('otp', function (Request $request) {
    // Stricter limit on OTP requests to prevent SMS flooding
    return [
        Limit::perMinute(3)->by($request->input('phone')),
        Limit::perHour(10)->by($request->input('phone')),
    ];
});

RateLimiter::for('exports', function (Request $request) {
    return Limit::perHour(5)->by($request->user()->id);
});
```

Rate limit responses return `429 Too Many Requests` with a `Retry-After` header.

---

## Dependency Security

- **Composer audit** runs in CI on every PR: `composer audit` — fails the build on any known vulnerability.
- **npm audit** runs in CI on every PR: `npm audit --audit-level=high` — fails on high/critical.
- **Laravel** is kept on the latest stable minor version. Security patches applied within 48 hours of release.
- **Third-party packages** — minimise the list. Every new dependency requires a justification comment in `composer.json`.

---

## Secrets Management

- All secrets in `.env` — never hardcoded, never committed.
- `.env` is in `.gitignore`. Production `.env` managed by Coolify (encrypted at rest).
- **Different secrets** between staging and production environments — staging Paystack, staging AT credentials.
- **Rotation policy:**
  - API keys: rotated on team member departure and every 90 days
  - `APP_KEY`: rotated only if believed compromised (rotation invalidates all sessions and encrypted fields)
  - Database password: rotated every 90 days
- **Who has access:** Only the Lead Engineer has production secret access. No exceptions.

---

## Application Hardening

```env
# Production .env must have
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
```

```php
// config/session.php
'secure'    => env('SESSION_SECURE_COOKIE', true),  // HTTPS only
'http_only' => true,                                 // Not accessible via JS
'same_site' => 'strict',

// config/cors.php — API only accepts requests from own frontend domain
'allowed_origins' => [env('FRONTEND_URL', 'https://yourapp.com')],
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
```

### Production Checklist (Pre-Launch)

- [ ] `APP_DEBUG=false` in production `.env`
- [ ] `APP_ENV=production` in production `.env`
- [ ] No Telescope or Debugbar in production (dev dependencies only)
- [ ] Database user has no `DROP TABLE` or `CREATE DATABASE` permissions
- [ ] Redis is not exposed to the public internet (bind to 127.0.0.1)
- [ ] PostgreSQL is not exposed to the public internet (internal Docker network only; `listen_addresses` / `pg_hba.conf` restricted)
- [ ] Coolify admin panel is behind a non-standard port or IP allowlist
- [ ] SSH access to Oracle instance restricted to key-only, specific IP (no password auth)
- [ ] All composer packages audited with `composer audit`
- [ ] All npm packages audited with `npm audit`
- [ ] Storage directory is not publicly accessible (R2 serves files, not the local disk)
- [ ] Laravel storage link points to R2, not `/public/storage`
- [ ] Webhook endpoints return 200 before any processing (not after)

---

## Breach Response Plan

If we believe data has been accessed without authorisation:

**Step 1 — Contain (within 1 hour)**
- Revoke all active Sanctum tokens: `PersonalAccessToken::query()->delete()`
- Rotate `APP_KEY` and all API credentials
- Force OTP re-authentication for all staff on next login
- Enable read-only mode if the breach vector was a write operation

**Step 2 — Assess (within 4 hours)**
- Review `webhook_logs` for anomalous payload patterns
- Review Laravel logs for unusual query patterns or 403 spikes
- Identify which tables and which church(es) may have been affected
- Determine timeline from logs

**Step 3 — Notify (within 24 hours)**
- Notify affected churches directly by WhatsApp/SMS
- If member PII was accessed, notify affected members
- If the breach may affect EU residents (diaspora churches), notify within 72 hours per GDPR
- File a report with Ghana's Data Protection Commission if required by the Data Protection Act 2012

**Step 4 — Fix**
- Patch the vulnerability that enabled the breach
- Deploy the fix to staging, verify, deploy to production
- Full security audit of adjacent code

**Step 5 — Review**
- Write a post-mortem (template in `docs/reference/post-mortems/`)
- Update this document with any new controls
- Update the risk register in `12-risks-and-open-questions.md`

---

## Security Review Process

**Before every production deployment:**
- [ ] Run `composer audit` — zero known vulnerabilities
- [ ] Run `npm audit --audit-level=high` — zero high/critical vulnerabilities
- [ ] Review any new routes for missing `auth:sanctum` middleware
- [ ] Review any new models for missing `BelongsToChurch` trait
- [ ] Confirm all new endpoints have cross-tenant isolation tests

**Quarterly:**
- [ ] Rotate all API keys and database password
- [ ] Review `personal_access_tokens` table — purge expired tokens (`php artisan sanctum:prune-expired`)
- [ ] Review `webhook_logs` table — enforce 90-day retention
- [ ] Review user accounts — deactivate any departed staff members
- [ ] Run through OWASP Top 10 checklist against the API

**After any team change (departure):**
- [ ] Revoke the departed person's platform accounts immediately
- [ ] Rotate all secrets they had access to
- [ ] Review git history for any credentials accidentally committed
