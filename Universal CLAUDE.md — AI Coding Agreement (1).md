# Universal CLAUDE.md — AI Coding Agreement

Oct 26, 2026 · @Ohene

> This is the universal coding agreement between the developer and any AI coding agent. Read this file completely before writing a single line of code. Re-read it at the start of every session and every new feature. If anything in this file conflicts with a project-specific override, the project override wins — but only where explicitly stated.

## How This Document System Works

This file (`CLAUDE.md`) is the universal baseline. It applies to every project, every language, every framework, every feature. Read it completely at the start of every session.

This file is intentionally scoped to principles that apply universally. Feature-specific and stack-specific depth lives in companion files that you read **when the feature or stack is relevant** — not all at once.

**Stack addendums** — read the relevant one at session start alongside this file:

| File | Read when |
| --- | --- |
| `docs/addendums/laravel.md` | Project uses Laravel |
| `docs/addendums/react.md` | Project uses React |
| `docs/addendums/vue.md` | Project uses Vue |
| `docs/addendums/typescript-backend.md` | Project uses Node / Hono / Express / Fastify |

**Pattern files** — read the relevant one before building that feature:

| File | Read when |
| --- | --- |
| `docs/patterns/caching.md` | Building any caching layer |
| `docs/patterns/email-notifications.md` | Building email or notification features |
| `docs/patterns/file-uploads.md` | Building file upload or media features |
| `docs/patterns/outbox-pattern.md` | Publishing events alongside DB writes |
| `docs/patterns/zero-downtime-migrations.md` | Running schema changes on a live database |
| `docs/patterns/audit-trails.md` | Building change history on any model |
| `docs/patterns/real-time.md` | Building WebSocket or live update features |
| `docs/patterns/search.md` | Building full-text or structured search |
| `docs/patterns/rate-limiting.md` | Implementing rate limiting beyond basic throttle |
| `docs/patterns/background-jobs-deep.md` | Building complex job chains, batches, or unique jobs |
| `docs/patterns/webhooks-outbound.md` | Sending webhooks to external consumers |
| `docs/patterns/pagination-deep.md` | Choosing and implementing a pagination strategy |
| `docs/patterns/batch-operations.md` | Building bulk create/update/delete endpoints |
| `docs/patterns/state-management.md` | Designing frontend state architecture |
| `docs/patterns/data-migrations.md` | Migrating existing data in production |
| `docs/patterns/ddd.md` | Applying domain-driven design |
| `docs/patterns/event-sourcing.md` | Building event stores or CQRS |
| `docs/patterns/load-testing.md` | Load and stress testing before launch |
| `docs/patterns/secrets-management.md` | Managing secrets beyond environment variables |

## 0. Precedence Rules & How to Use This File

This file is the universal baseline for every project. Read it completely before writing a single line of code. Re-read it at the start of every session and every new feature. If you are ever unsure whether something violates these rules, stop and re-read.

**Precedence order:**

1. This file (universal baseline)
2. Project-specific overrides (a `PROJECT.md`, `REQUIREMENTS.md`, or equivalent context file)
3. Framework and package defaults
4. Tool suggestions

Non-negotiables always win, regardless of precedence level. A project override can extend or replace a default — it cannot remove a non-negotiable.

**At the start of every new project:**

- Look for a project context file (`PROJECT.md`, `REQUIREMENTS.md`, `docs/context.md`, or similar)
- If it exists: read it fully before touching any code
- If it does not exist: stop and ask the user to either point to one or answer a short set of clarifying questions — then offer to write the answers into a new context file for future sessions
- Never assume or invent project-specific details (domain models, integrations, business rules) that are not confirmed by the context file or the user directly

DRY · KISS · YAGNI · Tests · Typed · Enums · Reusable · External IDs only · Layers respected · Checklist passed · **Framework first — use the framework, not the raw language**

## 1. Session Workflow

**At the start of every session:**

1. Read this file (and the project context file if one exists)
2. Detect the stack — read `package.json`, `composer.json`, route files, models, `.env.example`, config files
3. Check for `CHANGELOG.md` in the project root — confirm it is up to date; create it if missing (standard Keep a Changelog format)
4. Check the current sprint or task list — build only what is needed now
5. Ask before starting work:
   - Is this KISS — simplest viable approach?
   - Is this DRY — any logic to reuse or extract?
   - Is this YAGNI — are we building only what is needed now?
   - Are tests needed for this change?
   - Does `CHANGELOG.md` need an entry?

**Before writing any framework-specific code — verify against current docs:**

- Check the installed framework version from `composer.json`, `package.json`, or equivalent
- If a framework MCP is connected (Laravel Boost, etc.), run `search-docs` for the specific API you are about to use — do not rely on training-data knowledge for framework APIs
- If no MCP is available, explicitly note the version you detected and flag any APIs you are uncertain about as needing human verification
- Treat your own generated code as a first draft that must be verified — not as ground truth

**After generating framework code — self-check for stale patterns:**

- Check the stack addendum's "Stale Pattern Watch List" before finalising any code
- Ask: *"Is this the current idiomatic way to do this, or is this how it was done in an older version?"*
- If unsure: search the docs via MCP, or flag the line with a comment for human review
- Never ship code you cannot verify against the current installed version

**During work:**

- Build one thing at a time — one feature, one task, one commit
- Get it working, get it tested, get it green — then move on
- Never leave completed, verified work uncommitted
- If a decision changes the architecture, document it before continuing

**After every successfully implemented and verified unit of work:**

1. Add the `CHANGELOG.md` entry first
2. Create a git commit covering that implementation and its changelog entry (Conventional Commits format — see Section 25)
3. Never batch unrelated work into one commit
4. Make the commit even for small changes — it makes reverting safe

**At the end of every session:**

- Confirm all tests pass
- Confirm `CHANGELOG.md` is updated
- Confirm all completed work is committed
- Sign off: *"I am done. I have read CLAUDE.md for this session and implemented all the instructions in it."*

## 2. Standard Commands

Every project must define these standard scripts so the workflow is identical across all stacks. Adapt the underlying tools to the language/framework, but keep the command names the same.

| Command | Purpose |
| --- | --- |
| `[runner] run dev` | Start local development environment (server + queue + HMR) |
| `[runner] run test` | Run full test suite |
| `[runner] run lint` | Auto-fix lint and formatting issues |
| `[runner] run lint:check` | Check lint and formatting without fixing |
| `[runner] run setup` | Fresh project setup (install, key gen, migrate, seed) |
| `[runner] run build` | Production build |

**Backend (PHP/Laravel):** `composer run dev`, `composer run test`, etc. **Frontend (Node):** `npm run dev`, `npm run test`, etc. **Python:** adapt to `make` or `invoke` equivalents.

**Always-available direct commands:**

- Lint/format: run the formatter directly on changed files only (e.g. `pint --dirty`, `prettier --write`)
- Static analysis: run the analyser (e.g. `phpstan analyse`, `tsc --noEmit`, `eslint`)
- Single test: filter by name or file

Static analysis and formatting are **required on every project** — install and configure them at setup if not already present. Both must pass before any work is marked done.

## 3. Global Non-Negotiables

These rules apply to every project, every language, every framework. They cannot be overridden by a project context file. If a user asks you to skip any of them, politely decline and explain why the rule exists.

## 3a. Framework-First Principle

> **If you are using a framework, use the framework — not the underlying language.**

This is non-negotiable and applies to every project, every session, every feature.

**What this means in practice:**

- Use the framework's built-in tools, helpers, and conventions before reaching for raw language features
- Use the framework's official MCP server and skills when they are available — they are loaded for a reason
- A Laravel project uses Eloquent, not raw PDO. It uses `collect()`, not plain PHP arrays. It uses `dispatch()`, not manual queue connections.
- A React project uses React Router, not `window.location`. It uses React Hook Form, not uncontrolled inputs.
- A Vue project uses `defineProps`, not manual prop passing. It uses Pinia, not a plain reactive object.
- A Node/Hono project uses Hono's middleware, not raw `req`/`res` handlers.

**For AI agents specifically:**

- At the start of every session, check which MCP servers are connected — Laravel Boost, Chrome DevTools, Google Workspace, etc.
- Read the skill files for the active framework before writing code — they contain framework-specific conventions that override general defaults
- Use `php artisan make:*` generators, not hand-crafted files. Use Filament resource builders, not hand-written admin views. Use Laravel Scout for search, not a raw Meilisearch client.
- The framework's way is the right way unless there is a documented, specific reason to go lower-level

**Why this matters:**

- Frameworks encode years of community best practices — bypassing them means reinventing solved problems
- AI tools that go raw-language often produce code that fights the framework instead of working with it
- Framework-idiomatic code is more readable, more maintainable, and more likely to be correct
- Official MCPs and skills contain version-specific documentation that is more accurate than training data

**The test:** before writing any code, ask — *"Does the framework already have a way to do this?"* If yes, use it.

- **KISS** — write the simplest code that correctly solves the problem; if a junior developer would have to Google what your code does, rewrite it
- **DRY** — if the same logic appears in more than one place, extract it; copy-paste is a signal to abstract
- **YAGNI** — build only what the current task requires; do not add fields, methods, or features "just in case"
- **Tests required** — every behavioural change must have tests; write them before or alongside the feature, never after
- **Full test suite must be green** before marking any work done
- **CHANGELOG.md must be updated** for every session with real work
- **No secrets in code** — all keys, passwords, tokens, and API secrets go in environment variables; never hardcoded
- **No raw integer IDs exposed externally** — every table has an internal integer primary key and a separate external identifier (ULID, UUID, or slug); the integer never appears in any URL, API response, or frontend view
- **No weakened tests** — never delete, skip, comment out, or loosen assertions in a test without explicit approval
- **Static analysis must pass** — no committed code with type errors or lint violations
- **Formatter must run** — run the project formatter on every changed file before committing
- **One responsibility per unit** — every class, function, component, and module does exactly one thing
- **Layers must be respected** — controllers/views never contain business logic; business logic never reaches the presentation layer directly

## 4. Code Conventions

**Universal (all languages)**

- Use strict/strongly-typed mode wherever the language supports it (`strict_types=1` in PHP, `strict: true` in TypeScript, type hints in Python)
- Explicit return types on all functions and methods
- No magic strings — use constants, enums, or named values
- Keep functions and methods short — one function does one thing; if it does more, split it
- Prefer explicit over implicit — do not rely on language magic unless it is established framework convention
- Use the language's standard formatter and run it on every change
- Use the language's static analyser and keep it passing

**Naming conventions (technology-agnostic)**

| Concept | Convention | Example |
| --- | --- | --- |
| Classes / Types | PascalCase | `OrderService`, `UserProfile` |
| Functions / Methods | camelCase | `createOrder`, `formatMoney` |
| Variables | camelCase | `orderId`, `totalAmount` |
| Constants | UPPER\_SNAKE\_CASE | `MAX_RETRY_COUNT` |
| Database tables | snake\_case plural | `order_items`, `user_profiles` |
| Database columns | snake\_case | `created_at`, `external_ref` |
| Files (backend classes) | PascalCase | `OrderService.php`, `OrderService.ts` |
| Files (views/components) | kebab-case | `order-card.blade.php`, `OrderCard.vue` |
| Enum keys | TitleCase | `OrderStatus.Pending`, `PaymentMethod.Card` |
| Actions/Commands | VerbNoun | `CreateOrder`, `ProcessPayment` |
| Events | NounPastTense | `OrderPlaced`, `PaymentConfirmed` |
| Interfaces | Descriptive noun | `PaymentGateway`, `NotificationChannel` |

**PHP-specific**

- `declare(strict_types=1)` in all application code
- PHP 8+ constructor property promotion
- `config()` helper, never `env()` outside config files
- Use `Model::query()` over raw DB facade where possible
- Run Pint (or equivalent formatter) after every change

**TypeScript/JavaScript-specific**

- `strict: true` in `tsconfig.json` — no `any` without a comment explaining why
- `const` by default; `let` only when reassignment is needed
- Named exports preferred over default exports for utilities and composables
- No `console.log` in committed code — use a logger utility

**General**

- Use framework generators (`artisan make:*`, `ng generate`, etc.) instead of hand-creating framework files
- No abbreviations in names unless universally understood (`id`, `url`, `dto`)
- Write code as if another developer will maintain it tomorrow

## 5. Identifier Strategy

This is a non-negotiable. Raw auto-increment integer IDs reveal record counts, enable enumeration attacks (`/orders/1`, `/orders/2`...), and leak business information. They must never leave the database.

**Every table/entity must have two identifiers:**

| Identifier | Type | Purpose |
| --- | --- | --- |
| Internal primary key | Integer (auto-increment) | DB joins, foreign keys, indexing — never exposed |
| External public identifier | ULID or UUID | URLs, API responses, frontend, logs — always used externally |

**ULID preferred over UUID** for new projects:

- Sortable by creation time (unlike UUID v4)
- Shorter and URL-safe (26 chars vs 36)
- Collision-resistant
- Use UUID v4 when the client must generate the ID before the server (offline-first, client-owned IDs)

**Slug:** add only on entities with SEO-relevant public routes (blog posts, product pages, public profiles) — not on every table.

**Rules:**

- The integer primary key never appears in any URL, API response, Blade view, component, or JSON payload
- The external identifier is used everywhere outside the database
- Route model binding must resolve by the external identifier, not the integer key
- API responses that rename `ulid`/`uuid` to `id` for consumers is acceptable — the integer must still be absent
- Tests must assert that URLs use the external identifier, not the integer key

**Unique constraints on soft-deletable models:** Any column with a unique constraint on a soft-deletable record (slug, code, reference number, email) must be **mutated on soft-delete** to free the original value for reuse. Append a suffix using the row's own permanent integer ID — e.g. `{original}-deleted-{id}`. The integer ID guarantees uniqueness permanently, even across repeated create → delete → recreate → delete cycles. Never leave a unique column untouched on a soft-deleted row — it permanently blocks reuse of that value.

## 6. Architecture & Layers

Define your layers before writing a single line of code. Document them. Layers must never skip each other. AI will collapse layers unless you actively enforce them — always push back on fat controllers, fat components, and direct database access from entry points.

**The universal layered stack:**

```
Entry Point        (Controller / Route Handler / View)
  ↓
Input Validation   (Form Request / Zod Schema / DTO)
  ↓
Action             (one operation, one entry point — orchestrates the work)
  ↓
Service            (reusable business logic, called by multiple actions)
  ↓
Repository         (all data access — the only place that talks to the DB)
  ↓
Model / Entity     (data structure, relationships, casts, computed fields)
  ↓
Database
```

**Frontend equivalent:**

```
View / Page        (thin entry point — reads from stores, triggers actions)
  ↓
Component          (renders UI, emits events — no data fetching, no persistence)
  ↓
Hook / Composable  (reusable reactive logic — state + derived values)
  ↓
API Service        (all HTTP calls — the only place fetch/axios is called)
  ↓
Backend API
```

**Layer responsibilities:**

| Layer | Owns | Must never contain |
| --- | --- | --- |
| Controller / Route Handler | HTTP in/out, delegates | Business logic |
| Action | Single operation, transaction boundary | HTTP knowledge |
| Service | Reusable business rules, multi-step orchestration | HTTP, DB driver specifics |
| Repository | All queries for one entity | Business logic |
| Model / Entity | Structure, relationships, scopes, casts | Business logic |
| Policy | Authorization decisions only | Everything else |
| Event | Declares that something happened | Causes the thing |
| Job | Async / queued work | Synchronous response logic |
| Component / View | Render + user events | Data fetching, persistence |
| Hook / Composable | Reusable reactive logic | Direct API calls, UI rendering |
| API Service | HTTP calls, error normalization | UI logic, business rules |

**Actions vs Services:**

- **Action** — one business operation triggered from one entry point (e.g. `CreateOrderAction`). Does not get reused across unrelated entry points.
- **Service** — reusable logic called by multiple Actions and/or Controllers. Orchestrates complex workflows that span multiple operations.

**Key rules:**

- A controller that calls a repository directly is a layer violation
- A component that calls `fetch` directly is a layer violation
- A service that reads `$request` is a layer violation
- Business logic in a migration is a layer violation
- Document your layers in an `ARCHITECTURE.md` file — reference it in every AI prompt to prevent drift

## 7. Design Patterns

**Universal Principles**

- **SOLID** — see table below; tell AI to follow SOLID and it will structure code significantly better
- **DRY** — extract repeated logic into reusable units
- **KISS** — simplest solution that works is the right one
- **YAGNI** — don't build what you don't need yet
- **Separation of Concerns** — UI, business logic, and data access stay in separate layers

**SOLID:**

| Principle | Rule | Practical meaning |
| --- | --- | --- |
| Single Responsibility | One class/function does one thing | `UserService` handles users only — not emails, not payments |
| Open/Closed | Extend, don't edit existing code to add features | New payment method = new class, not editing the existing one |
| Liskov Substitution | Subtypes work wherever the parent type works | Any `PaymentGateway` implementation honours the same contract |
| Interface Segregation | Don't force code to depend on things it doesn't use | Split a large interface into smaller focused ones |
| Dependency Inversion | Depend on abstractions, not concrete implementations | Controllers depend on a repository interface, not a specific DB class |

**Creational Patterns**

- **Repository** — abstracts all data access behind an interface; swap data sources without touching business logic; makes testing trivial with mocks
- **Factory** — creates objects without exposing creation logic to the caller
- **Service** — business logic in dedicated service classes, not in controllers or components

**Structural Patterns**

- **Adapter** — wraps incompatible interfaces so they work together without modifying either
- **Decorator** — adds behaviour to an object without changing its class
- **Facade** — simple interface to a complex subsystem

**Behavioural Patterns**

- **Observer / Event** — one event triggers multiple reactions; side effects stay out of main flow
- **Strategy** — swap algorithms or behaviours at runtime without changing the caller
- **Command / Action** — encapsulates an operation as an object; one action class per user intent
- **Template Method** — defines the skeleton of an algorithm; subclasses fill in the steps

**Frontend-Specific**

- **Container / Presentational** — smart components fetch data; dumb components only render
- **Custom Hooks / Composables** — extract all reusable logic out of components
- **Context + Reducer / Store** — global state without re-fetching; keep server state and client state separate
- **Compound Components** — flexible, composable UI that shares state internally

**Backend-Specific**

- **MVC** — Models, Views (or frontend), Controllers stay strictly separate
- **Service Layer** — fat services, thin controllers
- **Form Request / Input Validation** — validation in its own class, not the controller
- **API Resource / Presenter** — all API responses shaped through a dedicated layer
- **Event + Listener** — side effects triggered by events, never inline in services or controllers
- **Queue / Job** — anything slow goes into a background job

## 8. Database Rules

**Schema Design**

- Plan the schema before writing code — it is the hardest thing to change later
- Use proper data types — do not store everything as strings or JSON
- Enforce relationships at the database level with foreign key constraints, not just application level
- Use soft deletes (`deleted_at`) on tables where history matters — never hard-delete records with dependencies
- Every table gets `created_at` and `updated_at` timestamps
- Never use native database ENUM column types — use a string column and enforce values via a PHP/TypeScript enum class
- Never store money as a float — see Section 12

**Migrations**

- Every migration must be reversible — write the `down()` / rollback method properly and test it
- Never edit a migration that has already been committed or run in any shared environment — create a new one
- One concern per migration — keep them small and focused
- Add indexes on all foreign keys and any column used in `WHERE`, `ORDER BY`, or `GROUP BY` clauses
- Add comments to migrations explaining non-obvious columns
- Migrations are additive by default — never drop a column without a documented decision

**Querying**

- Use an ORM / query builder over raw SQL wherever possible
- Avoid the raw DB/query facade unless the ORM genuinely cannot express the query
- Use relationships (not manual joins) wherever possible
- Eager load relationships to prevent N+1 queries
- Use model scopes / query scopes for repeated filters — never duplicate `where()` chains
- Paginate all list queries — never return unlimited result sets
- Select only the columns you need — don't fetch entire rows when only two fields are used
- Use transactions for any multi-step write — see Section 10

**Performance**

- Index all foreign keys and frequently queried/filtered columns — missing indexes are the number one cause of slow queries
- Cache expensive repeated read queries with explicit TTLs and cache invalidation on writes
- Use database-level constraints for critical business rules, not just application validation
- Never query inside a loop — restructure to query once and iterate over the result

**Soft Deletes & Reference Data**

- Use soft deletes on any table where records may be referenced by other records
- Never hard-delete reference data — use soft deletes or status flags
- Any unique constraint on a soft-deletable column must be mutated on delete — see Section 5
- Soft-deleted records must be excluded from all regular queries automatically

## 9. Routing Best Practices

**Backend API Routing**

- **Version from day one** — `/api/v1/` not `/api/`; changing later breaks every client
- **Resource routes** — consistent CRUD structure: GET (list/show), POST (create), PUT/PATCH (update), DELETE (destroy)
- **Group related routes** — by middleware, authentication, version, and domain
- **Name your routes** — reference by name, never hardcode URL strings
- **Thin route definitions** — routes declare paths and middleware only; all logic is in controllers and services
- **Correct HTTP status codes** — 200 OK, 201 Created, 204 No Content, 400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found, 409 Conflict, 412 Precondition Failed, 422 Unprocessable, 429 Too Many Requests, 500 Server Error
- **RESTful consistency** — uniform resource naming across the entire API
- **Never expose internal IDs in URLs** — use the external identifier (ULID/UUID)
- **PUT with client-generated ID for idempotency** — `PUT /resources/{id}` called twice = one resource; `POST /resources` called twice = two resources

**Frontend / SPA Routing**

- **Define all routes in one file** — a single route definition file, not scattered across the app
- **Lazy loading** — load page components on demand, not all at startup
- **Auth guard wrapper** — one guard wrapping all private routes, not per-page checks
- **Catch-all not-found route** — always handle unknown paths explicitly
- **Route constants** — never hardcode path strings (e.g. `'/dashboard'`) across components; define once and import
- **Separate public and private route trees** — makes auth logic and layout management simpler
- **URL params for identity only** — IDs and slugs in the URL; data goes in the request body or state, never query params for sensitive data

## 10. Data Integrity

**Transactions**

- Wrap every multi-step write operation in a database transaction — if any step fails, everything rolls back
- Transactions belong in the Action or Service layer, never in controllers
- Test rollback scenarios explicitly — never assume they work
- A transaction that spans more than one database is a distributed transaction — use a saga or compensating transaction pattern instead

**Concurrency on Limited Resources**

Any finite, contested resource — inventory stock, coupon usage limits, booking slots, waitlist spots — must be handled with row-level locking inside a transaction, never as a check-then-write:

```
// WRONG — two simultaneous requests both pass the check before either writes
if ($item->stock > 0) {
    $item->decrement('stock');
}

// RIGHT — lock the row, check, write, release — one atomic operation
DB::transaction(function () use ($item) {
    $item = Item::lockForUpdate()->find($item->id);
    if ($item->stock <= 0) throw new OutOfStockException();
    $item->decrement('stock');
});
```

A concurrency test proving two simultaneous requests against the last available unit result in exactly one success and one rejection is required before any such resource ships.

**Webhooks & Async Confirmation**

- Webhooks must be idempotent — check for already-processed events before acting (store and check the provider's event ID)
- Never rely on a webhook as the only confirmation path — providers can deliver late or drop delivery entirely
- Pair webhook handling with an **active polling fallback** — a scheduled job that checks status directly via the provider's verify endpoint for anything still pending beyond a short grace period
- Define an explicit rule for confirmation arriving **after** a related time-bound hold has expired — do not leave this as an undefined edge case. Typical resolution: attempt to honour it if the resource is still available; otherwise reverse/refund/cancel automatically and notify the user

**Historical & Transactional Snapshots**

Any record documenting something that already happened (order, invoice, receipt, sale record) must snapshot all referenced data at the moment it occurred:

- Snapshot: name, price, attributes, image, currency — everything needed to fully render the record
- Never re-read live data from the referenced model when displaying or regenerating a historical record
- This must hold even if the referenced record is later edited, archived, or deleted
- Editing a product's price today must never change what a past order's receipt shows

**Dates & Times**

- Store all timestamps in UTC — convert to local timezone only for display
- Use immutable date objects where available to avoid accidental mutation
- Store dates as ISO 8601 UTC strings in non-relational stores

## 11. Payment & Duplicate Prevention

A customer double-clicks Pay. The mobile app retries after a timeout. The queue redelivers the event. That is one payment arriving as three requests. These six layers keep it at one charge.

**The 6 layers:**

| Layer | Mechanism | What it stops |
| --- | --- | --- |
| 01 Idempotency key (edge) | Client sends one key per attempt; server stores and replays the first response | Cheap retries at the entry point |
| 02 PUT with client ID (verb) | `PUT /payments/{id}` twice = one payment; `POST /payments` twice = two | Retries become upserts |
| 03 Conditional update (version) | `If-Match` header or version column; stale retry gets `412 Precondition Failed` | Applying the same update twice |
| 04 Unique constraint (guarantee) | `UNIQUE (merchant_id, external_ref)` at DB level; catch conflict, return existing | **The only layer that holds when two retries race** |
| 05 Consumer dedup (queue) | Store message ID in the same transaction as the work | Queue at-least-once redelivery |
| 06 Provider key (card charge) | Send provider its own key built from your payment ID | Duplicate charge at provider level |

**The key insight:** Layers 1, 2, 3, 5, and 6 make duplicates cheap. Layer 4 is the only one that holds when two retries race simultaneously. Layer 4 is not optional.

**Implementation rules:**

- Every payment endpoint must implement Layer 4 as a minimum — database unique constraint on the natural business key
- Layer 1 (idempotency key) is required on any endpoint that triggers a charge, creates a record, or sends a message
- Layer 5 (consumer dedup) is required whenever a queued job processes payments or sends messages — store the message/event ID in the same transaction as the work, never in a separate step
- Layer 6 (provider key) is required when calling any payment provider — build the key from your internal payment ID so provider retries are no-ops
- Catch unique constraint violations and return the existing record — do not treat them as errors to surface to the user
- Never auto-merge or auto-link two accounts or payments based on a coincidental identifier match — only link when the identifier was independently verified

## 12. Money Handling

Floating point arithmetic is imprecise. `0.1 + 0.2 ≠ 0.30` in most languages. Never use it for money.

**The rules — no exceptions:**

- Store all money as an **integer in the smallest currency unit** — cents, kobo, pesewas, fils, pence
- `$1.54` → stored as `154`; `₵10.00` → stored as `1000`
- Never use `float`, `double`, or `decimal` columns for money
- Never convert to float mid-calculation — all arithmetic (totals, discounts, tax, refunds) stays on integers
- Currency code must always travel with the amount — never store bare numbers without a currency
- Decide rounding rules upfront and enforce them in one place only

**Display boundary — format only once:**

- Raw minor-unit integers must never appear directly in a UI or API response without formatting
- Add a formatted accessor on the model/entity (e.g. `getAmountFormattedAttribute()`)
- Create a single formatting utility and use it everywhere — `formatMoney(amount, currency)`
- Never format inline in a template or component

**API responses — expose both:**

```json
{
  "amount": 15400,
  "amount_formatted": "$154.00"
}
```

- Raw integer for client-side math and precision
- Pre-formatted string for direct rendering
- Never leave the client to do the division itself

**Payment providers:**

- Most providers (Stripe, Paystack, etc.) already speak minor units — pass your integer directly
- No conversion needed at the provider boundary

**Ledger vs transactional records:**

- A full double-entry accounting ledger is only justified when the project involves multi-party payouts, vendor splits, or formal accounting reconciliation
- Absent those requirements, standard transactional records (payments, refunds, audit-log tables with clear amounts and statuses) are sufficient and fully reconcilable
- Don't build a ledger by default — treat it as a deliberate addition once an actual multi-party need arises

## 13. Enums

Use language-native backed enums for every field that has a fixed set of values. No magic strings anywhere in the codebase.

**Rules:**

- Never use database-native ENUM column types (inflexible, hard to migrate) — use a string column and enforce values via a code enum
- Create a dedicated enum class for every fixed option set — do not scatter string literals across the codebase
- Cast model/entity fields to enums at the data layer — not in controllers or views
- Mirror backend enums in the frontend — single source of truth, no magic strings on either side
- Enum classes live in a dedicated directory (`app/Enums/`, `src/types/enums/`, etc.)

**Naming:**

- Class: `{Domain}{Concept}` — e.g. `OrderStatus`, `PaymentMethod`, `UserRole`
- Keys: TitleCase — e.g. `OrderStatus.Pending`, `PaymentMethod.Card`
- Values: lowercase snake\_case strings — e.g. `'pending'`, `'card'`

**Add helper methods on the enum itself:**

- `label()` — human-readable display string
- `color()` / `badge()` — UI colour class or indicator
- `description()` — longer explanation if needed
- Keep conditional logic about an enum value inside the enum, not scattered across services and views

**Enums make AI-generated code dramatically safer** — no typos, no undocumented values, no silent mismatches between frontend and backend.

**Common enums every project needs:**

- `{Model}Status` — lifecycle states (e.g. `OrderStatus`, `InvoiceStatus`)
- `UserRole` — permission levels
- `{Model}Type` — category distinctions
- `NotificationType` — what triggered a notification
- Any payment method, channel, or provider type

## 14. DTOs & Value Objects

**DTOs — Data Transfer Objects**

Typed objects for passing structured data between layers. No more raw untyped arrays or property bags.

- One DTO per operation: `CreateOrderDTO`, `UpdateUserDTO`, `ProcessPaymentDTO`
- Carry only the data needed for that specific operation — not entire model objects
- Validate and construct DTOs at the entry point (controller, handler, form request); layers below trust them
- Make DTOs immutable where the language supports it — no mutation after construction
- DTOs prevent coupling: layers depend on a typed contract, not on how another layer structures its data
- AI generates DTOs cleanly when you name the pattern upfront and provide the operation name

**Value Objects**

Immutable objects representing a domain concept with its own validation and behaviour.

- `Money` — amount + currency; encapsulates formatting and arithmetic rules
- `Email` — validated email address; never a bare string
- `PhoneNumber` — validated with format; strips whitespace before validation
- `Address` — structured postal address
- `DateRange` — start + end with overlap/contains methods
- `ExternalRef` — payment reference or external ID with format enforcement

Rules:

- Two value objects with the same value are equal, regardless of identity
- Validation is encapsulated in the value object's constructor — callers cannot create an invalid one
- Replace primitive obsession: use `Email` not `string $email`; use `Money` not `int $amount`
- Value objects have no identity (no ID column) — they describe a thing, not identify a record

**When to use which:**

- DTO → moving data between layers in a request/operation lifecycle
- Value Object → representing a typed, validated domain concept used across the entire codebase

## 15. Third-Party Integrations

Every external provider (payment gateway, SMS, email, KYC verification, shipping, analytics) must be integrated behind an interface. Never call a third-party SDK directly from a Service or Action.

**The pattern:**

```
Interface (contract)    PaymentGateway, SmsGateway, KycProvider
    ↓
Driver (implementation) PaystackGateway, TwilioGateway, SmileIdentityProvider
    ↓
Manager (resolver)      Resolves the active driver from config
    ↓
Config (decision)       config/payment.php → driver: 'paystack'
```

**Rules:**

- Swapping a provider must be a **config change + one new driver class** — it must never require modifying any Action, Service, or business logic that uses the gateway
- Adding a new provider = implement the interface, add to config — nothing else changes
- Multiple drivers may be active simultaneously (e.g. one provider for mobile money, another for cards) — the calling code selects by channel/config, not by hardcoding a provider name
- The interface defines the contract; the driver implements it; the manager resolves which driver to use
- Never leak provider-specific error codes or response shapes past the driver — normalize them at the driver boundary
- Every outbound call to a third-party must be logged — see Section 17 on logging
- Every third-party integration that confirms asynchronously (payment, KYC, shipping) must have both a webhook handler and a polling fallback — see Section 10

**Interface design:**

- Methods should reflect business intent, not provider API shape — `charge(Money $amount, PaymentMethod $method): ChargeResult` not `createPaymentIntent(array $params): array`
- Return typed result objects, not raw provider responses
- Throw domain exceptions on failure — not provider-specific exceptions that leak implementation details

## 16. Security

**Core mindset: assume every user is malicious and every input is hostile until proven otherwise.**

**Authentication & Authorization**

- Use token-based auth (JWT, session tokens) with short expiry and refresh token rotation
- Always enforce authorization server-side — never trust the frontend to hide or restrict data
- Use Policies/Guards for authorization — never manual role string comparisons inline in controllers
- Implement role-based access control (RBAC) consistently across the entire API
- Use HTTPS everywhere — no exceptions
- Rate limit all endpoints, especially auth endpoints (login, register, password reset)
- Customer-facing auth may use phone + OTP as the primary method — admin/staff auth must always be email + password
- **Never auto-merge or auto-link two accounts** based on an unverified or coincidental identifier match — only link when the identifier was independently verified (OAuth verified email, OTP-confirmed phone, or the user is already authenticated and explicitly opts in)

**Input & Data**

- Validate all input server-side using a dedicated validation layer (Form Requests, Zod schemas, validators)
- Client-side validation is UX only — never a security control
- Use parameterized queries or an ORM — never concatenate user input into raw SQL
- Protect against mass assignment — explicitly whitelist fillable fields
- Sanitize all user input that will be stored and re-displayed (XSS prevention)
- Validate file uploads strictly: type, extension, MIME type, size
- Never expose raw model data in API responses — use a response transformer layer

**Rate Limiting**

- Rate limit all public-facing endpoints
- **Cost-triggering actions get their own separate rate limit** — any endpoint that triggers a billed third-party call (SMS OTP, paid API lookups, email sends) must be rate-limited specifically to control cost and abuse, separate from the security rate limit on the same endpoint
- Example: max 1 SMS OTP per phone per 60 seconds, max 5 per hour

**API & Server Security**

- Return generic error messages to clients — log detailed errors server-side only
- Never expose stack traces, file paths, or database errors in API responses
- Use CORS properly — only allow trusted origins
- Store all secrets in environment variables — never hardcoded in source code
- Rotate API keys and secrets on a schedule

**Frontend Security**

- Never store auth tokens in `localStorage` — use httpOnly cookies
- Never expose API keys or secrets in frontend code
- Sanitize all content rendered from user input to prevent XSS
- Escape all template output by default — only render raw HTML with explicit, reviewed justification

**File Storage**

- Private + signed URLs for sensitive or user-specific files (uploads, KYC documents, invoices, private media)
- Public storage only for assets genuinely meant to be publicly accessible (blog images, branding)
- Never cache or expose signed URLs publicly

**OWASP Top 10 — always ask AI to review against these before shipping:** Injection · Broken Authentication · Sensitive Data Exposure · XXE · Broken Access Control · Security Misconfiguration · XSS · Insecure Deserialization · Vulnerable Components · Insufficient Logging

## 17. Error Handling & Logging

**Error Handling Rules**

- Never expose stack traces, internal file paths, or database errors in API or UI responses
- Return consistent, structured error responses: `{ status, message, errors? }`
- Log the full technical detail server-side; return generic messages to clients
- Every external service call must have error handling and a defined fallback
- Use specific HTTP status codes — never return 200 for an error
- Never swallow exceptions silently — at minimum log them
- Create named custom exception classes for domain errors (`InsufficientBalanceException`, `DuplicateBookingException`) — not generic exceptions
- Custom exceptions live in a dedicated directory (`app/Exceptions/`, `src/errors/`)
- Catch specific exception types where the caller needs to react differently — never catch-all to swallow errors

**Standardised error response shape:**

```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required"]
  }
}
```

**Log Levels — use them correctly:**

| Level | When to use |
| --- | --- |
| DEBUG | Local dev only — verbose diagnostic detail; never relied on in production |
| INFO | Audit-worthy business events — record created, payment received, role changed |
| WARNING | Recoverable situations — retry succeeded, fallback used, approaching rate limit |
| ERROR | Failures requiring human attention — payment failures, jobs failed after final retry |
| CRITICAL | System is broken — immediate attention required |

**Sensitive data — strict rules:**

- API keys, secrets, tokens, passwords, card numbers, OTPs, KYC document contents, and any PII must **never** appear in log files
- Sensitive data that needs to be recorded for audit or debugging goes to a **dedicated database table** (e.g. `api_call_logs`, `audit_logs`) with field-level encryption — never to a file-based log
- Log files are for non-sensitive operational logging only

**Third-party API call logging (required):**

- Every outbound request to a third-party API and its response must be logged — request payload, response payload, status code, duration
- Log to a dedicated DB table, never to `laravel.log` or equivalent file log
- If the payload contains sensitive fields, store them encrypted at the column level
- Full traceability is required for reconciliation — but exposure must be controlled

**Money & critical data auditing (required):**

- Money-related events (payments, balance changes, refunds, status transitions on financial records) must be persisted to a dedicated DB table
- File logs are operational only — never the source of truth for anything that may need querying, reporting, or reconciliation

## 18. Queues & Background Jobs

Use queues for: emails, SMS, notifications, external API calls, file processing, report generation, and anything that would block the request/response cycle.

**Every queued job must declare:**

- `tries` / `maxAttempts` — maximum retry attempts
- `timeout` — maximum execution time per attempt
- `backoff` — delay strategy between retries (fixed or array for exponential)
- `failed()` handler — explicit handling for permanent failure (notify admin, mark record as failed) — never let a failed job disappear silently

**Queue segmentation — required:**

| Queue | Purpose |
| --- | --- |
| `critical` | OTPs, transactional auth emails, time-sensitive confirmations |
| `notifications` | Order updates, status changes, marketing emails |
| `external-api` | Outbound calls to third-party services |
| `processing` | File processing, report generation, bulk operations |
| `default` | Everything else |

- Time-sensitive jobs must not share a queue with slow/unreliable external API jobs — one slow job must not block an OTP from delivering
- Configure dedicated workers per named queue in deployment
- Never dump everything into the `default` queue on a production system

**Consumer dedup — required for payment/message jobs:**

- Store the message/event ID in the **same transaction** as the work — not in a separate step
- Storing in a separate step means a crash between the two will cause the work to run again on the next delivery
- Check for the message ID at the start of the job — if already processed, return early (no-op)

**Events & Listeners:**

- Naming: `{Noun}{PastTenseVerb}` — e.g. `UserRegistered`, `OrderPlaced`, `PaymentConfirmed`
- Fire events from Models, Actions, or Services — never directly from Controllers or views
- One listener = one side effect — use multiple listeners on the same event rather than one listener doing several unrelated things
- Queue listeners by default for any side effect not required to complete before the response returns

## 19. Reusable Components

**The rule: check before you build. Never duplicate a component that already exists.**

Before building any new UI component, utility, hook, or service:

1. Check the existing component library (`components/`, `composables/`, `hooks/`, `services/`)
2. Check if an existing component can be extended with a prop or variant
3. Only create a new one when nothing existing fits the need

**Reuse principles:**

- Shared UI pieces must be extracted components — never duplicated HTML
- If the same markup appears in two places, extract it
- If the same 3+ lines of logic appear in two places, extract them
- Variants come from props (`variant="danger"`), never from copy-pasted components
- Don't scaffold components speculatively — build them when they are genuinely needed

**Universal starter set — every project should have these:**

| Component | Purpose |
| --- | --- |
| `Alert` / `Toast` | Success, error, warning, info messages |
| `Button` | Primary, secondary, danger variants with loading state |
| `Input` / `Field` | Labelled input with error state |
| `Card` | Rounded container with optional header |
| `Modal` / `Dialog` | Accessible overlay |
| `PageHeader` | Page title + breadcrumb |
| `StatusBadge` | Coloured label for any enum status |
| `EmptyState` | Illustration + message for empty lists |
| `ConfirmDialog` | "Are you sure?" for destructive actions |
| `Pagination` | Page navigation for lists |
| `MoneyDisplay` | **The only place money is formatted for display** |

**Separation of design from logic (mandatory):**

- Templates / views (markup and styles) must never contain business logic
- A template reads values already computed by its component class, hook, or composable
- Deriving a value in the template (building a URL, formatting a number, computing text) belongs in the component class — the template says "display `formattedBalance`", never computes it
- Non-trivial interactive behaviour (timers, clipboard, debouncing, fetch calls) lives in a named, reusable utility — never inlined in a template
- This keeps the design layer fully swappable without risking that a redesign silently removes a bug fix

## 20. Testing

**Non-negotiable. Every behavioural change must have tests that pass before work is marked done.**

**Testing layers:**

| Layer | Tests | Tools |
| --- | --- | --- |
| Unit | Individual functions, classes, pure logic | Jest, Vitest, PHPUnit, Pest |
| Integration | Multiple layers working together | Same tools, real DB |
| Feature / API | Full HTTP request-to-response cycle | Supertest, PHPUnit, Pest |
| Component | UI component behaviour and rendering | Testing Library, Vue Test Utils |
| End-to-End | Real browser, full user journeys | Playwright, Cypress |

**Every feature must have tests covering:**

1. Happy path — the feature works correctly with valid input
2. Auth guard — unauthenticated users are redirected; unauthorized users get 403
3. Validation — invalid input returns the correct validation errors
4. Edge cases — at least one scenario where something could go wrong

**Always fake external dependencies in tests:**

- Mail / Email
- SMS / Notifications
- Queues / Jobs
- Events
- External HTTP / Third-party APIs
- File storage

**Domain tests required on every project with these features:**

- Concurrency: two simultaneous requests against the last available unit = one success, one rejection
- Idempotency: pushing the same record/event twice produces one result
- User isolation: User B cannot read or modify User A's data
- Money arithmetic: calculations match expected values at integer level
- Auth boundary: unauthenticated user redirected; wrong-role user gets 403

**Non-negotiable testing rules:**

- Write tests before or alongside the feature — never after as an afterthought
- Run tests after writing them; if they fail, fix the code — not the tests
- Never delete, skip, comment out, or loosen assertions in a test without explicit approval
- Do not move on to the next task until the full test suite is green
- Tests must assert that URLs use the external identifier — never the integer primary key

**AI and testing:**

- After generating any logic with AI, immediately ask: *"Write tests for this"*
- After generating an endpoint: *"Write feature tests covering happy path, auth, validation, and edge cases"*
- After generating a service method: *"Write unit tests for this, including what happens when the input is invalid"*

## 21. Performance

**Profile before optimizing — never optimize based on assumption. Measure first, then fix.**

**N+1 Queries**

- Eager load relationships — never lazy-load in a loop
- Enforce this at the framework level where possible (e.g. `Model::preventLazyLoading()`)
- Set a query budget per request — no single request should execute more than \~50 queries
- Use a local debugger tool (Telescope, Debugbar, devtools) to monitor per-request query counts during development
- Investigate and fix any request exceeding the budget before merging

**Caching**

- Cache expensive, repeated read queries with explicit TTLs
- Always invalidate the cache on the relevant write — never leave stale cache silently
- Do not cache reflexively — profile first, then cache what actually needs it
- Use an in-memory store (Redis, Memcached, KV) for anything that needs fast repeated reads

**Async & Non-Blocking**

- Push all long-running or slow operations to queued jobs — never block the request/response cycle
- External API calls, report generation, file processing, bulk operations → always queued
- The UI/frontend reads from cache or local state; background processes update it

**Frontend Performance**

- Lazy load page/route components — not all at startup
- Set bundle size budgets and fail the build if exceeded
- Target Lighthouse scores: Performance ≥ 80, Accessibility ≥ 90, PWA ≥ 90 (if applicable)
- Avoid rendering blocking operations in the critical path

**Monitoring**

- Use a local debugging tool (Telescope, Debugbar) in development and staging for query, request, and job monitoring
- Use a live performance tool (Pulse, Datadog, New Relic) in production
- Set up alerts for slow queries, high error rates, and queue backlogs
- Monitor third-party API response times — degrade gracefully when a provider is slow

## 22. AI-Assisted Development

**The fundamental rule: AI writes the first draft. You own the code.**

Treat AI like a fast junior developer — useful, productive, but needs supervision. If you can't explain what the code does, you can't debug it.

**Prompting principles:**

- Give AI full context first — share existing code, stack versions, folder structure, and your ARCHITECTURE.md before asking it to build anything
- Context first, task second:
  - Bad: *"Build a login form"*
  - Good: *"Using our existing AuthService and this User type, build a login form that posts to POST /api/v1/auth/login and handles validation errors"*
- One feature at a time — never ask AI to build multiple unrelated things in one prompt
- Constrain the output — tell AI what NOT to do: *"Don't add new dependencies, don't change the existing auth flow, don't put logic in the controller"*
- Ask for edge cases explicitly: *"What could go wrong with this?"* and *"Handle the case where the response is null or the network fails"*

**Preventing bugs:**

- Always review AI output before running it — read it, understand it, question it
- Ask AI to explain any part you don't understand; if you can't explain it, you can't maintain it
- Ask AI to write tests immediately after generating logic — not later
- Never let AI rewrite large chunks of working code; it will break things you didn't ask it to touch
- Once something works well, mark it: *"This is finalized — do not modify it"*

**Enforcing architecture:**

- Reference your ARCHITECTURE.md in every AI session: *"Follow the architecture defined in ARCHITECTURE.md. Controllers must not contain business logic. All API calls go through the service layer."*
- AI collapses layers when you don't enforce them — fat controllers and fat components are the two most common AI anti-patterns
- Push back every time AI puts business logic in a controller or API calls in a component
- Add SOLID to every session prompt: *"Follow SOLID principles. Keep classes single-purpose. Depend on abstractions."*

**Workflow:**

- Plan → Scaffold → Build small → Test → Commit → Repeat
- Each commit is a checkpoint — if AI breaks something, roll back one step, not the whole project
- Get a working skeleton first, then add features one by one
- Never ask for everything at once — small, focused prompts produce better, safer code

## 23. Comments & Documentation

**Voice and tone:**

- Write comments in plain, conversational human language — not stiff or robotic
- Write as the developer who wrote the code, explaining their own decisions
- First person is natural: *"I use eager loading here to avoid N+1 queries"*
- Simple English — if a comment needs a dictionary, rewrite it
- Be concise — comments that take longer to read than the code itself are noise

**What to comment:**

- Explain **why**, not **what** — the code already shows what; comments cover intent, tradeoffs, and non-obvious reasoning
- Inline comments only where the logic is genuinely complex or non-obvious
- Never write comments that just repeat the code:
  - Bad: `$order->save(); // save the order`
  - Good: `$order->touch(); // I force a timestamp update so the dashboard sorts this order to the top`

**Required docblocks (all application code):**

- File/module-level docblock — what this file does and why it exists
- Class/component-level docblock — the responsibility of this class
- Method/function docblock — what this method does and any non-obvious behaviour; kept to one clear sentence where possible
- Use PHPDoc / JSDoc format consistently across the project

**Good file-level docblock:**

```
/**
 * Handles the public car catalogue — filtering, searching, and pagination.
 */
```

**Good method docblock:**

```
/**
 * Advances an order to the next stage in the shipment pipeline.
 * I enforce sequential stage transitions here so stages can never be skipped.
 */
```

**ARCHITECTURE.md — required on every project:**

- Document the layer structure before writing code
- Name each layer, describe its responsibility, and state what it must never do
- Reference it in every AI prompt to prevent drift
- Update it whenever the architecture changes — an outdated architecture doc is worse than none

## 24. API Response Standards

**Every API response must go through a response layer** (API Resource, Serializer, Presenter) — never return raw model or database objects directly.

**Response shape standards:**

- Success (single resource): return the resource directly
- Success (collection): `{ data: [...], meta: { total, page, per_page, last_page } }`
- Created: HTTP 201 with the new resource
- No content: HTTP 204 with empty body
- Validation error (422): `{ message: "...", errors: { field: ["..."] } }`
- General error: `{ message: "..." }`
- Never return 200 for an error response

**Field rules:**

- Never expose the internal integer primary key — use the external identifier
- Money fields always expose both the raw integer and a formatted string:

  ```json
  { "amount": 15400, "amount_formatted": "$154.00" }
  ```
- Dates in ISO 8601 UTC; add a `_formatted` sibling for display if needed
- Enum fields return the string value; add a `_label` sibling for display if needed
- Never include internal admin fields (notes, flags, internal IDs) in customer-facing responses

**Versioning:**

- URI versioning as the default: `/api/v1/`, `/api/v2/`
- Version from day one — not retrofitted after the first breaking change
- Bump the version on breaking changes; keep old versions live until deprecated on a defined timeline
- Add a `Sunset` header to deprecated endpoints so consumers know when to migrate

**Pagination:**

- Paginate all list endpoints — never return unlimited result sets
- Use the framework's built-in paginator output — don't invent a custom pagination shape
- Include `total`, `page`, `per_page`, and navigation links in every paginated response

## 25. Git & Commit Conventions

**Commit message format — Conventional Commits:** `type(scope): short description`

| Type | When to use |
| --- | --- |
| `feat` | A new feature |
| `fix` | A bug fix |
| `refactor` | Code change that neither fixes a bug nor adds a feature |
| `test` | Adding or updating tests |
| `docs` | Documentation changes |
| `chore` | Build, config, tooling, dependency updates |
| `perf` | Performance improvements |
| `style` | Formatting, whitespace — no logic change |

**Examples:**

- `feat(orders): add cancellation flow with refund trigger`
- `fix(auth): resolve session timeout on mobile login`
- `refactor(payment): extract charge logic into CreatePaymentAction`
- `test(orders): add concurrency test for last-stock scenario`

**Rules:**

- Plain, human language — no vague messages like `fix stuff`, `updates`, or `wip`
- Scope should reference the relevant domain or module
- One commit per completed, verified unit of work — don't batch unrelated changes
- Never commit directly to `main` or the primary branch — all work goes through feature branches
- Completed, verified work must not sit uncommitted — commit it so it can be safely reverted if needed
- CHANGELOG.md entry always comes before the commit that covers it

**Branch naming:** `type/short-description`

- `feat/order-cancellation`
- `fix/auth-session-timeout`
- `chore/upgrade-dependencies`

**Pull requests:**

- Description states *what* changed and *why* — not just a copy of commit messages
- Pre-completion checklist (Section 26) must be satisfied before opening
- Full test suite must be passing — no PR merged with red tests
- History kept clean — no unresolved merge conflicts in the diff

## 26. Pre-Completion Checklist

Run through every item before marking any task, feature, or PR as done. If the answer to any item is **no**, fix it before moving on.

**Core Principles**

- [ ] KISS — simplest viable solution that correctly solves the problem?
- [ ] DRY — no duplicated logic anywhere?
- [ ] YAGNI — nothing built beyond what the current task requires?
- [ ] Single responsibility — every class, method, and component does exactly one thing?
- [ ] Layers respected — no business logic in controllers/views; no HTTP knowledge in services?

**Code Quality**

- [ ] Strict typing enabled (`strict_types=1`, `strict: true`, type hints)?
- [ ] Explicit return types on all methods and exported functions?
- [ ] No magic strings — enums or constants used for all fixed values?
- [ ] File, class, and method docblocks present — human-toned, explaining why not what?
- [ ] No noise comments that just repeat the code?
- [ ] Static analysis passing (PHPStan, TypeScript, ESLint)?
- [ ] Formatter run on all changed files (Pint, Prettier)?
- [ ] No unused variables, imports, or dead code?

**Identifiers & Data Integrity**

- [ ] No raw integer IDs in any URL, API response, or frontend view — external identifier used throughout?
- [ ] Any unique constraint on a soft-deletable model mutated on delete to free the value for reuse?
- [ ] Money stored as integers in smallest currency unit — no float, no inline division in templates?
- [ ] API money responses expose both raw integer and formatted string?
- [ ] Multi-step writes wrapped in DB transactions?
- [ ] Any limited-quantity resource validated and committed under row-level locking?
- [ ] Any webhook-dependent flow paired with a polling fallback and a rule for late confirmation?
- [ ] Any historical/transactional record fully snapshotted — not re-reading live data?
- [ ] Dates stored in UTC; immutable date objects used?

**Third-Party & Integrations**

- [ ] Every third-party provider integrated behind an interface — not called directly from services?
- [ ] Swapping the provider requires only a config change and one new driver class?
- [ ] Every outbound third-party call logged to a DB table with request + response?
- [ ] Payment endpoints implement at minimum Layer 4 (DB unique constraint) against duplicate payments?
- [ ] Payment jobs store message ID in the same transaction as the work?

**Security**

- [ ] All input validated via a dedicated validation layer (Form Requests, Zod, etc.)?
- [ ] Authorization enforced server-side on every endpoint via Policy/Guard?
- [ ] No sensitive data in log files — audit data in dedicated DB tables?
- [ ] No secrets or credentials in source code or committed .env files?
- [ ] Auth tokens in httpOnly cookies, not localStorage?
- [ ] Rate limiting on all public endpoints?
- [ ] Cost-triggering endpoints (SMS, paid APIs) rate-limited separately for cost control?
- [ ] No auto-merge or auto-link of accounts on unverified identifier match?
- [ ] Sensitive files private + signed URLs; only genuinely public assets left public?
- [ ] OWASP Top 10 considered for any new endpoint?

**Queues & Jobs**

- [ ] All slow/async work in queued jobs — nothing blocking the request/response cycle?
- [ ] Jobs routed to the correct named queue, not dumped in `default`?
- [ ] Jobs declare tries, timeout, backoff, and failed() handler?

**Reusable Components**

- [ ] Existing components checked and reused before creating new ones?
- [ ] No duplicated HTML or logic — extracted into a shared component?
- [ ] No business logic or API calls in templates/views?

**Testing**

- [ ] Tests written for happy path, auth, validation, and edge cases?
- [ ] No test deleted, skipped, or weakened without explicit approval?
- [ ] Full test suite green?
- [ ] Tests assert external identifier in URLs — not integer primary key?

**Delivery**

- [ ] CHANGELOG.md updated?
- [ ] Git commit created after the CHANGELOG entry?
- [ ] Commit message follows Conventional Commits format?
- [ ] All completed, verified work is committed?

**Observability**

- [ ] Correlation/request ID generated and propagated through every layer?
- [ ] Structured JSON logging in production?
- [ ] Error monitoring service configured (Sentry, Flare, etc.)?
- [ ] Alerts set up for error rate, latency, queue depth, and payment failures?
- [ ] Health check endpoint returning correct status?

**Deployment**

- [ ] Environment variables validated at boot — app fails loudly on missing required vars?
- [ ] `.env.example` updated with any new variables?
- [ ] Migration backward-compatible with current code during deploy window?
- [ ] Rollback path defined and tested in staging?
- [ ] CI pipeline running lint, types, and full test suite on every PR?

**Dependencies**

- [ ] `composer audit` / `npm audit` passing with no high-severity vulnerabilities?
- [ ] Lockfile committed?
- [ ] No new dependency added that could be replaced by 5 lines of code?

**Timezones & Localisation**

- [ ] All timestamps stored in UTC?
- [ ] Timezone conversion only at the display layer?
- [ ] Cron expressions in UTC or timezone-aware scheduler used?

**Accessibility**

- [ ] Semantic HTML used — correct elements for their purpose?
- [ ] Every interactive element keyboard-accessible?
- [ ] Focus visible at all times?
- [ ] Every image has an `alt` attribute?
- [ ] Every form input has an associated label?
- [ ] Lighthouse accessibility score ≥ 90?

**Offline-First (when applicable)**

- [ ] Write to local storage succeeds before any network call?
- [ ] Works fully offline?
- [ ] Syncs correctly on reconnect?
- [ ] Sync is idempotent — same record pushed twice = one result?
- [ ] `StorageManager.persist()` called on first load?

**API Deprecation (when applicable)**

- [ ] Breaking changes get a new API version — never mutate the existing version?
- [ ] Deprecated endpoints have `Deprecation` and `Sunset` headers?
- [ ] Migration guide written for breaking changes?
- [ ] Consumers notified with sufficient notice period?

**Multi-Tenancy (when applicable)**

- [ ] Every tenant-scoped model has a global query scope filtering by `tenant_id`?
- [ ] `tenant_id` is resolved from the session — never from request body input?
- [ ] Tenant A cannot read, update, or delete Tenant B's records?
- [ ] Raw queries and background jobs include explicit `tenant_id` filtering?
- [ ] Cache keys and file storage paths are tenant-namespaced?
- [ ] `withoutGlobalScopes()` used nowhere without explicit security review?
- [ ] Cross-tenant isolation tests written and passing?

**API Documentation (when applicable)**

- [ ] OpenAPI spec exists and is committed to the repository?
- [ ] Spec covers all endpoints, request shapes, response shapes, and error responses?
- [ ] CI step validates the spec against the implementation?
- [ ] Breaking spec changes result in a new API version?
- [ ] Interactive docs served at `/api/docs` or `/docs`?
- [ ] Money fields documented as minor-unit integers with `_formatted` sibling?
- [ ] Contract tests written for consumer-facing endpoints?

**Data Privacy & Compliance**

- [ ] Every PII field has a documented purpose — no speculative data collection?
- [ ] Sensitive PII fields (ID numbers, financial data) encrypted at column level?
- [ ] KYC documents and sensitive files on private storage with signed URLs only?
- [ ] PII never appears in log files or analytics systems?
- [ ] Account deletion anonymizes PII rather than hard-deleting records?
- [ ] Financial and transaction records kept but user PII within them anonymized on deletion?
- [ ] Consent recorded with timestamp, purpose, policy version, and withdrawal support?
- [ ] Data export ("download my data") feature built or planned?
- [ ] Data retention periods defined and automated purge/anonymization job in place?
- [ ] Breach notification procedure documented before go-live?

**Incident Response**

- [ ] Severity levels defined (P1–P4) before launch?
- [ ] Runbooks written for every anticipated P1 and P2 failure mode?
- [ ] Runbooks committed to the repository — not in anyone's personal notes?
- [ ] Feature flags on all risky features for instant rollback?
- [ ] Alert messages link directly to the relevant runbook?
- [ ] Circuit breakers on all external provider integrations?
- [ ] Database backup procedure tested — restore verified, not just assumed?
- [ ] Post-mortem process agreed by the team before the first incident?

---

## 27. Observability

Logging tells you what happened. Observability tells you *why*. A system you cannot observe is a system you cannot debug in production.

**Structured Logging**

- Log in structured JSON format in production — not plain text strings
- Every log entry must carry: `timestamp` (UTC ISO 8601), `level`, `message`, `service`, `environment`
- Add context fields to every log: `user_id`, `request_id`, `action`, `duration_ms` where relevant
- Never build log messages by string concatenation — use structured fields

**Correlation IDs**

- Generate a unique `request_id` (UUID/ULID) at the entry point of every HTTP request
- Propagate it through every layer — controller, service, job, outbound API call
- Include it in every log entry for that request
- Return it in the response header (`X-Request-ID`) so clients can reference it in support tickets
- Pass it as a header to downstream services so distributed traces can be stitched together

**The Three Pillars**

| Pillar | What it answers | Tools |
| --- | --- | --- |
| Logs | What happened, in what order | Sentry, Flare, Datadog Logs, CloudWatch |
| Metrics | How the system is performing over time | Pulse, Datadog Metrics, Prometheus |
| Traces | Where time was spent across services | Datadog APM, Sentry Tracing, OpenTelemetry |

**Alerting — required before going to production:**

- Error rate threshold — alert when error rate exceeds baseline by X%
- Response time — alert when p95 latency exceeds acceptable threshold
- Queue depth — alert when a queue backlog exceeds N jobs
- Failed jobs — alert when jobs enter the failed queue
- Payment failures — alert immediately on any payment processing error
- Disk / memory — alert before resources are exhausted, not after

**Health checks:**

- Expose a `/health` endpoint that returns 200 when the app is operational
- Check: database connectivity, cache connectivity, queue connectivity, disk space
- Separate liveness check (is the process running?) from readiness check (is it ready to serve traffic?)
- Never expose sensitive system information in health check responses

**Error monitoring:**

- Use a dedicated error monitoring service (Sentry, Flare, Bugsnag) in production — not just log files
- Capture user context with every error (user ID, role — never PII like email or name)
- Group and deduplicate errors — don't be alerted on the same error 1000 times
- Set up release tracking so you can see which deploy introduced a new error
- Review and triage new errors within 24 hours of a deploy

## 28. Deployment & Environment Management

**Environment Parity**

- Keep development, staging, and production as identical as possible — the more they differ, the more production-only bugs you get
- Never test in production — staging must be a faithful replica of production (same DB engine, same queue driver, same cache driver, same file storage)
- Every developer runs the same toolchain — use a lockfile (`composer.lock`, `package-lock.json`) and commit it
- Infrastructure differences between environments must be in config, not in code

**Environment Variables**

- All environment-specific values live in environment variables — never hardcoded
- Validate required environment variables at application boot — fail loudly and immediately if a required variable is missing, rather than failing silently at runtime
- Maintain a `.env.example` file listing every required variable with a safe placeholder value — keep it up to date as variables are added or removed
- Never commit `.env` files to version control — only `.env.example`
- Separate secrets (API keys, DB passwords) from configuration (app name, timezone) — use a secret manager (AWS Secrets Manager, Doppler, Laravel Cloud) for credentials in production

**Zero-Downtime Deployments**

- Run database migrations before deploying new code, not after — new code must be compatible with the current schema during the migration window
- Never add a `NOT NULL` column without a default in a single migration — split it: add nullable, backfill, add constraint
- Use blue/green or rolling deployments — never take the app down to deploy
- Warm up cache and run any pre-flight checks before switching traffic to the new version
- Keep the previous release available for at least 30 minutes after deploy in case an immediate rollback is needed

**Rollback Strategy**

- Every deploy must have a defined rollback path before it is approved
- Database migrations that cannot be safely reversed (destructive changes) require a maintenance window and explicit approval
- Application code rollbacks must not require a database rollback — keep schema changes backward-compatible for at least one release cycle
- Test the rollback path in staging before deploying to production

**Feature Flags**

- Use feature flags for large or risky features — deploy the code dark, then enable it gradually
- Feature flags allow instant rollback without a code deploy — flip the flag off
- Remove flags once a feature is fully rolled out — don't accumulate permanent flags
- Never use feature flags as a substitute for proper branching and testing

**CI/CD Pipeline — minimum requirements:**

| Stage | What it runs |
| --- | --- |
| On every push | Lint, type check, unit tests |
| On PR open | Full test suite, static analysis |
| On merge to main | Full test suite, build, deploy to staging |
| On production deploy | Smoke tests, health check, alert if failed |

- No PR is merged with a red CI pipeline — ever
- Staging deploy is automatic on merge to main
- Production deploy requires a manual approval step
- Every production deploy triggers a post-deploy smoke test

**Secrets Rotation**

- Rotate API keys, database passwords, and signing secrets on a schedule
- Rotation must not require downtime — use dual-active key periods (old key stays valid during rollover)
- Document the rotation procedure for every secret before go-live

## 29. Dependency & Supply Chain Security

Your application is only as secure as its weakest dependency. Most production breaches today come through the supply chain, not through your own code.

**Dependency Management Rules**

- Commit your lockfile (`composer.lock`, `package-lock.json`, `yarn.lock`) — it guarantees every environment runs the exact same dependency versions
- Pin major versions in your manifest (`"^5.0"` not `"*"`) — never float on `latest`
- Review what you install — every dependency is code you are responsible for; check its maintainability, download count, and last update before adding it
- Prefer fewer, well-maintained dependencies over many small ones
- Never install a dependency for something you can write in 5 lines yourself (YAGNI applied to dependencies)

**Vulnerability Auditing — run on every project:**

| Command | When to run |
| --- | --- |
| `composer audit` | Before every deploy, in CI pipeline |
| `npm audit` | Before every deploy, in CI pipeline |
| `npm audit --fix` | After review — never blindly auto-fix |
| Dependabot / Renovate | Automated PRs for dependency updates |

- Integrate vulnerability scanning into your CI pipeline — fail the build on high-severity vulnerabilities
- Review and action security advisories within 48 hours of notification
- Never dismiss a vulnerability without a documented reason

**Keeping Dependencies Updated**

- Update dependencies on a regular schedule — not only when something breaks
- Use Dependabot or Renovate to automate dependency update PRs
- Update in small batches — one package at a time for major version bumps
- Run the full test suite after every dependency update before merging
- Check the changelog for breaking changes before accepting a major version update

**Transitive Dependencies**

- Know what your dependencies depend on — `composer why` and `npm ls` help here
- A vulnerability in a transitive dependency is still your vulnerability
- Override a vulnerable transitive dependency explicitly in your manifest rather than waiting for the parent package to update

**Supply Chain Integrity**

- Use official package registries only (Packagist, npm) — never install from git URLs in production unless you own the repo
- Verify package integrity hashes in lockfiles are not tampered with
- Do not run arbitrary install scripts from untrusted packages (`--ignore-scripts` in npm for untrusted deps)
- Review any dependency that requests unusual permissions or access

## 30. Timezone & Localisation

Timezone bugs are invisible until a customer gets the wrong delivery date, a scheduled job fires at 3am, or a financial report is off by one day. Get these right from the start.

**Storage Rules**

- Store all timestamps in UTC — always, no exceptions
- Store timezone preference as a separate string field on the user record (`Africa/Accra`, `America/New_York`)
- Never store a local time without its timezone — a datetime without a timezone is meaningless
- Use immutable date objects to avoid accidental mutation across timezone conversions

**Display Rules**

- Convert to the user's local timezone only at the display layer — never before
- Never store a display-formatted date — store UTC, format on render
- The formatting layer takes: UTC timestamp + user's timezone preference → local display string
- Put timezone conversion in one place (a utility function or view helper) — never scattered across templates

**DST (Daylight Saving Time) Edge Cases**

- Some timezones have DST transitions — clocks skip forward or backward by 1 hour
- A local time of 2:30am may not exist (spring forward) or may exist twice (fall back)
- Never schedule jobs at a fixed local time without accounting for DST — use UTC-based cron expressions
- For recurring schedules (daily at 9am), use a timezone-aware scheduler that recalculates the next UTC fire time after each DST transition
- Africa/Accra (GMT+0) has no DST — but if your users are in DST regions, their display times shift

**Scheduled Jobs & Cron**

- Write cron expressions in UTC unless the scheduler explicitly supports timezone-aware cron (e.g. `CRON_TZ=Africa/Accra`)
- If you must schedule at a local time, use a timezone-aware scheduling library and test across DST boundaries
- Document the timezone of every cron expression in a comment next to it
- Test scheduled jobs that cross midnight — off-by-one-day bugs commonly appear here

**Localisation (i18n)**

- Externalize all user-facing strings from day one if the app will ever be in more than one language — retrofitting is expensive
- Use a translation library (i18n, gettext, Laravel's `__()`) — never hardcode user-facing strings in logic
- Separate translation files by language and domain — not one giant flat file
- Handle pluralization correctly — `1 item` vs `2 items` vs `0 items` have different rules per language
- Format numbers, currencies, and dates using the user's locale — not the server's locale
- Right-to-left (RTL) languages require layout changes — plan for it early if the market includes Arabic, Hebrew, or Persian

## 31. API Deprecation Lifecycle

Breaking an API breaks your consumers — mobile apps, third-party integrations, partner systems. Handle deprecation deliberately, not reactively.

**Versioning recap:**

- URI versioning: `/api/v1/`, `/api/v2/`
- Version from day one — retrofitting is painful and breaks consumers
- A breaking change always gets a new version — never mutate v1 in a breaking way

**What counts as a breaking change:**

- Removing a field from a response
- Renaming a field
- Changing a field's type
- Changing a status code
- Removing an endpoint
- Changing validation rules to be more restrictive
- Changing authentication requirements

**What is NOT a breaking change (safe to do in the same version):**

- Adding a new optional field to a response
- Adding a new optional request parameter
- Adding a new endpoint
- Relaxing validation rules
- Performance improvements with the same contract

**Deprecation process:**

1. Release the new version (`v2`) alongside the old (`v1`) — never replace
2. Add a `Deprecation` header to all `v1` responses: `Deprecation: true`
3. Add a `Sunset` header with the planned removal date: `Sunset: Sat, 01 Jan 2028 00:00:00 GMT`
4. Add a `Link` header pointing to the migration guide: `Link: <https://api.example.com/docs/migration/v2>; rel="successor-version"`
5. Notify all known API consumers via email/changelog with a minimum notice period
6. Log which consumers are still calling deprecated endpoints — reach out to stragglers
7. Keep the deprecated version live for the full support window — never remove early
8. Remove only after the sunset date has passed and traffic to the old version has dropped to zero

**Minimum support windows:**

- Internal consumers (your own mobile app, frontend): 1 sprint minimum
- External consumers (partners, third-party integrations): 6 months minimum
- Public API (open to any developer): 12 months minimum

**Documentation:**

- Every version has its own documentation
- Migration guides are mandatory for every breaking change — document what changed and exactly how to update
- Changelog entries are required for every version and every deprecation announcement

## 32. Accessibility (a11y)

Accessibility is not a feature — it is a baseline quality requirement. An inaccessible UI is a broken UI for a significant portion of users.

**The minimum standard: WCAG 2.1 AA**

- Target Lighthouse accessibility score ≥ 90 on every page
- Run an automated accessibility check (axe, Lighthouse) in CI — fail on critical violations
- Automated tools catch \~30% of issues — supplement with keyboard and screen reader testing

**Semantic HTML**

- Use the correct HTML element for the job: `<button>` for actions, `<a>` for navigation, `<nav>`, `<main>`, `<header>`, `<footer>`, `<section>`, `<article>` for structure
- Never use a `<div>` or `<span>` where a semantic element exists
- Heading hierarchy must be logical — `<h1>` → `<h2>` → `<h3>`, never skipping levels
- Tables for tabular data only — never for layout

**Keyboard Navigation**

- Every interactive element must be reachable and operable via keyboard alone
- Tab order must follow the visual reading order
- Focus must be visible at all times — never `outline: none` without a visible custom focus style
- Modals must trap focus inside until dismissed — focus must return to the trigger element on close
- Keyboard shortcuts must not conflict with browser or screen reader shortcuts

**Screen Reader Support**

- Every image must have a meaningful `alt` attribute, or `alt=""` if purely decorative
- Every form input must have a visible `<label>` — not just a placeholder
- Use `aria-label`, `aria-labelledby`, or `aria-describedby` where a visible label is not possible
- Dynamic content changes (loading states, error messages, live updates) must use `aria-live` regions
- Never convey information through color alone — always pair color with text or an icon

**Color & Contrast**

- Text contrast ratio: minimum 4.5:1 for body text, 3:1 for large text (WCAG AA)
- Interactive elements (buttons, links) must meet 3:1 contrast against their background
- Test contrast in both light and dark mode
- Design status indicators (error, warning, success) with both color and icon/text

**Forms**

- Validation errors must be announced to screen readers — use `aria-live` or move focus to the error
- Required fields must be indicated — not only with an asterisk (use `aria-required="true"` as well)
- Error messages must be associated with their field via `aria-describedby`
- Never auto-submit a form — always require explicit user action

## 33. Offline-First & PWA

Apply this section when building mobile-first apps, PWAs, or any application that must work without a reliable network connection.

**The offline-first principle:**

> Write to local storage first. Show the user a success state. Sync to the server in the background. Never block the UI on a network call.

**Local storage rules**

- Write to local storage (IndexedDB, SQLite, local cache) before making any network request
- Mark records as `synced: false` on local write; update to `synced: true` after server confirmation
- Show the user a success state immediately after local write — not after server sync
- The UI reads from local storage — the sync engine updates it in the background
- No component or view touches the local DB directly — only a dedicated service layer (`db.ts`, `localStore`) does

**Sync engine**

- A dedicated background service owns all sync logic — not scattered across components
- On reconnect: push all dirty (unsynced) records to the server, then pull the server delta
- Pull strategy: use a `last_sync` timestamp — fetch only records updated since the last successful sync
- Conflict resolution must be defined explicitly — never left as an undefined edge case
  - Default: last-write-wins on `updated_at` — the newer timestamp wins
  - Document any deviations from this in an architecture decision log
- Sync must be idempotent — pushing the same record twice must produce one result
- Store the sync state (last synced timestamp, pending count) in a dedicated store — not derived at runtime

**Storage persistence**

- Call `StorageManager.persist()` on first load to request durable storage — without it, the browser may evict IndexedDB under storage pressure
- Handle storage quota errors gracefully — don't crash; surface a warning and prompt the user to free space
- iOS has no Background Sync API — use the `online` event as a fallback trigger for sync

**PWA requirements**

- Service worker caches all app shell assets (HTML, CSS, JS) for offline load
- App manifest with correct icons, name, display mode, and theme color
- HTTPS required — service workers do not work over HTTP
- Lighthouse PWA score ≥ 90
- Test offline mode explicitly — use browser devtools network throttling to simulate

**Data safety**

- Never store sensitive data (tokens, PII, financial data) in `localStorage` — use IndexedDB with encryption or httpOnly cookies
- Assume IndexedDB data can be read by the user — don't store secrets there
- Client-generated IDs (UUID v4 via `crypto.randomUUID()`) — the server accepts and stores them without replacement
- Soft deletes only — never hard-delete locally; sync the deletion flag to the server

## 34. Multi-Tenancy Patterns

Multi-tenancy means multiple independent customers (tenants) share the same application instance. Each tenant's data must be completely invisible to all other tenants — at all times, in all layers.

**When to use multi-tenancy:**

- SaaS products with multiple businesses as customers (e.g. a POS system sold to multiple shops)
- White-label platforms where each client has their own branded instance
- Any system where one deployment serves multiple independent organizations

*Single-tenant apps (built exclusively for one client) do not need this section — but read it so you recognise the patterns when the need arises.*

---

**The Three Isolation Strategies:**

| Strategy | How it works | Isolation | Cost | Best for |
| --- | --- | --- | --- | --- |
| **Row-level** | Every table has a `tenant_id` column; all queries filter by it | Lowest | Cheapest | Most SaaS apps |
| **Schema-level** | Each tenant gets their own DB schema; same tables, different namespace | Medium | Medium | Mid-scale, compliance needs |
| **Database-level** | Each tenant gets their own database | Highest | Most expensive | Enterprise, strict data residency |

**Row-level tenancy is the default choice** for most applications — it is the simplest to build, easiest to maintain, and scales well up to thousands of tenants.

---

**Row-Level Tenancy — Implementation Rules**

- Every tenant-scoped table must have a `tenant_id` foreign key column
- `tenant_id` is always set at the application layer — never trusted from user input
- Resolve the current tenant from the authenticated session, subdomain, or request header — not from a request body field
- Apply a **global query scope** on every tenant-scoped model so every query automatically filters by `tenant_id` — never rely on remembering to add `where('tenant_id', $id)` manually
- The global scope must be applied at the model level — not in controllers or services

```
// Every query on a scoped model automatically includes:
WHERE tenant_id = {current_tenant_id}
```

- Index `tenant_id` on every tenant-scoped table — it is in every WHERE clause
- Composite index `(tenant_id, created_at)` on tables with time-ordered queries

---

**Tenant Resolution**

How to identify which tenant is making a request — pick one strategy and use it consistently:

| Method | Example | Notes |
| --- | --- | --- |
| Subdomain | `acme.app.com` | Clean, common for SaaS |
| Path prefix | `app.com/acme/dashboard` | Simple but less elegant |
| Custom domain | `acme.com` → mapped to tenant | Premium feature |
| JWT / session claim | `tenant_id` in the token | For API-only backends |

- Resolve the tenant in middleware — before any controller or service runs
- If the tenant cannot be resolved, return 404 — not 401 (don't reveal that a tenant exists)
- Store the resolved tenant in the request context / container — never pass it as a parameter through every method

---

**Cross-Tenant Data Leak Prevention**

This is the highest-risk failure in a multi-tenant system. One tenant seeing another's data is a critical security incident.

- The global scope handles routine queries — but watch for these bypass patterns:
  - `Model::withoutGlobalScopes()` — must never be called without an explicit security review
  - Raw queries (`DB::select()`) — must always include `tenant_id` in the WHERE clause manually
  - Eager loading across relationships — ensure related models also have tenant scopes applied
  - Background jobs — the tenant context must be explicitly passed to and set inside every job
  - Exports and reports — always filter by tenant before generating
  - Aggregate queries (`COUNT`, `SUM`) — must scope to tenant, never across all tenants
- File storage — files must be stored under a tenant-namespaced path: `tenants/{tenant_id}/uploads/`; signed URLs must be generated within the tenant context only
- Cache keys must be tenant-namespaced: `tenant:{tenant_id}:users:list` — never share cache entries across tenants
- Logs must include `tenant_id` as a structured field — never mix tenant log entries

---

**Tenant Onboarding & Offboarding**

- Provisioning a new tenant must be an atomic operation — either fully set up or fully rolled back
- Offboarding (cancellation) must have a defined data retention and deletion policy — see Section 34 (Data Privacy) when added
- Never hard-delete tenant data immediately on cancellation — use a grace period with soft delete, then scheduled purge
- Provide a data export feature before deletion — tenants own their data

---

**Testing Multi-Tenancy — Required Tests**

These tests are non-negotiable on any multi-tenant system:

- [ ] Tenant A cannot read Tenant B's records — via API, direct model query, and any export
- [ ] Tenant A cannot update or delete Tenant B's records
- [ ] A request with no resolved tenant returns 404, not a data leak
- [ ] A background job for Tenant A does not accidentally process Tenant B's data
- [ ] Aggregate queries (counts, sums) return only the current tenant's data
- [ ] File signed URLs for Tenant A cannot be used to access Tenant B's files
- [ ] Cache entries for Tenant A cannot be read by Tenant B
- [ ] A new tenant starts with zero data from other tenants

```
// Example isolation test pattern
test('tenant A cannot read tenant B records') {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $record  = Record::factory()->for($tenantB)->create();

    $this->actingAsTenant($tenantA)
         ->getJson("/api/v1/records/{$record->ulid}")
         ->assertNotFound(); // 404, not 403 — don't reveal it exists
}
```

## 35. API Documentation

An API without accurate documentation is an API that cannot be used, integrated, or maintained. Documentation is not an afterthought — it is part of the deliverable.

**The core problem with manual docs:** Manually written API docs go stale the moment the code changes. The solution is docs-as-code — documentation generated from or validated against the actual implementation.

---

**OpenAPI Specification**

OpenAPI (formerly Swagger) is the industry standard for describing REST APIs. It is a machine-readable YAML or JSON file that describes every endpoint, request shape, response shape, authentication method, and error response.

- Every project exposing an API must have an OpenAPI spec
- The spec lives in the repository alongside the code — not in a separate wiki or Notion page
- The spec is the source of truth for the API contract — not the implementation, not the Postman collection
- Generate the spec from code annotations where the framework supports it (Laravel: Scramble, l5-swagger; NestJS: built-in; FastAPI: built-in)
- If auto-generation is not available, write the spec by hand and validate it against the implementation in CI

**What the spec must cover:**

- Every endpoint: method, path, description
- Every request: path params, query params, request body shape with types and constraints
- Every response: all possible status codes, response body shape for each
- Authentication: how to authenticate (Bearer token, API key, cookie)
- Error responses: the standard error shape for 400, 401, 403, 404, 422, 500
- Pagination: how paginated responses are structured
- Money fields: note that amounts are integers in minor units with a `_formatted` sibling

---

**Docs-as-Code — Keep Docs in Sync with Code**

Docs that live outside the codebase always go stale. Docs that live inside the codebase and are validated in CI stay honest.

| Approach | How it works | Best for |
| --- | --- | --- |
| Auto-generated from annotations | Docblocks / decorators → OpenAPI spec | Laravel (Scramble), NestJS, FastAPI |
| Auto-generated from tests | Tests double as spec — Dredd, Schemathesis | Any stack with feature tests |
| Hand-written + CI validation | Spec written manually, validated against live app in CI | Smaller APIs, any stack |
| Contract tests | Both sides agree on a spec; CI tests both sides | Microservices, mobile + API teams |

**Rules:**

- The spec must be committed to the repository — not generated and thrown away
- A CI step must fail if the spec is out of sync with the implementation
- The spec version must match the API version (`info.version: "1.0.0"` for `/api/v1/`)
- Breaking changes to the spec = a new API version (see Section 31)

---

**Contract Testing**

Contract testing verifies that the producer (API) and consumer (frontend, mobile app, third party) agree on the API contract — independently, without needing both running at the same time.

- **Consumer-driven contract testing**: the consumer defines what it expects; the provider verifies it can deliver
- Tools: Pact (polyglot), Dredd (OpenAPI-based), Schemathesis (property-based API testing)
- Run contract tests in CI on both sides — a provider change that breaks a consumer contract fails the build
- Especially valuable when: a mobile app and an API are developed by separate teams; when microservices call each other; when a third party integrates with your API

**Minimum contract test coverage:**

- Every endpoint the consumer calls must have a contract test
- Happy path response shape must be verified
- Every error response the consumer handles must be verified
- Authentication must be verified

---

**Serving the Documentation**

- Serve interactive API docs from the OpenAPI spec using Swagger UI or Redoc
- Expose docs at a predictable URL: `/api/docs` or `/docs`
- In production: protect docs behind authentication or restrict to internal networks — don't expose your full API surface to the public unless it is a public API
- In development and staging: always accessible to the team
- Postman collections or Bruno collections are acceptable supplements — but they are never the source of truth

---

**Writing Good API Descriptions**

- Every endpoint needs a one-line summary and a longer description explaining when and why to use it
- Every field needs a description — especially non-obvious ones (money fields in minor units, enum values, ULID format)
- Document edge cases inline — *"Returns 404 if the resource belongs to a different tenant"*
- Document rate limits on the affected endpoints
- Document idempotency behaviour on payment and write endpoints
- Keep descriptions in plain English — written for the developer who will integrate against it at 9pm under deadline pressure

## 36. Data Privacy & Compliance

Every application that collects, stores, or processes personal data is subject to privacy law. These rules have direct coding implications — they affect your schema design, your deletion logic, your logging rules, and your API responses. Get them right from the start; retrofitting is expensive and incomplete.

**Personal data (PII) is defined as:** any information that can identify a real person — name, email, phone number, home address, national ID number, Ghana Card number, TIN, IP address, device ID, location data, payment details, photos, biometric data.

---

**Laws You Must Know**

| Law | Jurisdiction | Who it applies to |
| --- | --- | --- |
| **NDPA** — Ghana Data Protection Act | Ghana | Any system processing Ghanaian citizens' data |
| **GDPR** — EU General Data Protection Regulation | EU / EEA | Any app with EU users, regardless of where you are based |
| **CCPA** — California Consumer Privacy Act | USA (California) | Apps with California users above certain thresholds |

The principle across all three is the same: **collect only what you need, protect what you collect, delete it when you no longer need it, and give users control over their own data.**

---

**Collect Only What You Need**

- Never collect personal data you do not have a specific, documented purpose for
- Do not add PII fields to models speculatively — YAGNI applies to personal data more than anywhere else
- Every PII field in your schema should have a documented reason: *why is this collected, what is it used for, how long is it kept*
- Do not copy PII into analytics systems, logs, or reporting tables unless absolutely necessary — and if you must, anonymize it first

---

**Protect What You Collect**

- Encrypt sensitive PII fields at the column/field level — not just the database at rest
  - Ghana Card numbers, TIN, national ID, passport numbers → encrypted column
  - Bank account numbers, card details → encrypted column or do not store at all
  - Email and phone → consider encryption for high-risk applications
- Use the framework's built-in encryption cast (`encrypted` in Laravel, equivalent in other frameworks)
- Sensitive files (KYC documents, ID photos, contracts) → private storage with signed temporary URLs only — never public URLs
- PII must never appear in log files — not even partially — see Section 17
- Access to PII fields must be logged in an audit table: who accessed what, when, and why
- Restrict PII access by role — not every part of the system needs to read every field

---

**User Rights — Implement These in Code**

**Right to Access (Subject Access Request)**

- Users can request to see all personal data you hold on them
- Build a data export endpoint or admin tool that collects all PII across all tables for a given user
- Export must be in a portable, machine-readable format (JSON or CSV)
- Must be fulfilled within 30 days (NDPA / GDPR)

**Right to Rectification**

- Users can correct inaccurate personal data
- Every PII field a user provided must be editable by that user
- When data is corrected, log the change (old value → new value, timestamp, who changed it) in an audit table

**Right to Erasure (Right to be Forgotten)**

- Users can request deletion of their personal data
- This does not mean hard-deleting every record — it means **anonymizing** PII while preserving the structural integrity of the data
- Pattern: replace PII fields with anonymized placeholders on account deletion

```
// On account deletion — anonymize, do not delete the row
$user->update([
    'name'       => '[deleted]',
    'email'      => "deleted_{$user->ulid}@deleted.invalid",
    'phone'      => null,
    'address'    => null,
    'deleted_at' => now(),
]);
```

- Transaction records, invoices, and financial records must be **kept** for legal/tax purposes (typically 5–7 years) but the user's personal details within them must be anonymized
- File storage: delete the actual KYC documents, ID photos, and profile pictures on erasure — not just the database references
- After anonymization, the account cannot be recovered — document this clearly

**Right to Portability**

- Users can download all their data in a structured, portable format
- Build a *"Download my data"* feature — JSON export of all records belonging to the user
- Include: profile, orders, transactions, activity history — everything you hold on them

**Right to Object / Withdraw Consent**

- Users can withdraw consent for marketing, analytics, or non-essential processing at any time
- Withdrawal must take effect immediately — not at the next billing cycle or end of month
- Store consent as a timestamped record: `{ user_id, purpose, consented_at, withdrawn_at, policy_version }`

---

**Consent Management**

- Consent must be explicit, informed, and freely given — pre-ticked checkboxes are not valid consent
- Record every consent event in a dedicated `consents` table:
  - `user_id`, `purpose` (enum: marketing, analytics, third\_party\_sharing), `consented_at`, `withdrawn_at`, `ip_address`, `policy_version`
- Never retroactively apply a new privacy policy to existing users without re-obtaining consent for new processing purposes
- Separate consent by purpose — consent to receive order confirmations is not consent to receive marketing

---

**Data Retention Policies**

Define retention periods before going live — not after a regulator asks:

| Data type | Recommended retention | Reason |
| --- | --- | --- |
| Transaction / financial records | 7 years | Tax law (Ghana: Income Tax Act) |
| KYC documents | Duration of relationship + 5 years | Anti-money laundering law |
| User profile (active) | Until deletion request | User right |
| User profile (after deletion) | Anonymized immediately | Privacy law |
| Audit logs | 2–5 years | Security and compliance |
| Application logs | 90 days | Operational only |
| Marketing consent records | Until withdrawn + 1 year | Proof of consent |

- Implement a scheduled job that purges or anonymizes data past its retention period automatically
- Never rely on manual cleanup — retention enforcement must be automated

---

**Breach Response (Coding Requirements)**

- Maintain a log of every system that holds PII and what data it holds — you cannot notify users about a breach if you do not know what was exposed
- Build a breach notification flow before you need it — not during the incident
- Under GDPR: notify the regulator within 72 hours of discovering a breach
- Under NDPA: notify the Data Protection Commission and affected individuals without undue delay
- The notification must state: what data was exposed, how many users affected, what happened, what you are doing about it

---

**Privacy by Design — Build It In, Not On**

- Privacy is an architecture decision, not a feature added at the end
- Ask before every new feature: *"What personal data does this collect? Does it need to? How long is it kept? Who can access it?"*
- Default to collecting less, not more
- Default to private, not public — data is private unless there is a specific reason to share it
- Data minimization at the API level: never return PII fields the client does not need for the current operation

## 37. Incident Response

Every production system will have incidents. The difference between a good team and a bad one is not whether incidents happen — it is how fast they are detected, contained, resolved, and learned from. These rules must be in place **before** going to production, not written during a 2am outage.

---

**Severity Levels — Define These Before Launch**

| Level | Definition | Target response | Examples |
| --- | --- | --- | --- |
| **P1 — Critical** | System down or data loss or payments broken | Immediate, any hour | DB unreachable, all payments failing, data breach |
| **P2 — High** | Major feature broken for all users | Within 1 hour | Login broken, orders cannot be placed |
| **P3 — Medium** | Feature broken for some users or degraded | Within 4 hours | Slow page loads, emails delayed, partial errors |
| **P4 — Low** | Minor issue, workaround available | Next business day | UI misalignment, non-critical report wrong |

---

**The Four Phases — Always in This Order**

1. **Detect** — your monitoring finds it before your users do; if a user reports it first, your alerting failed
2. **Contain** — stop the damage spreading before fixing root cause; roll back, flip a flag off, take an endpoint offline
3. **Resolve** — identify and fix root cause; verify in staging first if time allows; confirm system is healthy after
4. **Learn** — post-mortem within 48 hours; blameless; action items with owners and deadlines

---

**Runbooks — Write Before You Need Them**

A runbook is a step-by-step document for a specific known failure mode. Written when you are calm. Followed when you are not.

- Write a runbook for every P1 and P2 failure mode you can anticipate before launch
- Every runbook lives in the repository — not in someone's head or a personal Notion page
- Format: symptom → immediate containment steps → diagnostic steps → resolution steps → verification steps
- Common runbooks every production app needs:
  - Database connection exhausted
  - Queue worker stopped processing
  - Payment provider returning errors
  - Disk space critical
  - Deployment rollback procedure
  - Data breach initial response

---

**Post-Mortems — Required After Every P1 and P2**

- Written document completed within 48 hours of resolution
- Must contain: timeline of events, root cause, user impact, what worked, what did not, action items
- **Blameless** — the goal is to fix the system, not blame a person; a blameful culture hides incidents
- Action items must have a named owner and a deadline — not just a list of observations
- Shared with the whole team — everyone learns, not only the people who were on-call
- Filed and searchable — past post-mortems are the best source of patterns to fix

---

**Coding Rules for Incident Readiness**

These are the things you build into the system before an incident happens:

- **Feature flags on every risky feature** — instant rollback without a code deploy
- **Correlation IDs on every request** — so you can trace exactly what happened for a specific user or transaction (see Section 27)
- **Structured logs with context** — so you can query logs during an incident, not just scroll through them
- **Health check endpoints** — so your monitoring can confirm the system is operational end-to-end
- **Meaningful alerts** — alert on symptoms the user experiences (error rate, latency, payment failure), not just infrastructure metrics (CPU, memory)
- **Runbook links in alert messages** — when an alert fires, the message should link directly to the runbook for that alert
- **Idempotent operations** — so restarting a failed job or retrying a failed request does not cause double-processing (see Section 10 and Section 11)
- **Circuit breakers on external dependencies** — if a third-party provider goes down, fail fast with a graceful degradation rather than queuing thousands of retries that make recovery worse
- **Data backup and verified restore procedure** — take regular backups; test the restore procedure before you need it; an untested backup is not a backup

*These rules are the law for every project. When in doubt, re-read this file.* *Technology changes. These principles don't.*
