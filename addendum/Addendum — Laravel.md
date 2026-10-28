# Addendum — Laravel

> Read this file alongside `CLAUDE.md` at the start of every session on a Laravel project. This file adds Laravel-specific rules on top of the universal baseline. Where this file and `CLAUDE.md` conflict, `CLAUDE.md` wins unless this file explicitly states otherwise.

## Stale Pattern Watch List — Laravel

AI tools frequently generate outdated Laravel patterns from older training data. Before finalising any Laravel code, check this list. If you see any of these patterns in your own output — stop and rewrite.

**Authentication & Authorization**

- ❌ `Auth::routes()` — deprecated; use Breeze or Jetstream starter kits
- ❌ Manual `if ($user->role === 'admin')` checks in controllers — use Policies
- ❌ `$this->middleware()` in controller constructors (Laravel 11+) — use route middleware directly
- ❌ `auth()->user()` in service classes — inject the user or pass it as a parameter

**Eloquent & Database**

- ❌ `DB::table('orders')->...` for application queries — use `Order::query()->...`
- ❌ `$request->all()` passed to `Model::create()` without explicit fillable protection
- ❌ Lazy loading inside loops — always eager load with `with()`
- ❌ Raw `DB::select('SELECT ...')` with string interpolation — SQL injection risk
- ❌ `Carbon::now()` — use `now()` helper or `CarbonImmutable`; set `Date::use(CarbonImmutable::class)` in boot
- ❌ `$model->save()` after `$model->property = value` scattered everywhere — use `$model->update([...])`
- ❌ MySQL native `ENUM` column type in migrations — use `string` and enforce with PHP enums

**Controllers & Requests**

- ❌ `$request->validate([...])` inline in controllers — use Form Request classes
- ❌ Business logic in controllers — it belongs in Services or Actions
- ❌ Returning `$model->toArray()` or raw Eloquent collections from API controllers — use API Resources
- ❌ `response()->json($data)` with raw arrays — use `new OrderResource($order)` or `OrderResource::collection($orders)`

**Queues & Jobs**

- ❌ Sending emails synchronously in controllers — use `Mail::queue()` or dispatch a job
- ❌ Jobs without `$tries`, `$timeout`, `$backoff`, and `failed()` declared
- ❌ `Queue::push()` — use `dispatch()` helper

**Filament (if used)**

- ❌ Filament v2 syntax (`Tables\Columns\TextColumn::make()` without proper v3/v5 chaining)
- ❌ `$this->form(Form $form)` old-style resource methods — check installed Filament version via `composer.json` and search-docs before writing any Filament code
- ❌ Missing `->authorize()` on Filament actions

**Livewire (if used)**

- ❌ Livewire v2 syntax (`$emit`, `$listeners` array) — Livewire 4 uses `$dispatch()` and `#[On]`
- ❌ `wire:model` without `.live` or `.blur` modifier where real-time validation is needed
- ❌ Business logic directly in Livewire `mount()` or `render()` — delegate to services

**General**

- ❌ `env('APP_KEY')` in application code — use `config('app.key')`
- ❌ `Storage::disk('local')->...` hardcoded — use configured disk from `config/filesystems.php`
- ❌ `__DIR__` or `base_path()` for storage — use `Storage::` facade
- ❌ `array_map`, `array_filter`, `array_values` on Eloquent collections — use Laravel Collection methods (`->map()`, `->filter()`, `->values()`)

**Version check rule:** always read `composer.json` for Laravel, Livewire, and Filament versions at the start of a session. If Laravel Boost MCP is connected, run `search-docs` before using any API you are not 100% certain about for that exact version.

## Framework-First — Laravel

> Use Laravel. Not PHP.

Laravel is the framework. PHP is the language beneath it. Every time you reach for raw PHP where Laravel has a built-in solution, you are working against the framework.

**Use Laravel's tools — always:**

- **Routing** → `Route::get()`, `Route::apiResource()` — not manual routing
- **Database** → Eloquent, `Model::query()`, relationships — not raw PDO or `DB::select()` with string SQL
- **Validation** → Form Requests — not `$request->validate()` inline, not manual array checks
- **Auth** → Sanctum/Passport, Policies, Gates — not manual `if ($user->role === 'admin')`
- **Queues** → `dispatch()`, `ShouldQueue` — not custom queue implementations
- **Events** → Laravel Events + Listeners — not custom observer patterns
- **Cache** → `Cache::` facade — not manual Redis calls
- **Storage** → `Storage::` facade — not `file_get_contents()` or raw S3 SDK
- **Mail** → Mailables + `Mail::` facade — not PHPMailer or raw SMTP
- **HTTP** → `Http::` client (Laravel's Guzzle wrapper) — not raw cURL or Guzzle directly
- **Testing** → Pest + `RefreshDatabase` + `actingAs()` — not PHPUnit with manual setup
- **Artisan** → `php artisan make:*` generators — not hand-crafting framework files
- **Config** → `config()` helper — not `env()` directly in application code
- **Dates** → `CarbonImmutable` via `Date::use()` — not native `DateTime`

**Official MCPs and skills:**

- If **Laravel Boost MCP** is connected, use it — `search-docs` before writing code, `database-schema` before writing migrations, `database-query` for debugging
- If a **Laravel skill file** exists in `**/skills/**`, load it before working in that domain
- Laravel Boost's `search-docs` returns version-specific documentation — always prefer it over training-data knowledge for Eloquent, Filament, Livewire, and package APIs

**The rule in one line:** if Laravel has a facade, helper, or artisan command for it, use that — never the raw PHP equivalent.

## 1. How This File Works

This file extends `CLAUDE.md` for Laravel projects. Read both files at the start of every session on a Laravel project — this file first for context, then `CLAUDE.md` for the universal rules.

This file covers **Laravel-specific implementation** of the universal principles. Where `CLAUDE.md` says *"use a validation layer"*, this file says *"use Form Requests"*. Where `CLAUDE.md` says *"use Policies for authorization"*, this file shows how.

**Additionally, read the relevant pattern file before building any of these features:**

- Caching → `docs/patterns/caching.md`
- File uploads → `docs/patterns/file-uploads.md`
- Email/notifications → `docs/patterns/email-notifications.md`
- Background jobs (complex) → `docs/patterns/background-jobs-deep.md`
- Outbox pattern → `docs/patterns/outbox-pattern.md`
- Zero-downtime migrations → `docs/patterns/zero-downtime-migrations.md`

## 2. Stack & Version Conventions

- Always detect the Laravel version from `composer.json` before starting work — rules differ between major versions
- Minimum supported stack for new projects: **Laravel 11+, PHP 8.2+**
- `declare(strict_types=1)` in every file under `app/`
- PHP 8.2+ constructor property promotion required — no verbose property declarations
- Use `php artisan make:*` generators for all framework files — never hand-create controllers, models, migrations, jobs, events, listeners, policies, or requests
- Pass `--no-interaction` to all artisan commands in scripts
- Run `vendor/bin/pint --dirty --format agent` after every PHP file change
- Run PHPStan / Larastan after every session — keep it at level 8 minimum
- Use `config()` helper everywhere — never call `env()` outside of `config/*.php` files
- Use `Model::query()` as the starting point for all Eloquent queries — never call static methods directly on models for queries
- Immutable dates: use `CarbonImmutable` — set in `AppServiceProvider::boot()` via `Date::use(CarbonImmutable::class)`
- Prevent lazy loading in non-production: `Model::preventLazyLoading(!app()->isProduction())` in `AppServiceProvider::boot()`

## 3. Project Structure & Naming

**Standard directory layout:**

```
app/
  Actions/           ← one action class per user intent
  DTOs/              ← typed data transfer objects
  Enums/             ← all PHP backed enums
  Events/            ← domain events
  Exceptions/        ← custom domain exceptions
  Http/
    Controllers/     ← thin HTTP controllers
    Middleware/      ← request pipeline middleware
    Requests/        ← Form Request validation classes
    Resources/       ← API Resource response transformers
  Jobs/              ← queued jobs
  Listeners/         ← event listeners
  Models/            ← Eloquent models
  Notifications/     ← notification classes
  Policies/          ← authorization policies
  Providers/         ← service providers
  Repositories/      ← data access layer (optional but recommended)
  Services/          ← reusable business logic
  ValueObjects/      ← immutable domain value objects
```

**Naming conventions:**

| Class type | Convention | Example |
| --- | --- | --- |
| Model | PascalCase singular | `Order`, `BlogPost` |
| Controller | PascalCase + Controller | `OrderController` |
| Form Request | PascalCase + Request | `StoreOrderRequest` |
| API Resource | PascalCase + Resource | `OrderResource` |
| Policy | PascalCase + Policy | `OrderPolicy` |
| Action | VerbNoun + Action | `CreateOrderAction` |
| Service | PascalCase + Service | `OrderService` |
| Job | PascalCase verb phrase | `ProcessPaymentJob` |
| Event | PascalCase past tense | `OrderPlaced` |
| Listener | PascalCase verb phrase | `SendOrderConfirmation` |
| Enum | PascalCase noun | `OrderStatus`, `PaymentMethod` |
| DTO | VerbNoun + DTO | `CreateOrderDTO` |
| Exception | PascalCase + Exception | `InsufficientStockException` |
| Migration | snake\_case description | `create_orders_table` |
| Blade view | kebab-case | `order-card.blade.php` |

## 4. Eloquent Rules

**Models**

- Every model uses the `HasUlids` trait — ULID is the external identifier; integer `id` never leaves the database
- Override `getRouteKeyName()` to return `'ulid'` on every model
- Use `$casts` for all type coercions — enums, dates, booleans, encrypted fields, JSON
- Use `$fillable` (whitelist) not `$guarded = []` — explicit mass assignment protection
- Accessors and mutators for computed or transformed attributes — named `get{Attribute}Attribute()` / use new `Attribute::make()` syntax in Laravel 9+
- No business logic in models — models own data structure, relationships, scopes, and casts only
- Soft deletes via `SoftDeletes` trait on any model where history matters

**Relationships**

- Define all relationships on both sides — if `Order hasMany Item`, then `Item belongsTo Order`
- Always eager load — `Order::with(['items', 'customer'])` never lazy-load in loops
- Use `withCount()` instead of loading a full relationship just to count it
- Use `loadMissing()` when conditionally loading relationships to avoid re-querying

**Scopes**

- Use local scopes for every repeated filter — `scopeActive()`, `scopeForTenant()`, `scopePending()`
- Use global scopes for filters that must always apply — tenant isolation, soft delete exclusion
- Name scopes as verbs or adjectives that read naturally: `->active()`, `->forTenant($id)`, `->pendingPayment()`

**Observers**

- Use model observers for side effects triggered by model lifecycle events — `created`, `updated`, `deleted`
- Keep observers single-purpose — one observer handles one concern
- Register observers in a Service Provider, not in `AppServiceProvider` directly if the list is long
- Do not put business logic in observers — they dispatch events or update related data only

**Casts**

```php
protected $casts = [
    'status'       => OrderStatus::class,      // enum cast
    'metadata'     => 'array',                 // JSON cast
    'verified_at'  => 'immutable_datetime',    // immutable Carbon
    'ghana_card'   => 'encrypted',             // encrypted at rest
    'is_active'    => 'boolean',
];
```

## 5. Service, Action & Repository Layer

**Actions**

- One action per user intent: `CreateOrderAction`, `CancelOrderAction`, `ProcessRefundAction`
- Single public method: `execute(CreateOrderDTO $dto): Order`
- Owns the transaction boundary — `DB::transaction()` lives inside the action
- Fires domain events — the action calls `event(new OrderPlaced($order))`
- Called from controllers, Livewire components, CLI commands, and jobs — never from other actions directly

```php
final class CreateOrderAction
{
    public function execute(CreateOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto): Order {
            $order = Order::create([...]);
            $order->items()->createMany($dto->items);
            event(new OrderPlaced($order));
            return $order;
        });
    }
}
```

**Services**

- Reusable logic called by multiple actions or controllers: `PricingService`, `InventoryService`
- No transaction boundaries — services do not own transactions; actions do
- No HTTP knowledge — services never touch `$request` or return responses
- Injected via constructor, resolved from the container

**Repositories (optional but recommended for complex domains)**

- All Eloquent queries for one model live in one repository: `OrderRepository`
- Interface + implementation: `OrderRepositoryInterface` → `EloquentOrderRepository`
- Bind in a Service Provider: `$this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class)`
- Makes testing trivial — swap with a mock repository in tests
- Methods named for intent: `findByUlid()`, `findPendingForTenant()`, `countThisMonth()`

**Controller rules**

- Thin — receives request, validates via Form Request, calls one action, returns response
- One public method per route — no private helper methods that grow into hidden business logic
- Returns API Resources, redirects, or JSON — never raw arrays or model instances
- Never calls repositories directly — goes through actions or services

```php
public function store(StoreOrderRequest $request): OrderResource
{
    $order = $this->createOrder->execute(
        CreateOrderDTO::fromRequest($request)
    );

    return new OrderResource($order);
}
```

## 6. Form Requests, Policies & API Resources

**Form Requests**

- Every controller method that accepts input has its own Form Request — never `$request->validate()` inline
- Named after the action: `StoreOrderRequest`, `UpdateProfileRequest`, `ProcessRefundRequest`
- `authorize()` method delegates to a Policy — never hardcode role checks here
- `rules()` returns the validation rules — use Laravel's full rule set including `Rule::enum()`, `Rule::exists()`, `Rule::unique()`
- `messages()` for custom validation messages
- `prepareForValidation()` to normalize input before validation (strip spaces from phone numbers, lowercase email)
- Static `fromRequest()` factory on the DTO — keeps the controller one line: `CreateOrderDTO::fromRequest($request)`

**Policies**

- Every model that can be authorized against has a Policy: `OrderPolicy`, `CarPolicy`
- Register policies in `AuthServiceProvider` — or use auto-discovery in Laravel 9+
- Methods match controller actions: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`
- Add custom methods for custom actions: `cancel`, `refund`, `approve`
- Never check `$user->role === 'admin'` inline anywhere — always go through a Policy
- Use `$this->authorize()` in controllers or `Gate::authorize()` in services

**API Resources**

- Every API response goes through a Resource — never return raw models or `->toArray()`
- Named after the model: `OrderResource`, `OrderCollection`
- Never expose the integer `id` — use `ulid` renamed to `id` in the response
- Money fields: always expose both raw integer and formatted string
- Enum fields: expose the string value; add `status_label` for the human-readable label
- Nested resources: use `->whenLoaded()` to avoid N+1 — only include relations when they are already loaded
- Resource collections extend `ResourceCollection` for consistent pagination metadata

```php
public function toArray(Request $request): array
{
    return [
        'id'               => $this->ulid,
        'status'           => $this->status->value,
        'status_label'     => $this->status->label(),
        'amount'           => $this->amount_cents,
        'amount_formatted' => $this->amount_formatted,
        'customer'         => new CustomerResource($this->whenLoaded('customer')),
        'created_at'       => $this->created_at->toIso8601String(),
    ];
}
```

## 7. Events, Listeners & Jobs

**Events**

- Named in past tense: `OrderPlaced`, `PaymentConfirmed`, `KycApproved`
- Fired from Actions — never from controllers or Livewire components directly
- Carry the relevant model(s) as constructor arguments — listeners receive the full context
- Use `ShouldBroadcast` when the event needs to reach the frontend in real time

**Listeners**

- One listener = one side effect: `SendOrderConfirmationEmail`, `UpdateInventoryOnOrder`, `LogPaymentAuditTrail`
- Implement `ShouldQueue` by default — any side effect that does not need to complete before the response
- Declare `$queue`, `$delay`, `$tries`, `$backoff`, `$timeout` on every queued listener
- Register in `EventServiceProvider::$listen` array

**Jobs**

- Every job must declare:

```php
public int $tries = 3;
public int $timeout = 60;
public array $backoff = [10, 30, 60];

public function failed(Throwable $exception): void
{
    // notify admin, mark record as failed, never silent
}
```

- Route to named queues — never leave all jobs on `default`
- Use `ShouldBeUnique` for jobs that must not run more than once simultaneously for the same resource
- Use `WithoutOverlapping` middleware for jobs that must not overlap
- Use `ThrottlesExceptions` middleware for jobs calling unreliable external APIs
- Test jobs with `Queue::fake()` — assert dispatched, not the job's internal logic (test that separately as a unit)

**Queue segmentation:**

```php
// routes/jobs.php or AppServiceProvider
Queue::after(function (JobProcessed $event) { ... });

// In job class
public string $queue = 'notifications';
```

| Queue | Jobs routed to it |
| --- | --- |
| `critical` | OTPs, transactional auth, payment confirmations |
| `notifications` | Order updates, status emails |
| `external-api` | Third-party API calls |
| `processing` | File processing, report generation |
| `default` | Everything else |

## 8. Laravel Security Rules

**Authentication**

- Use Laravel Sanctum for SPA / mobile API authentication
- Use Laravel Passport for full OAuth2 server requirements
- Token abilities/scopes to restrict what each token can do
- Revoke tokens on password change and logout
- `auth:sanctum` middleware on all protected API routes

**Authorization**

- Register every Policy in `AuthServiceProvider`
- Use `$this->authorize('action', $model)` in controllers — never inline role checks
- Gate checks in services: `Gate::authorize('update', $order)`
- Super-admin bypass: use a Gate `before()` check in `AuthServiceProvider` — not sprinkled across policies

**Rate Limiting**

- Define named limiters in `RouteServiceProvider` or `AppServiceProvider`
- Apply per-route: `->middleware('throttle:api')`
- Separate limiters for auth endpoints, cost-triggering endpoints, and general API
- Custom response on rate limit: return 429 with `Retry-After` header

**Input Security**

- `prepareForValidation()` in Form Requests for normalization (strip spaces, lowercase)
- Encrypted casts on sensitive fields — never store raw Ghana Card, TIN, bank account numbers
- Signed temporary URLs for all private file access: `Storage::temporaryUrl($path, now()->addMinutes(5))`
- Never use `{!! !!}` in Blade without explicit sanitization

**Headers**

- Force HTTPS in production: `URL::forceScheme('https')` in `AppServiceProvider`
- Add security headers via middleware: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Content-Security-Policy`
- Never expose the Laravel version in response headers — remove `X-Powered-By`

## 9. Filament Admin Panel Rules

Apply this section only on projects using Filament.

- Every Filament Resource must have a corresponding Policy registered and respected — never bypass with `canX()` overrides unless intentional and documented
- Use `->authorize('action')` on all Filament actions that modify data
- Group form fields using `Section`, `Fieldset`, or `Tabs` — never a flat single-column form
- Use `->columns(2)` as the default form layout — adjust per section as needed
- Build complex read-only detail pages as custom `Infolist` views — not reusing the edit form as a view
- The shipment timeline, order history, or any multi-step view must be a custom Infolist component — not a dropdown or a generic list
- Navigation badges via `getNavigationBadge()` for actionable counts (pending approvals, unread items)
- Admin panel route prefix: `/admin` — never the default `/` root
- Separate admin authentication from customer authentication — admin uses email + password always
- Custom Filament pages for dashboards and analytics — not generic resource index pages
- Never expose customer PII in Filament table columns that appear in exported CSVs unless explicitly required and access-controlled

## 10. Livewire Rules

Apply this section on projects using Livewire for the frontend.

**Component design**

- One Livewire component = one responsibility — never a mega-component handling multiple unrelated concerns
- Public properties are the component's reactive state — keep them typed and minimal
- Use `#[Computed]` attributes for derived values — never recompute in `render()`
- No business logic in Livewire components — delegate to Actions and Services
- No direct Eloquent queries in components — call a repository or service method

**Events & Actions**

- Use `wire:click`, `wire:submit`, `wire:model` — never raw JavaScript event listeners on Livewire elements
- Use `$dispatch()` for cross-component communication — not direct method calls between components
- Use `$dispatchTo()` when targeting a specific component instance
- Validate in Livewire with `#[Validate]` attribute or `$rules` property for real-time validation
- For complex validation, delegate to a Form Request class

**Performance**

- Use `wire:key` on every element inside `@foreach` — without it Livewire cannot track DOM updates correctly
- Use `wire:loading` to show loading states on any action with a perceptible delay
- Disable buttons during processing: `wire:loading.attr="disabled"`
- Use `wire:model.live.debounce.500ms` for search inputs — never `wire:model.live` without debounce
- Lazy load heavy components: `<livewire:heavy-component lazy />`
- Use `->stream()` for long-running operations that should update the UI progressively

**Security**

- Never expose sensitive data as public Livewire properties — they are visible in the DOM
- Validate and authorize in every Livewire action method — treat each as an independent HTTP request
- Never trust Livewire property values from the client for authorization — re-fetch from DB when the action runs

## 11. Testing in Laravel

**Use Pest** — not raw PHPUnit. Pest is the Laravel community standard and produces cleaner, more readable tests.

**Test structure:**

```
tests/
  Feature/
    Auth/
    Admin/
    Customer/
    Api/
  Unit/
    Actions/
    Services/
    Models/
    Enums/
    ValueObjects/
```

**Feature tests — for every HTTP endpoint and Livewire component:**

- Use `RefreshDatabase` trait — fresh DB state per test
- Use `actingAs($user)` for authenticated tests
- Assert HTTP status, response structure, and database state
- Always test: happy path, unauthenticated (redirect/401), unauthorized (403), validation failure (422)
- Use `assertDatabaseHas()`, `assertDatabaseMissing()`, `assertSoftDeleted()`

**Unit tests — for Actions, Services, and pure logic:**

- No database — mock repositories or use in-memory implementations
- Test one method at a time — input in, assert output or side effects
- Use `Event::fake()`, `Mail::fake()`, `Queue::fake()`, `Notification::fake()`, `Storage::fake()`, `Http::fake()`

**Factories:**

- Every model has a factory — use them for all test data creation
- Define named states for common scenarios: `->admin()`, `->suspended()`, `->withOrders()`
- Never manually create model instances in tests — always use factories
- Use `->has()` and `->for()` for related models: `Order::factory()->for($user)->has(Item::factory()->count(3))->create()`

**Run commands:**

```bash
php artisan test --compact                          # full suite
php artisan test --compact --filter=OrderTest       # single test class
php artisan test --compact tests/Feature/Auth/      # directory
```

**Never move on until the full suite is green.**

## 12. Laravel-Specific Checklist

Run this alongside the universal checklist in `CLAUDE.md` Section 26.

**Models & Eloquent**

- [ ] `HasUlids` trait on every model?
- [ ] `getRouteKeyName()` returns `'ulid'`?
- [ ] No integer `id` in any URL, response, or view?
- [ ] All enum-like fields cast to PHP Enum classes?
- [ ] Sensitive fields using `encrypted` cast?
- [ ] All relationships defined on both sides?
- [ ] Eager loading used — no lazy loading in loops?
- [ ] Model scopes used for repeated filters?
- [ ] `$fillable` defined — no `$guarded = []`?

**Architecture**

- [ ] Controllers are thin — one action call per method?
- [ ] Business logic in Actions or Services — not controllers?
- [ ] Form Requests for all validation — no inline `$request->validate()`?
- [ ] Policies for all authorization — no inline role checks?
- [ ] API Resources for all responses — no raw model returns?
- [ ] Actions own the transaction boundary?
- [ ] Events fired from Actions — not controllers?
- [ ] Listeners implement `ShouldQueue` for non-critical side effects?

**Jobs & Queues**

- [ ] Every job declares `$tries`, `$timeout`, `$backoff`, and `failed()`?
- [ ] Jobs routed to correct named queues?
- [ ] `ShouldBeUnique` on jobs that must not run concurrently for the same resource?

**Security**

- [ ] Sanctum configured for SPA/API auth?
- [ ] All protected routes behind `auth:sanctum` middleware?
- [ ] Policies registered and enforced?
- [ ] Private files served via `Storage::temporaryUrl()`?
- [ ] Security headers set in middleware?

**Code Quality**

- [ ] Pint run after every change (`vendor/bin/pint --dirty --format agent`)?
- [ ] PHPStan / Larastan passing at level 8+?
- [ ] `declare(strict_types=1)` in every `app/` file?
- [ ] No `env()` calls outside `config/*.php`?

**Testing**

- [ ] Pest used — not raw PHPUnit?
- [ ] Every endpoint has a feature test covering happy path, auth, validation, edge cases?
- [ ] `Queue::fake()`, `Mail::fake()`, `Event::fake()` used in relevant tests?
- [ ] All test data created via factories with named states?
- [ ] Full suite green?
