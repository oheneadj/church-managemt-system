# Definition of Done

> A feature is not done when the code works on your machine. It is done when every item on this list is checked off. No exceptions, no "we'll do it later."

---

## Why This Exists

"Done" without a shared definition means different things to different people. To a developer, done might mean "it works when I test it." To the product owner, done means "it's live and working for churches." This document closes that gap.

Every story in the backlog is measured against this list before it is marked complete.

---

## The Checklist

### Code Quality
- [ ] Code follows the patterns in [`09-engineering-principles.md`](./09-engineering-principles.md)
- [ ] Controller is thin — no business logic, no validation, no direct model calls
- [ ] All validation in a Form Request class
- [ ] All API responses through a Resource class
- [ ] Business logic in a Service class
- [ ] All new Eloquent models with `church_id` use the `BelongsToChurch` trait
- [ ] No raw queries in application code (Eloquent or Query Builder only)
- [ ] No N+1 queries (verified with Laravel Telescope in dev)
- [ ] Code passes Laravel Pint style check (`./vendor/bin/pint --test`)
- [ ] No `dd()`, `dump()`, `var_dump()`, `console.log()` left in committed code
- [ ] No `APP_DEBUG=true` or hardcoded credentials in any committed file

### Data & Database
- [ ] Migration file created for any schema change
- [ ] Migration `down()` method works (tested with `php artisan migrate:rollback`)
- [ ] New migration is backward-compatible with the current deployed version
- [ ] New columns that are `NOT NULL` have a default value or the table is new/empty
- [ ] Indexes added for all new `WHERE` and `JOIN` columns
- [ ] `07-data-model.md` updated to reflect any schema change

### Security
- [ ] New routes are behind `auth:sanctum` and `tenant` middleware (or explicitly exempted with justification)
- [ ] A Policy class exists for every new model and is registered in `AuthServiceProvider`
- [ ] Policy methods verify `church_id` ownership in addition to permission check
- [ ] Webhook endpoints verify the provider's signature before processing
- [ ] No PII (phone numbers, names, amounts) in log statements
- [ ] Encrypted cast applied to any new sensitive fields (per [`05-data-safety.md`](./05-data-safety.md))

### Multi-Tenancy
- [ ] A cross-tenant isolation test exists for every new API endpoint
  - Creates data for Church A
  - Authenticates as Church B user
  - Asserts the endpoint returns `403` or `404` — not Church A's data

### Async
- [ ] Any outbound network call (SMS, WhatsApp, email, third-party API) is dispatched as a queue job
- [ ] The queue job implements `failed(Throwable $exception)` — logs the failure and updates the relevant status record
- [ ] Job retries are configured appropriately (default: 3 attempts with exponential backoff)

### Testing
- [ ] Unit or feature test exists for the Service class (if a Service was added or changed)
- [ ] HTTP test exists for each new API endpoint covering: happy path, validation failure, unauthenticated, unauthorised
- [ ] For **financial code** (contributions, pledges, platform fee): test coverage is mandatory, not optional
- [ ] For **authentication code** (OTP, token, session): test coverage is mandatory, not optional
- [ ] All existing tests pass (`php artisan test`)
- [ ] No tests were deleted or commented out to make the suite pass

### API Documentation
- [ ] `08-api.md` updated with any new or changed endpoint
  - Method, URL, required permission
  - Request body with field types
  - Response shape with example
  - Any side effects noted (e.g. "queues SMS job")
- [ ] Any changed response shape is backward-compatible OR the change is versioned

### Frontend (for full-stack features)
- [ ] Feature works in the latest version of Chrome, Firefox, and Safari
- [ ] Feature works on a 375px wide screen (iPhone SE viewport — the minimum we support)
- [ ] Loading states shown for all async operations
- [ ] Error states handled and shown to the user (not just `console.error`)
- [ ] Empty states handled (what does the page look like with zero records?)
- [ ] Destructive actions (delete, void, deactivate) require confirmation
- [ ] Toast/notification shown on success and failure
- [ ] Form inputs have correct keyboard types on mobile (`type="tel"` for phone, `type="number"` for amounts)

### Permissions & Roles
- [ ] The feature is inaccessible (UI hidden + API returns `403`) for roles that should not have access
- [ ] The permission matrix in [`02-scope-and-backlog.md`](./02-scope-and-backlog.md) and the relevant domain doc reflects the access level

### Communication Features (if applicable)
- [ ] SMS/WhatsApp/email content reviewed — correct grammar, no placeholders left unfilled
- [ ] Message content does not exceed Africa's Talking's per-SMS character limit (160 chars for standard, 153 for multi-part) or is handled as multi-part
- [ ] SMS credit deduction is logged in `sms_credit_ledger`
- [ ] Message outcome (sent/delivered/failed) is recorded in `message_logs`

### Financial Features (if applicable)
- [ ] Contribution records are never deletable — only voidable
- [ ] Void action requires a reason
- [ ] Void is logged with acting user ID and timestamp
- [ ] Paystack webhook idempotency is handled (duplicate reference = no duplicate record)
- [ ] Platform fee routing via subaccount confirmed in Paystack test environment
- [ ] Sunday offering report reflects the change correctly

### Deployment
- [ ] Feature branch merged to `staging` and deployed successfully
- [ ] Tested on staging against realistic data (not just happy-path on empty DB)
- [ ] No staging errors in the application log
- [ ] Queue workers processed all jobs without failures
- [ ] Migration ran successfully on staging (`php artisan migrate` output reviewed)

### Acceptance
- [ ] Product owner has reviewed the feature on staging and confirmed it matches the acceptance criteria in the user story
- [ ] If the story involved a UI change, it was reviewed on a real device (not just a browser resized to phone width)

---

## Special Rules for MVP Features

During the MVP sprint, a feature also must:

- [ ] Work reliably with a slow 3G connection (tested with Chrome DevTools network throttle)
- [ ] Work correctly in a WhatsApp Web session opened on a cheap Android phone browser
- [ ] Not require a church admin to understand what a "webhook" or "subaccount" is

---

## Definition of "Ready" (Before a Story Can Be Started)

Before a developer picks up a story, it must be:

- [ ] Written in the user story format: "As a [role], I want to [action] so that [outcome]"
- [ ] Has clear acceptance criteria (at least 3 bullet points)
- [ ] Design / wireframe approved (for UI stories)
- [ ] Dependencies identified and resolved (e.g. "requires fund_types migration to be complete first")
- [ ] Estimated (story points assigned in sprint planning)

A story that is not "Ready" does not enter the sprint.

---

## Who Signs Off

| Type of change | Sign-off required from |
|---|---|
| Backend only | Lead Engineer |
| Frontend only | Lead Engineer |
| Full-stack feature | Lead Engineer + Product Owner |
| Financial feature (contributions, pledges, fees) | Lead Engineer + Product Owner (both required) |
| Authentication change | Lead Engineer (mandatory code review) |
| Database migration | Lead Engineer |
| Third-party integration (Paystack, AT, Meta) | Lead Engineer + Product Owner |
| Production deployment | Lead Engineer |
