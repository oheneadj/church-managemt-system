# Architecture

> This document describes the system design: how the pieces fit together, why each technology was chosen, and how data flows through the system. Read this before touching infrastructure or adding a new integration.

---

## System Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CLIENTS                                      │
│                                                                     │
│  ┌─────────────────────┐          ┌──────────────────────┐         │
│  │   Church Staff       │          │   Members             │         │
│  │   React 19 SPA       │          │   WhatsApp / SMS      │         │
│  │   (Web Browser)      │          │   Web Browser (OTP)   │         │
│  └──────────┬──────────┘          └──────────┬───────────┘         │
└─────────────┼───────────────────────────────┼─────────────────────┘
              │ HTTPS (REST/JSON)              │ HTTPS / WhatsApp
              ▼                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    ORACLE ARM INSTANCE (Coolify)                    │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │                      Nginx (Reverse Proxy)                    │  │
│  │  *.yourapp.com → Laravel API  |  Wildcard SSL (Let's Encrypt) │  │
│  └───────────────────────┬──────────────────────────────────────┘  │
│                          │                                          │
│  ┌───────────────────────▼──────────────────────────────────────┐  │
│  │                  Laravel API (PHP-FPM)                        │  │
│  │                                                               │  │
│  │  ┌─────────────┐  ┌──────────────┐  ┌──────────────────┐    │  │
│  │  │  Auth Layer  │  │  Controllers │  │  Queue Workers   │    │  │
│  │  │  (Sanctum +  │  │  + Policies  │  │  (SMS / WA /     │    │  │
│  │  │   Spatie)    │  │  + Resources │  │   Email / Webhk) │    │  │
│  │  └─────────────┘  └──────────────┘  └──────────────────┘    │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────────────────┐  │
│  │ PostgreSQL DB│  │    Redis     │  │  Coolify (PaaS Layer)    │  │
│  │  (Primary)   │  │  (Cache +    │  │  CI/CD, SSL, Env Mgmt,   │  │
│  │              │  │   Queues)    │  │  Container Orchestration  │  │
│  └─────────────┘  └──────────────┘  └──────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
              │                    │                  │
              ▼                    ▼                  ▼
┌─────────────────┐  ┌─────────────────┐  ┌──────────────────────┐
│   Paystack      │  │ Africa's Talking │  │  Meta Business API   │
│   (Payments +   │  │  (SMS)          │  │  (WhatsApp)          │
│    Subaccounts  │  │                 │  │                      │
│    + Webhooks)  │  └─────────────────┘  └──────────────────────┘
└─────────────────┘
              │
┌─────────────────┐  ┌─────────────────┐
│  Cloudflare R2  │  │     Resend       │
│  (File Storage) │  │    (Email)       │
└─────────────────┘  └─────────────────┘
```

---

## Layers Explained

### Frontend — React 19 SPA

Lives in the same repository as the backend (`resources/js`, D-022) but is a pure client: it communicates with the Laravel API exclusively via HTTPS REST calls with JSON. No server-side rendering and no Inertia. Laravel serves the built assets through one shell view and a catch-all route.

**Why an SPA over REST and not Laravel Blade or Inertia:**
- App-like UX without page reloads — important for a tool used continuously during a Sunday service
- Clear API boundary — the API can later serve a mobile app or denomination portal without rework
- Clean separation of concerns inside one repo — the SPA can be split out later without touching the API (D-022)

**Full stack:**

| Tool | Version | Role |
|---|---|---|
| React | 19 (latest) | UI framework |
| TypeScript | 5.x | Type safety across all components and API calls |
| Vite | 6.x | Build tool + dev server |
| React Router | v7 | Client-side routing |
| Zustand | v5 | Global state (auth user, current church, UI state) |
| TanStack Query | v5 | Server state — fetching, caching, syncing API data |
| Tailwind CSS | v4 | Utility-first styling |
| shadcn/ui | latest | Accessible, unstyled component primitives (built on Radix UI) |
| Axios | v1 | HTTP client — all API calls go through a typed service layer |

**Hosting:** `npm run build` writes hashed assets to `public/build`, served by Nginx from the same container as the API. Laravel's catch-all route returns the SPA shell (`resources/views/app.blade.php`) for any non-`/api` path. Same origin as the API, so no CORS configuration is needed.

**Tenant resolution:** On load, the SPA reads the subdomain (`graceassembly.yourapp.com`), sends it with the first API call, and the backend resolves the church. The resolved church context is stored in Zustand for the session.

**State management:**
- **Zustand** for global client state: auth user, current church, sidebar open/closed, toast notifications
- **TanStack Query** for all server state: member lists, contribution data, attendance records — handles caching, background refresh, and optimistic updates

**Component library:** shadcn/ui components (copy-paste, owned in the codebase) + Tailwind CSS. No black-box UI framework — we control every component.

---

### Backend — Laravel API

The single source of truth for all business logic. Exposes a RESTful JSON API consumed by the React SPA and by external webhooks (Paystack).

**Key Laravel features in use:**

| Feature | Usage |
|---|---|
| Eloquent ORM + Global Scopes | Multi-tenancy via `BelongsToChurch` trait |
| Sanctum | SPA token authentication |
| Gates & Policies | Authorisation — every resource has a Policy class |
| Queues + Jobs | Async SMS, WhatsApp, email dispatch |
| Events + Listeners | Decoupled side effects (e.g. contribution recorded → fire `ContributionReceived` event → listener sends receipt) |
| Notifications | Multi-channel (SMS, WhatsApp, email) through a unified interface |
| Mail | Email sending via Resend; swap to own SMTP with `.env` change only |
| Artisan Commands | Scheduled tasks (birthday alerts, pledge reminders, weekly reports) |
| Form Requests | Input validation — all validation logic in Request classes, never in controllers |

---

### Multi-Tenancy — `BelongsToChurch` Trait

Every Eloquent model that holds church data uses this trait:

```php
<?php

namespace App\Traits;

use App\Models\Church;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToChurch
{
    protected static function bootBelongsToChurch(): void
    {
        // Automatically filter all queries to the current church
        static::addGlobalScope('church', function (Builder $builder) {
            if (app()->has('current.church')) {
                $builder->where(
                    (new static)->getTable() . '.church_id',
                    app('current.church')->id
                );
            }
        });

        // Automatically set church_id on new records
        static::creating(function ($model) {
            if (app()->has('current.church') && empty($model->church_id)) {
                $model->church_id = app('current.church')->id;
            }
        });
    }

    public function church(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
```

**The church is resolved per-request** from the subdomain in `App\Http\Middleware\ResolveTenant`:

```php
public function handle(Request $request, Closure $next): Response
{
    $subdomain = explode('.', $request->getHost())[0];
    $church = Church::where('subdomain', $subdomain)->firstOrFail();
    app()->instance('current.church', $church);
    return $next($request);
}
```

This middleware runs before all authenticated routes. If the subdomain doesn't match a church, the request is rejected with 404.

---

### Authentication — Sanctum + OTP

**Staff authentication flow:**

```
1. POST /api/auth/otp/request  { phone: "0244123456" }
   → Generates 6-digit OTP, stores hashed in cache (10 min TTL)
   → Sends OTP via the active SmsProvider (PayPerlez by default, D-020)

2. POST /api/auth/otp/verify   { phone: "0244123456", otp: "382910" }
   → Verifies OTP hash
   → Creates Sanctum personal access token
   → Returns: { token, user, church, permissions[] }

3. All subsequent requests:
   Authorization: Bearer <token>
   → Middleware validates token + resolves church
```

**Member authentication flow:** Identical, but returns a limited token scoped to member-only routes.

**OTP security rules:**
- 6 digits, numeric only
- 10-minute expiry
- Hashed with `bcrypt` before storage in Redis
- Max 5 attempts per phone per window; 15-minute lockout after failure
- Rate limited at the route level: 5 requests per phone per minute

---

### Authorisation — Roles + Policies

**Roles** (Spatie Laravel Permission) define what a user can do by default:

```
church-admin        → full access to their church
pastor              → members (read), giving summaries, pastoral care, communications
finance-secretary   → giving (full), members (read), reports (export)
department-head     → members in their department only
youth-pastor        → youth members, youth milestones, youth attendance
```

**Policies** enforce authorisation at the resource level. Every model has a Policy class. Controllers call policies via `$this->authorize()`. No raw role checks in controllers.

```php
// MemberPolicy.php
public function update(User $user, Member $member): bool
{
    return $user->hasPermissionTo('members.edit')
        && $member->church_id === app('current.church')->id;
}
```

The `church_id` check in policies is a defence-in-depth measure — the global scope handles it in queries, but policies catch it for direct model operations.

---

### Queue System — Redis + Laravel Queues

All side effects that don't need to be synchronous are queued:

| Job | Queue | Priority |
|---|---|---|
| `SendSmsJob` (OTP) | `critical` | Highest (D-025) |
| `SendSmsJob` (receipts, broadcasts) | `notifications` | Normal |
| `SendWhatsAppJob` | `notifications` | Normal |
| `SendEmailJob` | `notifications` | Low |
| `ProcessPaystackWebhookJob` | `payments` | High |
| `GenerateOfferingReportJob` | `reports` | Low |
| `ExportMembersJob` | `exports` | Low |
| `SendBirthdayAlertJob` | `scheduled` | Low |

**Queue workers run as separate processes** managed by Coolify's process supervisor (Supervisor). If a job fails, it retries 3 times with exponential backoff. After 3 failures, it lands in the `failed_jobs` table and triggers a monitoring alert.

**Why Redis over database queues:** Speed. SMS jobs must dispatch within seconds of a contribution being recorded. Database queue polling adds latency; Redis is sub-millisecond.

---

### Integrations

> **Updated Oct 2026 (D-018, D-019, D-020):** the integration subsections below were written when Paystack and Africa's Talking were the only providers. Today PayPerlez is the default for SMS, OTP, MoMo collections and automatic church payouts; Paystack is for cards only; Africa's Talking is the SMS fallback; Moolre/USSD is on hold. Where this section and `14-integrations.md` or `09-engineering-principles.md` disagree, those two win.

#### Paystack

**Purpose:** MoMo + card payment processing, subaccount management, webhooks.

**Key operations:**
- `POST /subaccount` — called during church onboarding to create the church's subaccount
- `POST /transaction/initialize` — initiate a MoMo or card payment
- `POST /charge` — direct MoMo charge (USSD prompt)

**Webhook handling:**
- Paystack POSTs to `/api/webhooks/paystack`
- Request signature verified using `HMAC-SHA512` with `PAYSTACK_SECRET_KEY`
- Payload stored in `webhook_logs` immediately (before processing)
- Processing dispatched as `ProcessPaystackWebhookJob` → queued, not synchronous
- Idempotency: `paystack_reference` checked before creating a contribution record

```php
// WebhookController.php
public function paystack(Request $request): JsonResponse
{
    // Verify signature first — reject if invalid
    $signature = $request->header('X-Paystack-Signature');
    $computed = hash_hmac('sha512', $request->getContent(), config('services.paystack.secret'));

    if (!hash_equals($computed, $signature)) {
        return response()->json(['message' => 'Invalid signature'], 401);
    }

    // Store raw payload — never lose a webhook
    WebhookLog::create([
        'source'  => 'paystack',
        'event'   => $request->input('event'),
        'payload' => $request->getContent(),
    ]);

    // Queue for processing
    ProcessPaystackWebhookJob::dispatch($request->all());

    return response()->json(['status' => 'received']);
}
```

#### Africa's Talking (SMS)

**Purpose:** OTP delivery, giving receipts, broadcast messages, pastoral alerts.

**SDK:** `africastalking/africastalking` (official PHP SDK)

**Usage:**
```php
$sms = AfricasTalking::sms();
$result = $sms->send([
    'to'      => ['+233244123456'],
    'message' => $message,
    'from'    => config('services.africastalking.sender_id'),
]);
```

**Credit tracking:** After each successful send, Africa's Talking returns units used. The system deducts from the church's SMS credit balance. If balance is zero, the job fails gracefully and the admin is notified.

**Sender ID:** Each church uses the platform's registered sender ID by default. Verified custom sender IDs are a v2 feature.

#### Meta Business API (WhatsApp)

**Purpose:** CHECKIN command, giving receipts (optional), weekly pastoral reports, broadcasts to opted-in members.

**Message types:**
- **Template messages** (HSM): pre-approved formats for transactional messages (receipts, OTPs). Required for outbound messages to non-initiated conversations.
- **Session messages**: free-form replies within 24 hours of member-initiated contact (CHECKIN flow).

**CHECKIN flow:**
```
Member sends:  CHECKIN [service-code]
             ↓
   Meta webhook → POST /api/webhooks/whatsapp
             ↓
   System matches sender phone → finds member
             ↓
   Finds active service matching code
             ↓
   Creates attendance_record
             ↓
   Replies: "✓ Checked in to Sunday Service — 12 Oct 2026. See you inside, Kwame!"
```

**Opt-in required:** Members must opt in to receive WhatsApp broadcasts. The opt-in is recorded in `member_communication_preferences`.

#### Resend (Email)

**Purpose:** Staff invitation emails, export download links, system notifications (breach alerts, failed job alerts).

**Laravel Mail abstraction:** Email is sent via `Mail::to()->send()`. The mail driver is configured in `.env`. Switching from Resend to a self-hosted SMTP (e.g. Postal) requires only updating `MAIL_*` environment variables — no code changes.

```env
# Resend (current)
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=re_xxxxxxxxxxxx
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@yourapp.com

# Own SMTP (future)
MAIL_MAILER=smtp
MAIL_HOST=mail.yourapp.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourapp.com
MAIL_PASSWORD=xxxxxxxxxxxx
MAIL_ENCRYPTION=tls
```

#### Cloudflare R2

**Purpose:** Member profile photos, receipt attachments (v2), export files.

**Laravel integration:** `league/flysystem-aws-s3-v3` with R2-compatible endpoint. Configured as the `r2` disk in `config/filesystems.php`.

```env
CLOUDFLARE_R2_ACCESS_KEY_ID=xxxx
CLOUDFLARE_R2_SECRET_ACCESS_KEY=xxxx
CLOUDFLARE_R2_DEFAULT_REGION=auto
CLOUDFLARE_R2_BUCKET=yourapp-production
CLOUDFLARE_R2_URL=https://xxxx.r2.cloudflarestorage.com
```

Files are stored at: `churches/{church_id}/members/{member_id}/photo.jpg`

Signed URLs (time-limited, 1 hour) are used for photo access — photos are never publicly accessible by default.

---

### Infrastructure — Oracle Always Free ARM + Coolify

**Oracle ARM Instance:**
- 4 OCPUs (ARM Ampere A1)
- 24 GB RAM
- 200 GB block storage
- Always free — no expiry, no cost

**Coolify** sits on top of the Oracle instance and provides:
- Application deployment (Docker-based, zero-downtime deploys)
- Environment variable management (encrypted at rest)
- Automatic SSL via Let's Encrypt (wildcard `*.yourapp.com`)
- Process management (PHP-FPM, queue workers, scheduler)
- Reverse proxy (Nginx)
- Basic monitoring and log streaming

**What Coolify replaces:** Forge, Ploi, manual Nginx config, Certbot cron jobs, Supervisor config.

**Scaling path:** When the Oracle free instance is no longer sufficient, Coolify can point at a paid ARM instance (Oracle, Hetzner, or DigitalOcean) with no application code changes. The Docker containers are portable.

---

### CI/CD Pipeline

```
Developer pushes to GitHub (main branch)
              ↓
     GitHub Actions runs:
       - PHP unit tests
       - Laravel Pint (code style)
       - PHPStan (static analysis)
              ↓
     Tests pass → Coolify webhook triggered
              ↓
     Coolify pulls latest image, builds, deploys
     (zero-downtime: new container up → traffic switched → old container removed)
              ↓
     Coolify runs: php artisan migrate --force
              ↓
     Slack/WhatsApp notification: "Deployed to production ✓"
```

**Branch strategy:**
- `main` → production (auto-deploy)
- `staging` → staging instance (auto-deploy)
- Feature branches → PR required before merge to `staging`

**Database migrations:** Run automatically as part of deployment. All migrations must be backward-compatible (no column renames, no destructive changes without a deprecation window).

---

### Subdomain Strategy

Each church gets a subdomain: `{slug}.yourapp.com`

The slug is generated from the church name at onboarding:
- "Grace Assembly International" → `grace-assembly`
- Collisions get a suffix: `grace-assembly-2`

**Wildcard SSL:** Let's Encrypt wildcard certificate covers `*.yourapp.com`. Renewed automatically by Coolify.

**DNS:** A single `A` record: `*.yourapp.com → [Oracle IP]`. All subdomains hit the same Nginx, which routes to the same Laravel app. Laravel identifies the church from the subdomain in `ResolveTenant` middleware.

**Custom domains (Enterprise, v3):** Church purchases `portal.theirchurch.org.gh`. They set a CNAME to `their-slug.yourapp.com`. Coolify handles the cert for the custom domain. The church's `custom_domain` field is populated; the `ResolveTenant` middleware checks both subdomain and custom domain.

---

### Scheduled Tasks (Laravel Scheduler)

The scheduler runs via Coolify's cron (every minute: `* * * * * php artisan schedule:run`):

| Task | Frequency | Description |
|---|---|---|
| `SendBirthdayAlerts` | Daily 08:00 | Notify pastors of members with birthdays in the next 7 days |
| `FlagAbsentMembers` | After each service | Check for members absent 3+ consecutive Sundays |
| `SendPledgeReminders` | Weekly Monday | Members with outstanding pledges approaching deadline |
| `SendPastorWeeklyReport` | Weekly Monday 07:00 | WhatsApp digest to pastors (v2) |
| `ArchiveStaleVisitors` | Weekly Sunday | Move unconverted visitors (30+ days) to archived |
| `PruneExpiredOtps` | Hourly | Clean Redis OTP cache |
| `PruneWebhookLogs` | Monthly | Delete webhook logs older than 90 days |

---

## Request Lifecycle (Example: Record a Cash Contribution)

```
1. Finance Secretary clicks "Record Contribution" in the React SPA

2. React sends:
   POST https://grace-assembly.yourapp.com/api/contributions
   Authorization: Bearer <token>
   { member_id, amount, fund_type, payment_method: "cash", service_id }

3. Nginx receives → passes to PHP-FPM

4. Laravel:
   a. ResolveTenant middleware → loads Grace Assembly church from DB
   b. Sanctum middleware → verifies token → loads user
   c. Router → ContributionController@store
   d. StoreContributionRequest → validates input
   e. Policy → checks user has `giving.record` permission
   f. ContributionService::record() → creates DB record
   g. ContributionReceived event fired
   h. SendContributionReceiptListener → dispatches SendSmsJob to Redis queue
   i. Returns 201 { contribution resource }

5. React receives 201 → updates UI → shows success toast

6. Queue worker (separate process) picks up SendSmsJob:
   → Africa's Talking SDK sends receipt SMS to member
   → Deducts 1 credit from church SMS balance
   → Logs in message_logs
```

Total time to UI response: < 200ms
SMS delivery: 2–10 seconds after response

---

## Environment Variables Reference

```env
# App
APP_NAME="YourApp"
APP_ENV=production
APP_KEY=base64:xxxx
APP_URL=https://yourapp.com
APP_DEBUG=false

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yourapp_production
DB_USERNAME=yourapp
DB_PASSWORD=xxxx

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# Paystack
PAYSTACK_PUBLIC_KEY=pk_live_xxxx
PAYSTACK_SECRET_KEY=sk_live_xxxx
PAYSTACK_PLATFORM_SPLIT_CODE=SPL_xxxx

# Africa's Talking
AT_API_KEY=xxxx
AT_USERNAME=yourapp
AT_SENDER_ID=YourApp

# Meta / WhatsApp
META_WHATSAPP_TOKEN=xxxx
META_PHONE_NUMBER_ID=xxxx
META_VERIFY_TOKEN=xxxx

# Cloudflare R2
CLOUDFLARE_R2_ACCESS_KEY_ID=xxxx
CLOUDFLARE_R2_SECRET_ACCESS_KEY=xxxx
CLOUDFLARE_R2_BUCKET=yourapp-production
CLOUDFLARE_R2_URL=https://xxxx.r2.cloudflarestorage.com

# Email (Resend)
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=re_xxxx
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@yourapp.com
MAIL_FROM_NAME="YourApp"
```

---

## Related Documents

- [`07-data-model.md`](./07-data-model.md) — database schema
- [`08-api.md`](./08-api.md) — API endpoint reference
- [`15-security.md`](./15-security.md) — security controls
- [`16-deployment-and-ops.md`](./16-deployment-and-ops.md) — deployment procedures
- [`17-monitoring-and-alerting.md`](./17-monitoring-and-alerting.md) — observability
