# Engineering Principles

> Rules we build by. Not suggestions — these are the standards every PR is held to. When in doubt, come back here. When a rule should change, change the doc and tell the team.

---

## The Short Version

1. Write code for the next developer, not just for the machine.
2. Every side effect is async. Never make the user wait for a network call.
3. The global scope protects every query. Never bypass it.
4. Validate at the edge. Trust nothing that came from outside.
5. No hard deletes. Ever.
6. Tests are not optional on financial or authentication code.
7. If it touches money, it gets a code review before merge.

---

## Code Organisation

### Laravel (Backend)

Follow the standard Laravel directory structure. Do not invent new top-level folders without team discussion.

```
app/
├── Console/
│   └── Commands/          # Artisan commands (scheduled tasks)
├── Events/                # Domain events (ContributionReceived, MemberCheckedIn)
├── Exceptions/            # Custom exception classes + Handler overrides
├── Http/
│   ├── Controllers/Api/   # Single-action controllers (__invoke), thin
│   ├── Middleware/        # ResolveTenant, etc.
│   ├── Requests/          # Form Request classes — all validation here
│   └── Resources/         # API Resource classes — all serialisation here
├── Jobs/                  # Queued jobs (SendSmsJob, ProcessPaystackWebhookJob)
├── Listeners/             # Event listeners
├── Mail/                  # Mailable classes
├── Actions/               # One class per user intent (CreateMemberAction)
├── DTOs/                  # Typed input for one operation
├── Models/                # Eloquent models
├── Repositories/          # Repository interfaces + Eloquent implementations
├── Notifications/         # Laravel Notifications (multi-channel)
├── Policies/              # Authorisation — one Policy per model
├── Services/              # Business logic classes (ContributionService, etc.)
└── Traits/
    └── BelongsToChurch.php
```

### Controller Rules

Controllers are thin and single-action. A controller does exactly three things, in this order:

1. Build a DTO from the Form Request (`CreateContributionDTO::fromRequest($request)`)
2. Call one Action
3. Return a Resource response

Authorisation is not in the controller: the Form Request's `authorize()` asks a Policy (`return $this->user()->can('create', Contribution::class)`), so an unauthorised request is refused before any input is read.

```php
// Good
public function __invoke(StoreContributionRequest $request): JsonResponse
{
    $contribution = $this->action->execute(RecordContributionDTO::fromRequest($request));

    return ContributionResource::make($contribution)->response()->setStatusCode(201);
}

// Bad: business logic in the controller
public function store(Request $request): JsonResponse
{
    $data = $request->validate([...]);
    $contribution = Contribution::create($data);
    SMS::send($contribution->member->phone, "Receipt: GHS {$contribution->amount}");
    return response()->json($contribution, 201);
}
```

### Actions, Services and Repositories

An **Action** is one user intent (`RecordContributionAction`): a `final` class with a single public `execute(DTO)` method. It owns `DB::transaction()` and fires the domain event. An Action is called from controllers, jobs and commands, and never from another Action.

A **Service** holds rules that several Actions share. It never opens a transaction.

A **Repository** holds every query for one model, behind an interface (`ContributionRepositoryInterface`, implemented by `EloquentContributionRepository` and bound in `RepositoryServiceProvider`). Actions and Services depend on the interface, so unit tests swap in a mock.

```php
final class RecordContributionAction
{
    public function __construct(private readonly ContributionRepositoryInterface $contributions) {}

    public function execute(RecordContributionDTO $dto): Contribution
    {
        return DB::transaction(function () use ($dto): Contribution {
            $contribution = $this->contributions->create($dto->attributes());
            ContributionReceived::dispatch($contribution);

            return $contribution;
        });
    }
}
```

### Form Requests

All input validation belongs in a Form Request class. Never validate in the controller. Never use `$request->validate()` in a controller method.

```php
class StoreContributionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'member_id'    => ['nullable', 'ulid', 'exists:members,id'],
            'fund_type_id' => ['required', 'ulid', 'exists:fund_types,id'],
            'amount'       => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'payment_method' => ['required', 'in:momo,card,cash'],
            'contributed_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }
}
```

### API Resources

All response serialisation belongs in a Resource class. Never return a model directly from a controller. Never call `->toArray()` manually in a controller.

```php
class MemberResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'member_number' => $this->member_number,
            'full_name'    => "{$this->first_name} {$this->last_name}",
            'phone'        => $this->phone,
            'status'       => $this->status,
            // giving_summary only shown to authorised roles
            'giving_summary' => $request->user()->can('giving.summary')
                ? $this->givingSummary()
                : null,
        ];
    }
}
```

### React 19 (Frontend)

```
src/
├── assets/
├── components/
│   ├── ui/           # shadcn/ui primitives (Button, Input, Dialog, etc.)
│   └── app/          # Feature-specific components (MemberCard, OfferingForm)
├── hooks/            # Custom hooks (useAuth, useChurch, useToast, useMembers)
├── layouts/          # Page shell layouts (AppLayout, AuthLayout)
├── pages/            # Route-level page components (one per route)
├── router/           # React Router v7 config
├── services/         # Typed API call functions — never call axios directly in components
├── stores/           # Zustand stores (auth, church, ui)
├── types/            # Shared TypeScript types and API response interfaces
└── utils/            # Pure utility functions (formatGHS, formatDate, etc.)
```

**Component naming:** PascalCase, descriptive. `MemberDirectoryTable.tsx`, not `Table.tsx` or `MDT.tsx`.

**Custom hooks over copy-pasted logic.** Any shared logic across components becomes a custom hook in `src/hooks/` with the `use` prefix.

**No direct `axios` calls in components.** All API calls go through a typed service in `src/services/`. Components call the service via a TanStack Query hook; the service calls axios.

**TypeScript everywhere.** No `.jsx` files — all components are `.tsx`. All API response shapes have a type in `src/types/`. No `any`.

**Server state lives in TanStack Query, not Zustand.** Zustand holds UI and auth state only. Anything that comes from the API (members, contributions, services) is fetched and cached by TanStack Query hooks.

```tsx
// Correct — TanStack Query for server data
const { data: members, isLoading } = useQuery({
  queryKey: ['members', churchId],
  queryFn: () => membersService.list(),
});

// Correct — Zustand for client/auth state
const { user, church } = useAuthStore();

// Wrong — fetching in Zustand, or raw axios in a component
```

---

## Multi-Tenancy — Non-Negotiables

**Rule 1: Every Eloquent model that holds church data must use the `BelongsToChurch` trait.**

If you create a new model and it has a `church_id`, add the trait. If you're unsure whether it needs one, it does.

**Rule 2: Never use `withoutGlobalScope` in application code.**

`withoutGlobalScope('church')` exists for migrations, seeders, and console commands only. Using it in a controller or service is a security defect.

**Rule 3: Every new API endpoint needs a cross-tenant test.**

Before any PR merging a new endpoint is approved, it must include a test that:
1. Creates data for Church A
2. Authenticates as a user of Church B
3. Attempts to access Church A's data via the endpoint
4. Asserts `403` or `404` — not `200` with Church A's data

---

## Provider Abstraction — SMS and Payments

SMS and payment processing are routed through provider interfaces. No Job or Service class ever names a specific provider — they resolve it from the container.

### Contracts

Amounts crossing these interfaces are **decimal strings in GHS** (for example `"200.00"`), never floats and never pesewas. Conversion to a provider's own unit (Paystack wants pesewas) happens only inside that provider's adapter. Adapters also translate our `MomoNetwork` enum to the provider's codes (PayPerlez uses `at` for AirtelTigo).

```php
// app/Contracts/SmsProvider.php
interface SmsProvider
{
    public function send(string $phone, string $message): void;
}

// app/Contracts/PaymentProvider.php  (collecting money from a giver)
interface PaymentProvider
{
    /** Start a MoMo collection. $clientReference is our own ULID and doubles as the idempotency key. */
    public function collect(string $phone, string $amountGhs, string $clientReference, MomoNetwork $network): CollectionResult;

    /** Poll the provider; used by the reconciliation job for anything not yet final. */
    public function collectionStatus(string $clientReference): CollectionResult;

    public function verifyWebhook(Request $request): bool;

    public function extractReference(Request $request): string;
}

// app/Contracts/DisbursementProvider.php  (paying a church, D-018)
interface DisbursementProvider
{
    /** Pay $budgetGhs (the most we will spend, provider fee included) to the church's payout account. */
    public function disburse(PayoutAccount $account, string $budgetGhs, string $clientReference): DisbursementResult;

    public function disbursementStatus(string $clientReference): DisbursementResult;

    /** Available balance as a decimal string; the payout job checks it (minimum GHS 1.00 must remain). */
    public function availableBalance(): string;

    /** Verify a payout account and return the holder's name. */
    public function verifyAccount(PayoutAccount $account): string;
}
```

`CollectionResult` and `DisbursementResult` are immutable value objects: provider reference, a normalised status enum (`pending`, `succeeded`, `failed`, `unknown`; anything unrecognised maps to `unknown` and is polled, never assumed), the provider fee as a decimal string or null, and the amount delivered. Provider-specific response shapes and error codes never leave the adapter.

### Implementations

```
app/Services/
├── Sms/
│   ├── PayPerlezSmsProvider.php       # implements SmsProvider (default)
│   └── AfricasTalkingSmsProvider.php  # implements SmsProvider (fallback)
└── Payment/
    ├── PayPerlezPaymentProvider.php   # implements PaymentProvider + DisbursementProvider (MoMo, payouts)
    └── PaystackPaymentProvider.php    # implements PaymentProvider (cards, subaccount split)
```

Not built for the MVP: Moolre SMS/payment/USSD providers (USSD is deferred, D-019).

### Service Container Binding

Collections are resolved by **payment method**, not by one global primary, because MoMo and cards use different providers.

```php
// app/Providers/ProviderServiceProvider.php
public function register(): void
{
    $this->app->bind(SmsProvider::class, fn () => match (config('services.sms.primary')) {
        'africastalking' => new AfricasTalkingSmsProvider(),
        default          => new PayPerlezSmsProvider(),   // payperlez is default
    });

    $this->app->bind(DisbursementProvider::class, fn () => new PayPerlezPaymentProvider());

    // GivingProviderResolver::for(PaymentMethod::Momo) / ::for(PaymentMethod::Card)
    $this->app->bind(GivingProviderResolver::class, fn () => new GivingProviderResolver([
        PaymentMethod::Momo->value => config('services.payment.momo_provider'),   // payperlez
        PaymentMethod::Card->value => config('services.payment.card_provider'),   // paystack
    ]));
}
```

### Jobs use the interface, never a concrete class

```php
// app/Jobs/SendSmsJob.php
public function handle(SmsProvider $sms): void
{
    $sms->send($this->phone, $this->message);
}
```

### Webhook routes are provider-specific

Each provider has its own route, verification middleware, and processing job, because webhook shapes and authenticity schemes differ per provider (PayPerlez: timestamped HMAC header; Paystack: HMAC of the body; Moolre, if added: body secret plus IP allowlist). All jobs normalise to the same internal format before calling `GivingService`.

```php
// routes/api.php
Route::post('/webhooks/paystack',   [PaystackWebhookController::class,   'handle'])->middleware('verify.paystack');
Route::post('/webhooks/payperlez',  [PayPerlezWebhookController::class,  'handle'])->middleware('verify.payperlez');
// USSD (Moolre) webhook is deferred past the MVP, see D-019
```

`GivingService::record(array $normalised)` is provider-agnostic: it receives a normalised payload and creates the `Contribution` record. It knows nothing about Paystack or PayPerlez.

### `.env` toggles

```env
SMS_PRIMARY_PROVIDER=payperlez         # payperlez | africastalking
PAYMENT_MOMO_PROVIDER=payperlez        # MoMo collections and payouts
PAYMENT_CARD_PROVIDER=paystack         # cards only, when built
```

Switching providers = update `.env` + restart queue workers. Zero code changes, zero migrations for SMS. There is no automatic cross-provider fallback; the operator switches the primary in `.env` if one fails.

---

## Async First

**Every outbound network call is queued. No exceptions.**

Sending an SMS, dispatching a WhatsApp message, sending an email, calling an external API — all of it goes through a queue job. The HTTP response returns before the job runs.

**Why:** Africa's Talking can take 2–5 seconds to respond. Paystack webhooks can stall. An admin recording 50 contributions should not wait 2 minutes for SMS receipts to send.

```php
// Correct
SendSmsJob::dispatch($member->phone, $message);

// Wrong — blocks the request
AfricasTalking::sms()->send(['to' => $phone, 'message' => $message]);
```

The only exception: Paystack charge initiation, which must be synchronous because we need the redirect URL to return to the client.

---

## Error Handling

### Never silently swallow exceptions
```php
// Bad
try {
    $this->sendSms($phone, $message);
} catch (\Exception $e) {
    // silent
}

// Good
try {
    $this->sendSms($phone, $message);
} catch (\Exception $e) {
    Log::error('SMS send failed', ['phone' => $phone, 'error' => $e->getMessage()]);
    // Re-throw if the caller needs to know, or update message_log status to 'failed'
}
```

### Job failures
All queue jobs must implement `failed(Throwable $exception)`:

```php
public function failed(Throwable $exception): void
{
    Log::error('SendSmsJob failed', [
        'phone'   => $this->phone,
        'message' => $this->message,
        'error'   => $exception->getMessage(),
    ]);

    // Update the message_log record status to 'failed'
    if ($this->messageLogId) {
        MessageLog::where('id', $this->messageLogId)
            ->update(['status' => 'failed']);
    }
}
```

### API error responses
The `Handler.php` renders consistent JSON for all exception types. Do not return raw exception messages in production. Error messages should be human-readable, not stack traces.

---

## Database Practices

**No raw queries in application code.** Use Eloquent. If a query can't be expressed cleanly in Eloquent, use the Query Builder (`DB::table(...)`). Raw SQL strings are only acceptable in migrations.

**Eager load to prevent N+1.**
```php
// Bad — N+1 on members with their departments
$members = Member::all();
foreach ($members as $member) {
    echo $member->departments->pluck('name'); // hits DB for every member
}

// Good
$members = Member::with('departments')->paginate(25);
```

Use Laravel Telescope or Debugbar in development to catch N+1 queries before they reach PR review.

**Migration rules:**
- Every migration must be reversible (`down()` method must work).
- Never rename a column or table directly — add new, deprecate old, remove in a later migration after the code is updated.
- Never add a `NOT NULL` column without a default to an existing table with data — it will fail in production.
- All migrations must be backward-compatible with the currently deployed version of the application.

**Indexing:** Every `WHERE` clause that runs frequently needs an index. At a minimum: all `church_id` columns, all `status` columns used in filters, all foreign keys, all columns used in `UNIQUE` constraints.

---

## Naming Conventions

| Thing | Convention | Example |
|---|---|---|
| Model | PascalCase singular | `Member`, `Contribution` |
| Controller | PascalCase, `ResourceController` | `MemberController` |
| Form Request | PascalCase, action + model + `Request` | `StoreMemberRequest` |
| API Resource | PascalCase, model + `Resource` | `MemberResource` |
| Job | PascalCase, imperative verb | `SendSmsJob`, `ProcessPaystackWebhookJob` |
| Event | PascalCase, past tense noun | `ContributionReceived`, `MemberCheckedIn` |
| Service | PascalCase, noun + `Service` | `ContributionService` |
| Policy | PascalCase, model + `Policy` | `MemberPolicy` |
| DB table | snake_case, plural | `members`, `attendance_records` |
| DB column | snake_case | `church_id`, `contributed_at` |
| React component | PascalCase, `.tsx` | `MemberDirectoryTable.tsx` |
| Zustand store | camelCase, `use` prefix | `useAuthStore`, `useChurchStore` |
| Custom hook | `use` prefix, camelCase | `useToast`, `useMemberSearch` |
| Route name | dot-separated resource.action | `members.index`, `contributions.store` |
| API endpoint | kebab-case, plural resource | `/api/members`, `/api/fund-types` |

---

## Security Defaults

**All routes are authenticated by default.** The `auth:sanctum` and `tenant` middleware are applied globally to the `api` prefix. Unauthenticated routes (OTP endpoints, webhooks) are explicitly listed as exceptions.

**All policies check `church_id`.** Even if the global scope catches it in queries, policy methods verify the resource belongs to the current church before allowing any action.

**Never log PII.** Log job IDs, ULIDs, event names. Never log phone numbers, names, amounts in plain text. Log the member's ULID only.

**`APP_DEBUG=false` in production.** Always. No exceptions.

**Webhook endpoints verify signatures before doing anything else.** If the signature check fails, return 401 immediately. Do not process the payload.

---

## Code Review Checklist

Every PR must pass before merge:

- [ ] Does it follow the controller/service/resource pattern?
- [ ] Is there a Policy for every new model?
- [ ] Does every new model with `church_id` use `BelongsToChurch`?
- [ ] Are outbound calls (SMS, email, API) going through queue jobs?
- [ ] Are there N+1 queries? (check with Telescope)
- [ ] Does a new migration have a working `down()` method?
- [ ] Are Form Requests handling all validation (nothing in controller)?
- [ ] For financial or auth code: is there a test covering it?
- [ ] For new endpoints: is there a cross-tenant isolation test?
- [ ] Is `08-api.md` updated if the API changed?
- [ ] Is `07-data-model.md` updated if the schema changed?

---

## Code Style

**PHP:** Laravel Pint with the `laravel` preset. Run `./vendor/bin/pint` before committing. GitHub Actions enforces this — a PR with style violations will not merge.

**TypeScript/React:** ESLint with `@typescript-eslint` + `eslint-plugin-react-hooks` + Prettier. Formatted on save in VSCode (`.vscode/settings.json` committed to the repo).

**Commit messages:** Conventional Commits format:
```
feat(members): add family grouping and head-of-household role
fix(giving): prevent duplicate contribution on Paystack webhook retry
chore(deps): upgrade Laravel to 12.x
docs(api): document new /api/pledges endpoints
test(attendance): add cross-tenant isolation test for check-in endpoint
```

**Branch names:** `feat/member-family-grouping`, `fix/giving-webhook-duplicate`, `chore/upgrade-laravel`

---

## Performance Targets

| Operation | Target |
|---|---|
| Member directory search (1,000 members) | < 500ms |
| Sunday offering report generation | < 1s |
| Contribution record + receipt SMS dispatch | < 200ms (to UI) |
| Dashboard load | < 1s |
| CSV export (5,000 records) | < 30s (async — download link sent via SMS) |
| API p99 response time | < 800ms |

Monitor these targets in staging before every release. See [`17-monitoring-and-alerting.md`](./17-monitoring-and-alerting.md).

---

## Related Documents

- [`10-definition-of-done.md`](./10-definition-of-done.md) — when a feature is truly finished
- [`15-security.md`](./15-security.md) — security-specific rules
- [`16-deployment-and-ops.md`](./16-deployment-and-ops.md) — deployment standards
