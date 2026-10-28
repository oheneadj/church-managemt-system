# Product & Architecture Decisions
*Settled October 2026 — update this doc when any decision changes, not a new doc*

---

## 1. Deployment Model — SaaS Only

**Decision:** Multi-tenant SaaS. One codebase, one infrastructure, many churches.

**Why:** Target market (100–800 member churches) has no IT staff, no server budget, and no appetite to manage software. They want to log in and go.

**Standalone requests — how to handle them:**

When a church or denomination asks for a "standalone" or "self-hosted" version, qualify what they actually mean before saying no:

| What they say | What they usually mean | Our response |
|---|---|---|
| "We want our own domain" | Brand visibility | Custom domain mapping (`portal.presbchurch.org.gh` → our infra). 2 hrs of config. Still SaaS. |
| "We want our data separate" | Data isolation / compliance | Dedicated hosted instance — separate DB, separate subdomain, fully isolated. **Premium plan. Still our infrastructure. Still recurring revenue.** |
| "We want it on our own server" | True self-hosting | Enterprise contract only. Significant upfront fee (GHS 15,000+) + monthly support retainer. Make it expensive so the dedicated hosted instance looks attractive by comparison. |

**Key principle:** Never give away the code. Every "standalone" request is a sales opportunity to upsell to the Dedicated Instance tier.

**Script to use:**
> *"We offer a dedicated hosted instance — your own database, your own domain, fully isolated. It's [price]/month. That covers hosting, updates, support, and security. You get all the benefits of your own setup without managing any infrastructure."*

**True self-hosting is v3 Enterprise only** — and only if a denomination (with 50+ branches) signs a proper contract. Do not build or promise this before v3.

---

## 2. Multi-Tenancy Strategy

**Decision:** Single database, `church_id` on every table, Laravel global scopes.

**Implementation:** `BelongsToChurch` trait applied to all models. Global scope automatically filters every query by the authenticated church's ID.

**Why not separate databases per church:** Operational overhead is too high at our scale. Migrations across 100 databases is a maintenance nightmare. Single DB with proper scoping is the standard SaaS approach.

**Dedicated instance exception:** For Enterprise/Dedicated customers, they get their own database *and* their own deployed instance. The codebase is identical — just different `.env` values. Docker Compose makes this a 30-minute spin-up.

---

## 3. Tech Stack

| Layer | Choice | Reason |
|---|---|---|
| Backend | Laravel (latest stable via `composer create-project`) | Batteries included, strong Ghana/Africa dev community |
| Frontend | React 19 SPA — inside the Laravel repo (`resources/js`), REST only | Largest ecosystem, largest hiring pool, strong TypeScript support |
| Frontend build | Vite 6.x | Fast HMR, standard for React projects |
| Routing | React Router v7 | Latest React Router with improved loaders/actions |
| Global state | Zustand v5 | Lightweight, no boilerplate, replaces Pinia |
| Server state | TanStack Query v5 | API caching, background refresh, optimistic updates |
| Styling | Tailwind CSS v4 | Utility-first, consistent design system |
| Components | shadcn/ui (Radix UI primitives) | Accessible, owned in codebase, fully customisable |
| Auth (staff) | Laravel Sanctum + Spatie Permission | Token-based SPA auth + granular roles |
| Auth (members) | OTP via SMS/WhatsApp — no passwords | Members shouldn't need to remember passwords |
| Payments | Paystack / PayPerlez / Moolre (provider abstraction) | See Decision 11 |
| SMS | PayPerlez / Africa's Talking / Moolre (provider abstraction) | See Decision 11 |
| WhatsApp | Meta Business API | CHECKIN command, broadcasts, automated reports |
| Email | Resend → own SMTP later | Laravel Mail abstraction = `.env` change only |
| Queues | Redis + Laravel Queues | All SMS/WhatsApp/email sending is async |
| Storage | Cloudflare R2 | S3-compatible, cheap, fast |
| Infrastructure | Oracle Always Free ARM (4 OCPUs, 24GB RAM) + Coolify | Zero infra cost to start |
| CI/CD | GitHub → Coolify auto-deploy | Push to deploy |
| SSL | Let's Encrypt wildcard via Coolify | Covers all church subdomains automatically |

---

## 4. Domain Strategy

**Decision:** Subdomains per church (`graceassembly.yourapp.com`)

**Why over paths (`yourapp.com/graceassembly`):** Cleaner branding, easier wildcard SSL, tenant isolation is visually obvious to the church admin.

**Custom domains:** Enterprise/Dedicated tier only. Church buys their own domain, we configure DNS CNAME + wildcard cert on our side.

---

## 5. Roles & Permissions

**Roles:** `church-admin`, `pastor`, `finance-secretary`, `department-head`, `youth-pastor`

**Granular permissions (Spatie):**
- `members.view`, `members.edit`
- `giving.*`, `giving.summary`
- `sms.send`
- `reports.view`, `reports.export`

**Why both roles AND permissions:** Roles are shorthand for onboarding. Permissions let us handle edge cases (e.g., a church admin who wants their finance secretary to see summaries but not export raw data).

---

## 6. Pricing Model

**Tiers:** Stored as plan names (`starter`, `growth`, `pro`) in the DB — never as prices. Prices live only in Paystack payment links.

**Why:** Changing prices later requires updating Paystack links only — zero code changes, zero migrations.

**SMS billing:** Churches get a free SMS credit allocation per plan tier each month. When credits run out, they buy top-up bundles (like airtime). This protects margin from heavy-sending churches on flat plans.

**Platform fee on giving:** Yes. Small % cut on every contribution processed through the platform. Disclosed to churches upfront.

---

## 7. Accounting Scope

**Decision:** No full double-entry accounting. Ever (in core product).

**What we build instead:**
- v1 MVP: Giving & contributions tracking only
- v2: Basic expenditure log (record what money went out, attach receipts)
- v3+: If demand justifies, partner with or integrate a dedicated accounting tool

**Why:** Full church accounting is a compliance and liability risk. Churches use external accountants. Building it poorly is worse than not building it.

---

## 8. Features Included (by version)

### MVP (v1)
- Multi-tenant church onboarding
- Member directory + family grouping
- Attendance (service check-in via WhatsApp CHECKIN, QR code, manual staff check-in)
- Giving & contributions (MoMo, cash recording, pledge tracking). USSD giving is deferred past the MVP (D-019).
- SMS/WhatsApp broadcasts (provider abstraction — PayPerlez / Africa's Talking / Moolre + Meta)
- Basic reports (attendance trends, giving summaries)
- Role-based access (5 roles, granular permissions)
- Member OTP login

### v2
- Pastoral care (visit logs, follow-up tasks, prayer requests)
- Ghana youth lifecycle tracking (Crèche → KG → Primary → JHS → SHS → Tertiary)
- Small groups management
- Basic expenditure log
- Automated WhatsApp reports to pastors

### v3
- Volunteer management & scheduling
- Event management
- Biometric attendance (hardware integration — Akwaaba-style)
- Enterprise/Dedicated hosted instances
- Denomination multi-branch management

---

## 9. What We Deliberately Excluded

| Feature | Why excluded |
|---|---|
| Full double-entry accounting | Liability risk, churches use external accountants |
| Native mobile app (iOS/Android) | PWA covers 80% of use cases; app store approval is slow and expensive |
| Video/streaming | Not our core problem |
| Self-hosted/standalone (v1, v2) | Maintenance nightmare, no recurring revenue |
| USD pricing | Trust and affordability signal — GHS only |

---

## 10. Open Decisions (not yet settled)

- **Product name** — must be Ghanaian, modern, church-related. Candidates: *Asafo* (congregation/assembly), *Ayeyi* (praise), *Hyira* (blessing). No decision yet.
- **Primary domain** — TBD after name is chosen
- **Branding** — TBD

---

## 11. Multi-Provider Architecture — SMS and Payments

> **Updated Oct 2026 (D-018, D-019, D-020 in `docs/13-decision-log.md`):** PayPerlez is the default for SMS, OTP, MoMo collections and automatic church payouts; Paystack is for cards only; Africa's Talking is the SMS fallback; Moolre (USSD) is on hold. The full interface definitions are in `docs/09-engineering-principles.md` and take precedence over the sketch below.

**Decision:** SMS and payment processing are abstracted behind provider interfaces. At least one provider must be enabled at all times. Providers are switched via environment config — no code changes required.

**Why:** We have three viable providers (PayPerlez, Moolre, Paystack) with overlapping capabilities. Relying on a single provider creates a single point of failure and pricing lock-in. The abstraction lets us route around outages, run multiple providers simultaneously, or switch without a deploy.

### Supported providers

| Provider | SMS | OTP | Payments (MoMo) | USSD giving | Notes |
|---|---|---|---|---|---|
| PayPerlez | ✅ | ✅ | ✅ | ❌ (coming soon) | Primary recommended provider |
| Africa's Talking | ✅ | ✅ | ❌ | ❌ | Fallback SMS provider |
| Moolre | ✅ | ❌ | ✅ | ✅ | USSD specialist |
| Paystack | ❌ | ❌ | ✅ (+ card) | ❌ | Card payments + subaccounts |

### Provider interfaces (Laravel contracts)

```php
// app/Contracts/SmsProvider.php
interface SmsProvider {
    public function send(string $phone, string $message): void;
}

// app/Contracts/PaymentProvider.php
interface PaymentProvider {
    public function collect(string $phone, string $amountGhs, string $clientReference, MomoNetwork $network): CollectionResult; // decimal string, never pesewas or floats
    public function verifyWebhook(Request $request): bool;
    public function extractReference(Request $request): string;
}
```

### Configuration (`.env`)

```
# SMS — set at least one
SMS_PROVIDER_PAYPERLEZ=true
SMS_PROVIDER_AFRICASTALKING=false
SMS_PROVIDER_MOOLRE=false
SMS_PRIMARY_PROVIDER=payperlez   # which one sends first

# Payments — set at least one
PAYMENT_PROVIDER_PAYSTACK=true
PAYMENT_PROVIDER_PAYPERLEZ=true
PAYMENT_PROVIDER_MOOLRE=true
PAYMENT_PRIMARY_PROVIDER=payperlez
```

### Routing logic

- `SendSmsJob` resolves the active `SmsProvider` from the service container and calls `send()`. No provider name appears in the job.
- `ProcessPaymentWebhookJob` is provider-specific (each provider has its own webhook format and signature scheme). Webhook routes are separate per provider, each dispatching its own job, which normalises the payload and creates a `Contribution` record through the shared `GivingService`.
- The `GivingService` is provider-agnostic — it receives normalised data and has no knowledge of which provider sent it.

### Failure handling

If the primary SMS provider fails (exception or non-2xx), `SendSmsJob` retries on the queue (3 attempts, exponential backoff). If all retries fail, the job lands in `failed_jobs`. There is no automatic cross-provider fallback — that adds complexity without enough reliability gain at our scale. The operator monitors `failed_jobs` and can switch the primary provider in `.env` + restart workers.

### What never changes when switching providers

- `Contribution` model and schema
- `ContributionReceived` event
- Receipt SMS content
- `GivingService` business logic
- Any report or export

Only the provider-specific adapter class, its `.env` keys, and its webhook route change.
