# Decision Log

> Every significant decision made on this project — what we decided, why, who decided it, and when. If a decision is ever questioned or needs to be revisited, start here. Decisions are never deleted — they are superseded.

---

## How to Use This Log

When a significant decision is made:
1. Add it at the top of the relevant category (newest first within each category)
2. Fill in all fields — especially the **Alternatives Considered** section
3. If a previous decision is being reversed, mark the old entry as **Superseded by D-XXX** and add the new entry

A "significant decision" is one that:
- Affects the architecture or data model
- Costs money or changes the business model
- Removes a feature that was previously planned
- Chooses between two or more real alternatives that would have produced meaningfully different outcomes

---

## Product & Business Decisions

---

### D-001 — SaaS over standalone deployment
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active

**Decision:** The product ships as a multi-tenant SaaS only. There is no standalone or self-hosted version in v1 or v2.

**Rationale:** Target market (100–800 member churches) has no IT staff, no server budget, and no capacity to manage software. Self-hosting creates unbounded support overhead with no recurring revenue benefit. Standalone is reserved for v3 Enterprise, contract-only, priced high enough that it is always less attractive than the Dedicated Hosted Instance tier.

**Alternatives considered:**
- **Standalone from day one:** Rejected. Would fragment the codebase, eliminate recurring revenue on some customers, and require us to support multiple versions simultaneously.
- **Standalone as a paid product tier:** Rejected for v1–v2 (see above). Not ruled out for v3 if a denomination signs a contract.

**How to handle standalone requests:** See `decisions/product-and-architecture-decisions.md` §1.

---

### D-002 — GHS-only pricing
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active

**Decision:** All pricing, invoicing, reports, and contribution records are denominated in Ghana Cedis (GHS). No USD, EUR, or multi-currency support.

**Rationale:** USD pricing is a real pain point in the market — it signals a foreign product with foreign assumptions. GHS pricing is a trust signal. Our target market earns and thinks in GHS. Currency conversion for diaspora giving is handled by Paystack transparently.

**Alternatives considered:**
- **USD pricing:** Rejected. Competitors using USD pricing are perceived as expensive and foreign.
- **Multi-currency:** Rejected for MVP. Adds complexity (exchange rate management, reporting inconsistency). Revisit in v3 if diaspora giving becomes a significant use case.

---

### D-003 — Platform fee on processed giving
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active

**Decision:** The platform takes a small percentage fee on every contribution processed via Paystack (MoMo or card) using Paystack subaccounts. Cash contributions carry no platform fee.

**Rationale:** Provides a revenue stream that scales with platform usage. Disclosed to churches upfront — not a hidden charge. Paystack subaccounts handle the split automatically with no manual reconciliation.

**Alternatives considered:**
- **No platform fee, subscription only:** Rejected. Leaves revenue on the table. A church processing GHS 10,000/month in digital giving generates meaningful fee revenue that subsidises the subscription price.
- **Fixed fee per transaction:** Rejected. Too complex to explain. Percentage is simpler and proportional.

**Exact percentage:** TBD — see open question Q3 in `12-risks-and-open-questions.md`.

---

### D-004 — SMS billing: free credits per tier + top-up bundles
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active

**Decision:** Each plan tier includes a free monthly SMS credit allocation. When credits are exhausted, churches buy top-up bundles via Paystack (like airtime top-up). Bundles are tracked in `sms_credit_ledger`.

**Rationale:** Flat subscription with unlimited SMS would expose the platform to margin destruction from heavy-sending churches. Per-message billing is unfamiliar to the market. Bundle top-ups are immediately understood ("like buying airtime") and give the church control over their spending.

**Alternatives considered:**
- **Unlimited SMS on flat plan:** Rejected. A church sending 500 broadcast messages/month at GHS 0.06/message would consume GHS 30/month in SMS costs alone — unsustainable on a low-tier plan.
- **Per-message billing:** Rejected. Too granular and unfamiliar to non-technical church admins.
- **No SMS included, buy-only:** Rejected. Makes the free trial unattractive.

---

### D-005 — No church accounting in the product
**Date:** October 2026
**Decided by:** Product Owner + Lead Engineer
**Status:** Active

**Decision:** The product will never implement double-entry accounting or statutory financial reporting. v1 tracks contributions and pledges only. v2 adds a simple expenditure log (record what went out, not a balance sheet).

**Rationale:** Full church accounting is a compliance and liability risk. Ghanaian churches use external accountants or bookkeepers for formal accounts. Building accounting poorly is worse than not building it — errors in financial records damage trust catastrophically. The CSV export is sufficient for a church's accountant.

**Alternatives considered:**
- **Basic bookkeeping (income vs. expense):** Deferred to v2 as a simple expenditure log only.
- **Integration with accounting software (QuickBooks, Sage):** Possible v3 consideration, not planned.

---

### D-006 — Store plan name in DB, prices in Paystack only
**Date:** October 2026
**Decided by:** Lead Engineer
**Status:** Active

**Decision:** The `churches.plan` column stores the plan name (`starter`, `growth`, `pro`, `enterprise`) as an enum. Actual GHS prices exist only in Paystack payment links and are never stored in the database.

**Rationale:** Decouples pricing from code. Changing subscription prices requires updating Paystack links only — no migration, no code deploy, no data update. Historical plan names remain meaningful regardless of price changes.

**Alternatives considered:**
- **Store price in DB:** Rejected. Every price change requires a data migration. Historical records become misleading if prices change.
- **Store price + effective date in DB:** Rejected. Unnecessary complexity for the current scale.

---

## Architecture Decisions

---

### D-007 — Single database multi-tenancy (not database-per-tenant)
**Date:** October 2026
**Decided by:** Lead Engineer
**Status:** Active

**Decision:** All churches share a single PostgreSQL database (D-021). Tenant isolation is enforced by `church_id` on every table, with automatic filtering via Laravel global scopes (`BelongsToChurch` trait).

**Rationale:** Database-per-tenant is standard at large SaaS scale but operationally expensive at startup scale — running migrations across 100 isolated databases is a significant maintenance burden. Single-DB with proper scoping is the standard pattern for Laravel SaaS applications and handles tens of thousands of tenants without issue.

**Exception:** Enterprise/Dedicated tier customers get an isolated database on a separate instance. The codebase is identical.

**Alternatives considered:**
- **Database per tenant:** Rejected for v1–v2. Revisit if regulatory requirements (data residency) or enterprise contracts require it.
- **Schema per tenant (PostgreSQL):** Rejected. Same operational concern as DB-per-tenant (migrations across every schema). PostgreSQL row-level security may be added later as defence in depth on top of the `church_id` global scope.

---

### D-008 — Full SPA (separate project) over Inertia.js or Blade
**Date:** October 2026
**Decided by:** Lead Engineer + Product Owner
**Status:** Partly superseded by D-022 — the SPA stays a pure REST/JSON client with no Inertia, but it now lives in the Laravel repo instead of a separate project. See D-015 for frontend framework choice

**Decision:** The frontend is a fully separate SPA that communicates with the Laravel API exclusively via REST/JSON. No server-rendered views, no Inertia.js.

**Rationale:** App-like UX without page reloads is critical for a tool used continuously during a Sunday service. The clean API boundary means the same API can serve a future denomination portal, mobile PWA, or third-party integration without architectural changes.

**Alternatives considered:**
- **Inertia.js (Vue + Laravel SSR):** Rejected. Tight coupling between frontend and backend makes independent deployment harder. The shared-router approach adds complexity without enough benefit at this scale.
- **Laravel Blade + Alpine.js:** Rejected. Not suitable for the rich, real-time UX required (live check-in dashboard, contribution recording with instant feedback).

---

### D-015 — React 19 over Vue 3 for the frontend SPA
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active — supersedes the Vue 3 choice implicit in D-008

**Decision:** The frontend SPA is built with React 19 (latest), TypeScript, Vite, React Router v7, Zustand v5, TanStack Query v5, Tailwind CSS v4, and shadcn/ui.

**Full stack:**
- React 19 + TypeScript 5.x
- Vite 6.x (build tool)
- React Router v7 (routing)
- Zustand v5 (global client state)
- TanStack Query v5 (server state, caching)
- Tailwind CSS v4 (styling)
- shadcn/ui (accessible component primitives, owned in codebase)
- Axios v1 (HTTP client, wrapped in typed service layer)

**Rationale:** React has the largest frontend ecosystem and the largest available developer pool in Ghana and globally — easier to hire and onboard contributors. React 19's concurrent rendering and improved server-component foundations give a longer architectural runway. shadcn/ui provides accessible, fully-owned components without lock-in to a component library.

**Alternatives considered:**
- **Vue 3 + Pinia:** Strong alternative — simpler mental model, gentler learning curve. Rejected because React's ecosystem advantage and hiring pool are more valuable as the team grows.
- **Next.js (React SSR):** Rejected — no SSR needed; the API handles all data, and the SPA is served as static files from Nginx. SSR adds infra complexity with no benefit.
- **SvelteKit:** Excellent DX but smallest talent pool of the three. Too risky for a project where hiring matters.

---

### D-009 — Paystack for payments (over Flutterwave or direct MoMo API)
> **Amended by D-020:** Paystack is now used for card payments only. MoMo collections go through PayPerlez (D-018).
**Date:** October 2026
**Decided by:** Lead Engineer
**Status:** Active

**Decision:** Paystack handles all payment processing — MoMo (all three Ghana networks), card, subaccounts.

**Rationale:** Paystack is the most developer-friendly payment gateway in West Africa with the best documentation, webhook reliability, and subaccount support for marketplace-style splits. Their PHP SDK is mature. They are already used by many Ghanaian businesses, meaning finance secretaries may already recognise the payment flow.

**Alternatives considered:**
- **Flutterwave:** Solid alternative. Paystack chosen for better subaccount support and cleaner webhook documentation.
- **Direct MTN MoMo API:** Requires separate MoMo API partnerships with each telco (MTN, Telecel, AirtelTigo), lengthy approval process, and more complex reconciliation. Paystack abstracts all of this.
- **Hubtel:** Ghana-specific but more expensive and less developer-friendly.

---

### D-010 — Oracle Always Free ARM + Coolify for infrastructure
**Date:** October 2026
**Decided by:** Lead Engineer + Product Owner
**Status:** Active

**Decision:** The platform runs on an Oracle Always Free ARM instance (4 OCPUs, 24GB RAM) managed by Coolify (open-source PaaS).

**Rationale:** Zero infrastructure cost while validating the market. 4 OCPUs and 24GB RAM is sufficient for 50–100 churches with no performance concerns. Coolify eliminates manual Nginx, Certbot, and Supervisor configuration. CI/CD is push-to-deploy via GitHub → Coolify webhook.

**Alternatives considered:**
- **Hetzner (paid):** Great value (€6/month for ARM). Chosen as the first paid fallback if Oracle becomes unreliable.
- **DigitalOcean / Linode:** More expensive per unit than Hetzner for equivalent ARM specs.
- **Heroku:** Too expensive at any meaningful scale, limited control.
- **Hostinger:** Considered initially. Oracle free tier offers better specs; Hostinger would be a fallback if Oracle has issues.

**Risk:** Oracle has historically reclaimed "idle" free instances. Mitigation in `12-risks-and-open-questions.md` R4.

---

### D-011 — Subdomains over path-based routing for tenant resolution
**Date:** October 2026
**Decided by:** Lead Engineer + Product Owner
**Status:** Active

**Decision:** Each church is identified by subdomain: `{slug}.yourapp.com`. Not by path (`yourapp.com/{slug}`).

**Rationale:** Cleaner branding per church. Wildcard SSL covers all subdomains automatically. Tenant isolation is visually clear to the church admin — they see their own domain. Easier to map to a custom domain in v3.

**Alternatives considered:**
- **Path-based:** `yourapp.com/grace-assembly`. Simpler DNS (one A record, one cert). Rejected because it makes custom domain mapping impossible, and `yourapp.com/grace-assembly` looks like a subdirectory, not a product.

---

### D-012 — OTP-only authentication (no passwords)
**Date:** October 2026
**Decided by:** Lead Engineer + Product Owner
**Status:** Active

**Decision:** All users (staff and members) authenticate via phone number + OTP sent by SMS. There are no passwords in the system.

**Rationale:** Church admins and members in Ghana are highly phone-native but often forget or reuse passwords. OTP-only removes the password reset flow, eliminates credential stuffing attacks, and aligns with the existing behaviour (Ghanaian users are very comfortable with OTP via mobile banking apps). One less attack surface.

**Alternatives considered:**
- **Password + OTP (2FA):** Rejected. Passwords add complexity and a support burden (resets, forgotten passwords) for no meaningful security gain when OTP alone is the authentication factor.
- **Password only:** Rejected. Weakest option. Members in particular would use trivial passwords.
- **Google/Apple SSO:** Rejected for MVP. Not all church admins have Google accounts, and some have religious objections to Apple/Google dependency.

---

### D-013 — Africa's Talking for SMS (over Hubtel or direct NCA routes)
> **Amended by D-020:** PayPerlez is now the default SMS and OTP provider; Africa's Talking is the fallback.
**Date:** October 2026
**Decided by:** Lead Engineer
**Status:** Active

**Decision:** Africa's Talking handles all outbound SMS.

**Rationale:** Excellent developer experience, reliable delivery in Ghana, competitive pricing, official PHP SDK, and supports sender ID registration for Ghana. Credit-based model integrates cleanly with our bundle top-up business model.

**Alternatives considered:**
- **Hubtel:** Ghana-specific, but primarily a consumer product with a less developer-friendly API.
- **Twilio:** International, reliable, but expensive for Ghana — and local routing is less reliable than a local provider.
- **Direct NCA/telco route:** Not available without a licensed aggregator partnership — not viable for a startup.

---

### D-014 — Resend for transactional email (with own SMTP later)
**Date:** October 2026
**Decided by:** Lead Engineer
**Status:** Active

**Decision:** Use Resend for transactional email in MVP. Laravel Mail abstraction means switching to own SMTP later requires only `.env` changes.

**Rationale:** Resend has a generous free tier, excellent deliverability, and minimal setup. Own SMTP (e.g. Postal) is a cost-saving measure for when email volume justifies it. The abstraction layer means we are not locked in.

**Alternatives considered:**
- **SendGrid:** More expensive, more complex. Overkill for MVP email volume.
- **Mailgun:** Solid alternative to Resend. Resend chosen for simpler developer UX and better free tier.
- **Own SMTP from day one:** Rejected. Deliverability requires domain reputation warmup. Resend gives instant good deliverability.

---

### D-016 — ULID as the sole primary key (not UUID, not integer + external ID)
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active — supersedes the UUID primary-key convention in the original data model

**Decision:** Every table uses a `CHAR(26)` ULID as its only primary key. There is no separate auto-increment integer. Models use Laravel's `HasUlids`; foreign keys use `foreignUlid()`; request validation uses the `ulid` rule.

**Rationale:** The universal coding agreement requires that no guessable integer ever leaves the database. A ULID primary key meets that directly without the cost of a second identifier column on every table. ULIDs are time-ordered, so inserts append to the InnoDB clustered index instead of fragmenting it like random UUID v4. They are 26 characters rather than 36, which matters because the primary key is copied into every secondary index and `church_id` plus several foreign keys are indexed on almost every table. Nothing was built yet, so switching cost only documentation edits.

**Alternatives considered:**
- **UUID primary key:** Rejected. Longer (36 chars), and Laravel's current `HasUuids` generates v7 anyway, so the ordering benefit is not unique to UUID.
- **Integer primary key + public ULID (universal agreement default):** Rejected. Adds a second column and index to every table for no practical gain at this scale.

**Consequences:**
- Offline contribution recording (R7) generates ULIDs client-side with a small `ulid` library; the server validates the format and enforces uniqueness and never trusts client IDs for ordering.
- Third-party references (Paystack, PayPerlez, Moolre) are separate string columns and are not primary keys.
- The generic `Addendum — Laravel.md` rule about a hidden integer `id` plus `ulid` column does not apply to this project.

---

### D-017 — String columns + PHP backed enums; money stays DECIMAL(12,2) in GHS
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active

**Decision:**
1. Fixed-value fields (status, type, method, channel, etc.) are `VARCHAR(30)` columns cast to PHP backed enums in `app/Enums/`. The database never uses native `ENUM` columns. Mirror each enum as a TypeScript union/const in the frontend so there are no magic strings on either side.
2. Money stays `DECIMAL(12,2)` in GHS, as originally designed. We do not adopt the universal agreement's integer-minor-units rule.

**Rationale:**
1. This system keeps gaining values (`ussd`, `payperlez`, new member statuses, new fund channels). Altering a MySQL `ENUM` is a schema migration on a live table; with a string column it is a code change. The enum class still enforces valid values at the application layer, and it is where `label()` and badge helpers live.
2. `DECIMAL` is exact, which is the concern behind the "no floats" rule. All amounts are single-currency GHS with two decimal places, and the pesewas conversion happens only at the payment-provider boundary (covered by QA-07 TC-07-04). Switching to integers would rewrite every doc, report and QA expectation for no precision gain.

**Alternatives considered:**
- **Keep native `ENUM`:** Rejected. Painful migrations as the value sets grow.
- **Integer minor units for money (universal agreement default):** Rejected, see above. Revisit only if multi-currency is ever added (D-002 rules it out for now).

**Consequences:**
- Migrations use `$table->string('status', 30)`; models cast with `'status' => MemberStatus::class`.
- The API still returns enum values as strings; add a `*_label` sibling where the UI needs display text.
- Any code that handles money must use `decimal`/string-safe arithmetic (for example `brick/math` or `bcmath`), never PHP floats. This is a new rule for the engineering principles.

---

### D-018 — Automatic per-transaction disbursement for MoMo/USSD (no fee split)
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active — amends D-003 (how the platform fee is collected)

**Decision:** MoMo gifts (and USSD gifts, once USSD is added after the MVP, see D-019), collected through PayPerlez (or Moolre for USSD), settle to the platform's provider account. The platform immediately and automatically disburses the net amount to the church's registered MoMo wallet or bank account, keeping its fee. No batching, no held balances. Card payments stay on Paystack with the subaccount split, because Paystack settles next-day and cannot be paid out instantly.

**Rationale:** PayPerlez and Moolre cannot split a payment at charge time the way Paystack subaccounts do, but both credit collections immediately, so the platform can pay out right away without pre-funding a float. This lets the platform fee apply to MoMo/USSD, the main giving channels in Ghana, and keeps PayPerlez as the default MoMo provider.

**Alternatives considered:**
- **Direct settlement, no per-transaction fee:** Rejected. Gives up the revenue D-003 planned.
- **Direct settlement + fee billed monthly:** Rejected. Collection risk and extra billing work.
- **Make Paystack the default MoMo provider just to get splits:** Rejected. Loses PayPerlez/Moolre pricing and USSD.

**Consequences:**
- New `disbursements` table and church payout fields (see `07-data-model.md`).
- Digital giving stays disabled for a church until its payout account is verified.
- The payout job runs on the `payments` queue, is idempotent on `contribution_id`, retries with backoff, and alerts on failure. A daily reconciliation compares collected totals with disbursed totals plus fees.
- Design principle 5 is reworded: the platform no longer "does not touch the money"; it passes it through automatically and never keeps a church balance.

**Settled after review (Oct 2026):**
- **Transaction fees:** the church bears them. We request `gross − platform_fee` from the provider and its transfer fee comes out of that.
- **Refunds:** once a gift has been paid out the platform does no refunds, chargebacks or clawbacks. That is between the church and the giver.
- **Platform fee:** calculated by us per transaction as `clamp(round(gross × rate, 2), floor, cap)`: **1% of the gift, capped at GHS 10.00, with a GHS 0.10 floor**, and a **minimum digital gift of GHS 1.00**. All four values live in platform config. (PayPerlez collections have no fee or split parameter, so there is nothing to "send" to the provider; the fee is applied by reducing the disbursement amount.)
- **PayPerlez API:** confirmed against its public docs (Oct 2026), see `14-integrations.md`. Disbursement fees come out of the requested amount, there is an account-name verification endpoint and a balance endpoint, and webhooks use `X-Payperlez-Signature` (`t=…,v1=…`, HMAC-SHA256 over `timestamp.body`).
- **Collection fee:** PayPerlez adds it on top of the requested amount, so the church receives the full `amount` and bears only the payout fee. Confirmed from the collections page.
- **Going live:** the business account is already verified with PayPerlez, so live keys are not a blocker (Product Owner, Oct 2026).
- **Limits:** no daily payout limit. The maximum per transaction is **GHS 30,000**, so digital (MoMo/USSD) gifts must be validated at `max:30000.00` and anything larger must be split or recorded another way. (The earlier generic `max:999999.99` rule applies to cash only.)
- **Reversals:** the platform does not handle reversals, refunds or disputes after payout. Residual risk, accepted by the Product Owner: if the provider itself ever reversed a collection and debited our balance, the platform would absorb it, because we do not claw back from churches.
- **Immediate availability:** confirmed by the Product Owner directly with PayPerlez (see open item 2).
- **Giver-facing fee (all digital payments):** shown openly and paid by the giver on top of the gift ("Gift 100.00 + processing fee 0.80 = 100.80"). The church receives the full gift amount less only our platform fee and its payout fee. Receipts and reports record the gift amount, the processing fee and the total paid (`contributions.amount`, `processing_fee`, `total_paid`). Chosen over hiding the fee by shrinking the gift, so the amount the church is credited always matches what the giver intended.

**Still open (must be settled before Sprint 4):**
1. Legal opinion on the Bank of Ghana position for receiving and forwarding third-party funds, even momentarily.
2. **Immediate availability: settled.** PayPerlez confirmed (relayed by the Product Owner, Oct 2026) that collected funds are available immediately, with the same behaviour on all networks. A minimum operating balance of GHS 1.00 must stay in the account, so the payout job only sends when `available_balance − requested_amount ≥ 1.00`, otherwise it waits and retries. This was also seen in the test environment. Ask PayPerlez to put it in writing when convenient, but it no longer blocks design.
3. **Showing the fee estimate up front.** The collection fee is null until settlement, so the giving screen needs the live fee schedule to show an estimate. Until we have it, show "plus a small processing fee" and put the exact figure on the receipt.
4. **Moolre** (USSD): **ON HOLD, USSD deferred past MVP (D-019).** Pricing is recorded in `14-integrations.md` (collection 1% min GHS 0.50 + network fee; transfer 1% min GHS 0.50; USSD GHS 420/month + GHS 0.014 per session). The collection, transfer, balance and webhook APIs are now read and recorded in `14-integrations.md` (webhook has no HMAC, only a body `secret` plus IP allowlist; transfer fees are charged on top of the amount, so the church-bears-fees rule needs us to compute the amount to send; payouts need our server IP whitelisted). Still unverified: the USSD protocol itself (interactive guide only, not readable offline), whether wallet funds are available immediately for transfer, and the fee schedule (docs example shows 0.01 on 1.00, pricing page says min 0.50). Decide whether USSD gifts follow the same auto-disbursement flow, and whether USSD needs a higher minimum gift because of Moolre's GHS 0.50 minimum fees.
5. PayPerlez webhook event names and payload shape, which are not in the public docs. Polling covers us until we have them.

---

### D-019 — USSD giving deferred past the MVP (Moolre on hold)
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active

**Decision:** USSD giving is not part of the v1 MVP. Digital giving at launch is MoMo through PayPerlez (with automatic disbursement, D-018) plus cash recording, and cards through Paystack if cards are built. Moolre, which was only needed for USSD, is not integrated for now.

**Rationale:** USSD's protocol is not readable in Moolre's public docs (interactive guide only), its terms are untested (no sandbox key yet), its fees are heavier on small gifts (GHS 0.50 minimum on both collection and payout), and it adds a GHS 420 per month fixed cost. PayPerlez's MoMo flow has been tested end to end in the sandbox and covers the core need.

**Consequences:**
- `02-scope-and-backlog.md` marks the USSD story as deferred; the Moolre section of `14-integrations.md` and the USSD section of `04-giving-and-contributions.md` stay as reference only.
- No `ProcessUssdWebhookJob`, `VerifyMoolreCallback` middleware or Moolre provider class in v1. `churches.ussd_code`, `contributions.ussd_reference` and `payment_method = ussd` stay in the schema as unused, nullable placeholders to avoid a later migration.
- PayPerlez is the only MoMo provider for MVP. Its USSD is "coming soon" (see `14-integrations.md`).
- Revisit when a church asks for USSD giving. First steps then: get Moolre's USSD protocol and a sandbox key, then decide whether USSD gifts use auto-disbursement (D-018) or Moolre settlement direct to the church.

---

### D-020 — Provider abstraction with PayPerlez as the default
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active — amends D-009 (payments) and D-013 (SMS)

**Decision:** SMS, collections and payouts are accessed through provider interfaces (`SmsProvider`, `PaymentProvider`, `DisbursementProvider`, defined in `docs/09-engineering-principles.md`). Defaults:
- SMS and OTP: PayPerlez, with Africa's Talking as a manual fallback.
- MoMo collections and church payouts: PayPerlez.
- Card payments: Paystack (subaccount split), when cards are built.
- USSD: Moolre, on hold (D-019).

Collections are resolved by payment method (`PAYMENT_MOMO_PROVIDER`, `PAYMENT_CARD_PROVIDER`), not by one global primary. Money crossing the interfaces is a **decimal string in GHS**; pesewas conversion exists only inside the Paystack adapter. There is no automatic cross-provider fallback: the operator switches the provider in `.env` and restarts workers.

**Rationale:** PayPerlez covers SMS, OTP, MoMo collections and payouts in one account and was verified end to end in its sandbox. Paystack is kept only where PayPerlez has no equivalent (cards, with the split). Keeping providers behind interfaces avoids lock-in and pricing risk.

**Alternatives considered:**
- **Paystack for everything (D-009 as written):** Rejected. Paystack's split model cannot pay out per transaction to many church accounts the way D-018 needs, and settles next-day.
- **Automatic failover between SMS providers:** Rejected. Complexity without enough reliability gain at this scale (decisions file §11).

---

### D-021 — PostgreSQL instead of MySQL
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active — supersedes the MySQL 8 choice in D-007 and the architecture docs

**Decision:** The database is PostgreSQL (18 locally; pin the same major version in staging and production). Single database, `church_id` on every table (D-007).

**Consequences for the docs and code:**
- `TINYINT(1)` booleans are native `boolean`; `JSON` columns are `jsonb`.
- "Unique where not null" indexes are native partial indexes. Unique keys on soft-deleted tables are partial too (`WHERE deleted_at IS NULL`) so a re-added phone or member number does not collide with a deleted row.
- Case-insensitive search uses `ILIKE` with a `pg_trgm` GIN index, not MySQL `LIKE`/`FULLTEXT`.
- Backups use `pg_dump` (custom format), restores use `pg_restore`. CI runs against a Postgres service with `pdo_pgsql`.
- Online migrations use `CREATE INDEX CONCURRENTLY` (in a migration with `$withinTransaction = false`).
- ULID `CHAR(26)` keys, `DECIMAL(12,2)` money and VARCHAR-plus-PHP-enum columns (D-016, D-017) are unchanged. Use `numeric(12,2)` semantics; never PHP floats.

**Rationale:** Already installed and familiar; stronger constraint and indexing support (partial indexes, `jsonb`, row-level security) at no extra cost.

**Alternatives considered:**
- **MySQL 8 (original choice):** Dropped; no capability the product needs that Postgres lacks.

---

### D-022 — React SPA lives inside the Laravel repo (`resources/js`)
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active — amends D-008

**Decision:** One repository. The React 19 SPA is in `resources/js`, built by Vite through `laravel-vite-plugin`. Laravel serves it from a single shell view (`resources/views/app.blade.php`) via a catch-all web route; every other route is `/api/*`. **No Inertia.** The SPA still talks to the API only through REST/JSON with Sanctum bearer tokens, exactly as documented in `08-api.md`.

**Rationale:** One repo, one CI pipeline, one deploy, and no CORS between the SPA and the API (same origin, same wildcard subdomain). The documented API contract is unchanged, so a mobile PWA or denomination portal can still be added later.

**Trade-offs:**
- Frontend and backend now deploy together; a frontend-only fix redeploys the whole app.
- The "no Blade views" rule becomes "no Blade views except the single SPA shell". Controllers still return JSON only.
- CI must run `npm ci && npm run build` and the frontend type-check/tests alongside Pint, PHPStan and Pest/PHPUnit.

**Alternatives considered:**
- **Separate `chms-frontend` repo (D-008):** Dropped for the team size; coordination cost outweighs independent deploys.
- **Inertia.js:** Still rejected; couples page routing to the backend and changes the API contract.

---

### D-023 — Native development, Docker for staging and production
**Date:** October 2026
**Decided by:** Product Owner
**Status:** Active — clarifies D-010

**Decision:** Local development runs natively (PHP, PostgreSQL and Redis installed on the machine; `php artisan serve`, `queue:work`, `npm run dev`). No Docker or Sail locally. Staging and production keep Docker via Coolify as in D-010; the `Dockerfile` and compose/Coolify service definitions live in `deploy/`.

**Rationale:** Faster local loop and no container tooling needed on the dev machine, while production keeps the portable, zero-downtime Coolify setup.

**Consequences:** Local, CI and production must agree on PHP version, PHP extensions (`pdo_pgsql`, `redis`, `bcmath`), PostgreSQL major version and Redis major version. The Dockerfile is the source of truth for the production PHP extension list.

---

### D-024 — API versioned under `/api/v1` from day one
**Date:** October 2026
**Decided by:** Lead Engineer (following the Universal agreement, section 9)
**Status:** Active — amends the unversioned paths in the first draft of `08-api.md`

**Decision:** Every API route is prefixed `/api/v1` (set once via `apiPrefix` in `bootstrap/app.php`). Breaking changes get `/api/v2`; the old version gets `Deprecation` and `Sunset` headers and stays live for its support window.

**Rationale:** Retrofitting a version later breaks every client, including the SPA, the WhatsApp/webhook configuration at providers, and any future mobile PWA. It costs nothing now.

---

### D-025 — OTP sends use a `critical` queue and cost-based rate limits
**Date:** October 2026
**Decided by:** Lead Engineer (following the Universal agreement, sections 16 and 18)
**Status:** Active — amends the queue list in `06-architecture.md` and the OTP limits in `08-api.md` and `15-security.md`

**Decision:**
- A sixth queue, `critical`, carries OTP SMS so a slow broadcast can never delay a sign-in. It is listed first in the worker command.
- Code requests are limited to **1 per minute and 5 per hour per phone per church**, plus 20 per hour per IP. Sending a code costs money, so this is separate from the security limit on verification (5 wrong codes, then a 15-minute lockout; 10 verify calls per minute per IP).
- OTP SMS is not charged to the church's SMS credit balance. The message is stored in `message_logs` with the code redacted.
- Redis keys for codes and attempts are namespaced by church id and use a hash of the phone number.

**Rationale:** The Universal agreement requires cost-triggering endpoints to have their own limit and OTPs to be isolated from slow external-API work. The previous limits (5 a minute, 3 a minute) allowed an attacker to burn SMS budget quickly.

---

### D-026 — Frontend toolchain versions, and the session token lives in memory only
**Date:** October 2026
**Decided by:** Lead Engineer (following the React addendum, section 11)
**Status:** Active — amends the version numbers in D-015 and `06-architecture.md`

**Decision:**
- **Versions actually installed:** React 19.3, React Router **7.18** (not 8), TypeScript **5.9**, Vite **8.3**, Tailwind 4.3, TanStack Query 5, Zustand 5, Vitest 5, ESLint **9**. Vite 8 is required, not chosen: `laravel-vite-plugin` 3 and `@vitejs/plugin-react` 6 both peer-depend on `vite ^8`. ESLint stays on 9 because `eslint-plugin-jsx-a11y` does not support 10 yet. Router and TypeScript are held on the majors the docs name; both have newer majors available.
- ~~The bearer token is kept in memory only.~~ **Superseded by D-027:** the browser app now uses Sanctum cookie sessions, so a refresh no longer signs the user out.
- The phone number typed on the login screen is held in component state, not the URL.
- UI primitives are shadcn/ui (`radix-nova` style, Radix base) generated with the official CLI; its `cn` helper comes from the first-party `cn` package (maintainer shadcn, repo `shadcn-ui/cn`).

**Rationale:** The Universal agreement and the React addendum forbid tokens in browser storage. Cookie-based Sanctum SPA authentication would keep users signed in across refreshes and is possible now that the SPA shares an origin with the API, but it changes the documented auth contract, so it is left as an open option rather than done silently.

**Known audit note:** `npm audit` reports 3 low-severity findings, all in the `shadcn` CLI's own dependencies (a dev tool). CI fails only at `--audit-level=high`.

---

### D-027 — The browser app uses Sanctum cookie sessions; bearer tokens remain for other clients
**Date:** October 2026
**Decided by:** Product Owner (asked that sessions survive a page refresh)
**Status:** Active — supersedes the token-in-memory choice in D-026 and amends `08-api.md` and `15-security.md`

**Decision:**
- A request from the church's own page (its `Origin`/`Referer` host equals the request host) is a **stateful** Sanctum request. Verifying a code signs the user in with a Laravel session and returns **no token**. The session id travels in an httpOnly cookie, so JavaScript never holds a credential and a page refresh keeps the user signed in.
- Any other client (no matching `Origin`/`Referer`) still receives an 8-hour bearer token from the same endpoint, exactly as before.
- `SANCTUM_STATEFUL_DOMAINS` defaults to `Sanctum::currentRequestHost()`, so every church subdomain is stateful without a list. `SESSION_DOMAIN` stays `null`: cookies are **host-only**, so a session never reaches another church's subdomain.
- Sessions last 8 hours of inactivity (`SESSION_LIFETIME=480`, sliding), stored in Redis.
- State-changing requests need CSRF: the SPA calls `GET /sanctum/csrf-cookie` once per page load and axios sends `X-XSRF-TOKEN`.
- `GET /api/v1/auth/me` also returns the church, so the app can restore its state after a refresh.
- `EnsureStaffIsCurrent` runs on every authenticated request: a deactivated user, or a session opened under a different phone number, is signed out immediately (the cookie-session equivalent of deleting tokens).
- `ResolveTenant` is first in the middleware priority list, ahead of Sanctum, and Sanctum's `AuthenticateSession` step is disabled.

**Why the last two matter:** the first build loaded the session user *before* the church was known, so a session cookie from church A replayed on church B's host was accepted. Found by a cross-church test and confirmed against the live server, then fixed and pinned with a test that fails if the fix is removed.

**Consequences:** the web app must run on the same origin as the API (it does, D-022). CORS is not needed for it. A mobile app or third-party client uses the token path.

---

### D-028 — Controller → Form Request → Service; no Action or Repository layers yet
**Date:** October 2026
**Decided by:** Lead Engineer (reconciling the Universal agreement §6 with `docs/09-engineering-principles.md`)
**Status:** Superseded by D-033

**Decision:** Backend code is layered Controller → Form Request → Service → Model, as `docs/09-engineering-principles.md` specifies. Services own `DB::transaction()` boundaries and dispatch jobs and events. We do not add Action classes (one per operation) or Repository classes (all queries for a model) until a real need appears: an Action when one operation is triggered from several entry points, a Repository when a second data source exists. The layers, their duties and their prohibitions are written down in `ARCHITECTURE.md`.

**Rationale:** The project documents name Services explicitly, `09` says interfaces are added only once there are two implementations, and the codebase is small. Extra layers now would be speculative (YAGNI). Eloquent scopes already cover repeated filters.

**Revisit when:** a second client of the same operation appears (for example a CLI command and an endpoint doing the same thing) or a second storage backend is introduced.

---

### D-029 — One image with runtime roles, deployed as three Coolify Applications
**Date:** October 2026
**Decided by:** Lead Engineer (from Coolify and serversideup/php documentation)
**Status:** Active

**Decision:** `deploy/Dockerfile` builds one image on `serversideup/php` (pinned tag). `CONTAINER_ROLE` (`web`, `worker`, `scheduler`) selects the process at start-up. It is deployed as three Coolify Applications, not Docker Compose. Only `web` runs migrations and seeding, from the start script, which always runs `env:check` first. Telescope is registered only in the `local` environment so a `--no-dev` image boots. Logs go to stderr as JSON. Queue workers run under Supervisor inside the `worker` container.

**Rationale:** Coolify does not support rolling updates for Compose applications, and the Dockerfile build pack has no start-command override, so roles must be chosen by environment variable. One image keeps web, worker and scheduler on identical code.

**Revisit when:** Coolify documents rolling updates for Compose, or worker load needs more than one container.

---

### D-030 — Member numbers, change log and pastor edit rights
**Date:** October 2026
**Decided by:** Product Owner (answers given at Sprint 2 planning)
**Status:** Active

**Decision:**
1. **Member number.** The church admin types a short prefix at onboarding, stored as `churches.member_number_prefix`. The number is `{prefix}-{sequence, at least 4 digits}`, for example `GRC-0042`. The sequence is `churches.member_number_seq`, incremented under a row lock inside the create transaction, so numbers are unique, gapless on success and never reused after a delete. Until a prefix is set, adding a member returns `422`.
2. **Change log.** A new append-only `audit_logs` table records creates, updates and status changes on members, with the acting user (name snapshotted), old and new values, the request id and an optional reason. Encrypted fields (address, national id) are recorded by name only, never by value. Written by `AuditLogger` from the service layer, not from model observers, so the actor is always explicit.
3. **Pastor.** The `pastor` role gets `members.edit` (add and edit members), as `docs/02` and `docs/03` specify. Only `church-admin` can deactivate members or export.

**Rationale:** `docs/03` requires an audit trail on phone and status changes and `docs/02` requires a change log, but `docs/07` had no table for it. The seeder and `docs/06` disagreed with `docs/02` and `docs/03` about the pastor.

**Revisit when:** a church needs a different number format, or audit rows need to be shown in the app (Sprint 5).

---

### D-031 — Who can see which members, and who can deactivate
**Date:** October 2026
**Decided by:** Lead Engineer, from `docs/03`; the youth rule was set by the Product Owner
**Status:** Active

**Decision:**
1. **Directory visibility.** `church-admin`, `pastor` and `finance-secretary` see all members. `department-head` sees members of departments where they are `head_user_id`. `youth-pastor` sees youth members. Youth is defined by age (Product Owner, Sprint 2): younger than the church's `settings.youth_age_limit`, default 25 (`config/members.php`). A member with no date of birth falls back to the educational level: anything except `graduate`. The docs said "youth only" without defining it. A user with several of these roles sees the union. Enforced by `Member::scopeVisibleTo` and `MemberPolicy::view`.
2. **Deactivation.** A new permission `members.deactivate`, held only by `church-admin`, is required to move a member to inactive, transferred, deceased or archived. `members.edit` is enough to move to prospect or active. This matches the `docs/03` table (pastors add and edit, only the admin deactivates).
3. **Default directory.** With no status filter and no search, only prospects and active members are listed. Everything else is reachable by search or an explicit status.
4. **Milestones.** A new permission `milestones.view`, held by `church-admin`, `pastor` and `youth-pastor`, controls whether Get Member includes milestones (docs/03: finance secretaries and department heads may not see them). Adding a milestone needs `members.edit`.
5. **Deceased is read-only.** `MemberPolicy::update` refuses a deceased member (docs/03, Stage 4). A status change is still allowed so a mistake can be corrected.

**Rationale:** `docs/03` states these rules in prose and a table, but `docs/08` only mentions `members.view` and `members.edit`.

**Revisit when:** a church needs per-department editing rights.

---

### D-032 — Member photos: processed in the request, stored in the default disk
**Date:** October 2026
**Decided by:** Lead Engineer (reconciling `docs/08` with the "queue every outbound call" rule)
**Status:** Active

**Decision:** A member photo is validated, re-encoded to a 512 pixel JPEG and written to object storage during the upload request, using Laravel's image API (`intervention/image` ^4, GD). Writing to our own private R2 bucket is treated like a database write, not as a third-party API call, so it is not queued. The limits that make this safe are a 5 MB and 4096 pixel cap, a 20 per minute rate limit, and a fixed storage key per member so replacements leave nothing behind.

**Rationale:** `docs/08` returns the new signed link in the upload response, which a queued job cannot do, and the rule against inline outbound calls exists for slow third-party services (SMS, payments), not our own storage. The file-upload pattern prefers a queue; if photo volume or size grows (for example batch imports), move processing to a job and return `202`.

**Revisit when:** uploads are slow on the 3G target, or a bulk import of photos is built.

---

### D-033 — Follow the Universal layer stack in full: Actions, DTOs and Repositories
**Date:** October 2026
**Decided by:** Product Owner (instructed that the code must match the Universal agreement and the Laravel addendum exactly)
**Status:** Active. Supersedes D-028.

**Decision:** Backend code is layered Controller → Form Request → DTO → Action → Service → Repository → Model, as Universal section 6 and the Laravel addendum (sections 3, 5, 6, 7, 11 and 12) describe.
- One `final` Action per user intent in `app/Actions`, with a single `execute(DTO)` method. It owns `DB::transaction()` and fires domain events. An Action never calls another Action.
- Services in `app/Services` hold rules shared by several Actions and never own a transaction.
- Every Eloquent model that Actions touch has a Repository interface and an Eloquent implementation in `app/Repositories`, bound in `RepositoryServiceProvider`. Controllers, Actions and Services do not query models directly.
- Controllers are single-action (`__invoke`) and only build a DTO, call one Action and return a Resource. Form Requests authorise through a Policy.
- Events are fired by Actions (`MemberCreated`, `MemberUpdated`, `MemberStatusChanged`); the audit line is written by a synchronous listener inside the transaction, so a rolled-back change leaves no history.
- Unit tests for Actions and Services use mocked repositories and no database; Feature tests remain for every endpoint.

**Rationale:** D-028 skipped these layers on YAGNI grounds, but the Universal agreement sits at the top of the precedence order and the project documents never explicitly removed them. The addendum's checklist requires Actions to own transactions and fire events, which the earlier Service-only code could not satisfy.

**Revisit when:** a rule in the agreement is changed by the Product Owner.

---

### D-034 — Response labels, formatted money and a log of who views personal data
**Date:** October 2026
**Decided by:** Product Owner (instructed to match the Universal agreement and Laravel addendum exactly)
**Status:** Active

**Decision:**
1. Every enum field in an API response is sent as its value plus a `{field}_label` sibling (addendum section 6).
2. Money is shaped by the `Money` value object, the only place an amount becomes display text. Storage stays `DECIMAL(12,2)` and the raw API value stays a decimal string (D-017), which is the project's explicit override of the agreement's integer minor units; a `_formatted` sibling such as `"GHS 2,400.00"` is added beside it.
3. Opening a member's full record fires `MemberViewed`, and a listener writes a `viewed` line to `audit_logs` naming the viewer and that the encrypted address was shown (agreement section 36: access to PII fields must be logged). The value is never copied into the log. List views do not return the address, so they are not logged.
4. Resources nest other Resources for related records, and every list uses a `ResourceCollection` class (`MemberCollection`, `FamilyCollection`, `DepartmentCollection`).

**Rationale:** The agreement and addendum require each of these. The view log costs one insert per profile open, which is small next to the compliance value.

**Revisit when:** the member profile returns more personal fields, or view volume makes the log too large (then add a retention job, per the agreement's retention table).

---

### D-035 — The OpenAPI spec is generated by Scramble, committed, and checked in CI
**Date:** October 2026
**Decided by:** Product Owner (instructed to match the Universal agreement, section 35)
**Status:** Active

**Decision:** `dedoc/scramble` generates an OpenAPI 3.1 document from the routes, Form Requests, Resources and DTO-typed Actions. It is committed at `docs/openapi.json`. A Pest test and a CI step re-generate it and fail on any difference or on any type Scramble cannot work out (`--fail-on-unknown`), and a test checks that every `/api` route is in it. Routes behind `auth:sanctum` are documented as needing a bearer token and the rest as public. The interactive docs live at `/docs/api` behind the `tenant` middleware and the `viewApiDocs` gate: open in local development, otherwise only for staff with `church.settings`. The spec version is `1.0.0`, matching `/api/v1`, and a breaking change means a new version.

**Rationale:** The agreement requires a committed spec, a CI check, interactive docs behind authentication in production, and says hand-written docs go stale. Generating it from code removes that risk. `docs/08-api.md` stays as the plain-language guide to the conventions.

**Revisit when:** a second API version appears (add a second Scramble API), or contract tests with the React app are added.

---

### D-036 — How the member screens are built
**Date:** October 2026
**Decided by:** Lead Engineer, from the React addendum and `docs/patterns/Pattern — State Management.md`
**Status:** Active

**Decision:**
1. **Filters and the page number live in the URL** (`useMemberFilters`), so a refresh, the back button and a shared link show the same view. Unknown values in the URL are ignored.
2. **Search runs when the user presses Search or Enter**, not on every key press, which is gentler on a slow connection and needs no timer.
3. **Drop-downs are the browser's own `<select>`**, which is the easiest control on a cheap phone and fully accessible. The generated shadcn Select is not used.
4. **Buttons are hidden by permission** (`useCan`) for a cleaner screen; the API still answers 403, which is what actually protects the data.
5. **Money is never calculated in the browser.** `MoneyDisplay` shows the `_formatted` text the API sends.
6. **Backend enums and permissions are mirrored** in `constants/enums.ts` and `constants/permissions.ts`, guarded by a backend test so they cannot drift.
7. **UI parts come from the official shadcn registry** (`npx shadcn add`) and are formatted to the project style. `next-themes`, which the toast wrapper wanted, was dropped because the app has no theme switch.

**Rationale:** Each point follows the React addendum (state in the right place, no business logic in components, no magic strings) and the agreement's accessibility and mobile-first rules.

**Revisit when:** a theme switch is added, or a list gets long enough to need virtualisation.

---

### D-037 — How a church is started
**Date:** October 2026
**Decided by:** Product Owner (phone check and reserved names), Lead Engineer (the rest)
**Status:** Active

**Decision:**
1. **The phone is proven before anything is created.** The person asks for a code on the platform host, then sends the church details with the code; the church and its first admin are created in one transaction only if the code is right. Nothing half-made is ever stored, and no one can hold a web address without owning a phone. (QA-03 TC-03-01 describes creating a "pending" church first; this order replaces it, so there is no pending state to clean up.)
2. **Sign-up codes use the same rules as sign-in codes** (hashed, 10 minutes, 5 tries, 15-minute lockout) under their own scope. They are limited more tightly because they text a stranger: 1 a minute and 3 an hour per phone, 10 an hour per IP.
3. **The web address** is typed or built from the church name. It is lowercase letters, numbers and single hyphens, 3 to 40 characters. Names kept for the platform (`www`, `api`, `admin`, `docs` and others in `config/onboarding.php`) are refused. A taken typed address returns free alternatives and does not use up the code. A deleted church's address stays taken.
4. **The member number prefix** (2 to 6 letters) is asked at sign-up, as D-030 decided.
5. **After creating the church the admin signs in on the new address with a new code**, because a session cookie belongs to one host (D-027). Handing a session from the platform host to the church host would need a one-time link; it is left out until it is wanted. (QA-03 TC-03-01 expects the admin to be signed in straight away.)
6. **The sign-up code's text message is logged with no church**, so `message_logs.church_id` is now nullable. Rolling that migration back deletes those rows.
7. **Left for later stories:** the bank or MoMo payout details and Paystack subaccount (story 4.1, QA-03 TC-03-05), inviting a second admin (story 5.15; the sign-up page only recommends it, as TC-03-06 allows), and a welcome email (the platform has no email sender yet).

**Rationale:** The agreement requires provisioning to be atomic (section 34) and every cost-triggering endpoint to be separately rate limited (section 16). Proving the phone first keeps abusive sign-ups cheap to refuse.

**Revisit when:** a one-time sign-in link is wanted, or churches can be started by the platform's own staff.

---

### D-038 — PayPerlez sends our SMS; we keep making our own codes
**Date:** October 2026
**Decided by:** Lead Engineer, from the PayPerlez SMS documentation
**Status:** Active

**Decision:**
1. **`SMS_PRIMARY_PROVIDER=payperlez` sends every text through `POST /sms/messages`** (docs/14, section 7). The provider interface now takes the whole message and returns a receipt, so the message log keeps PayPerlez's reference and the safe parts of its reply.
2. **Every message has one retry key**, made when the job is created and kept across retries, so a retry after a timeout can never send a second text.
3. **Temporary failures retry; refusals do not.** A rate limit, a server error or a lost connection retries with backoff. Any other refusal (unapproved sender, no credit, bad request) fails the job at once and is logged with PayPerlez's error code and request id only.
4. **Sign-in and sign-up codes stay ours.** PayPerlez's own OTP product is not used, so sign-in is not tied to one provider and the code rules (10 minutes, 5 tries, lockout) stay the same whichever provider sends the text.
5. **The app refuses to start** with this provider unless the API key and an approved sender id are set (`env:check` and the provider binding both say so).

**Rationale:** The agreement requires every provider to sit behind an interface, retries to be safe, and failures to be normalised at the driver boundary.

**Revisit when:** PayPerlez publishes SMS delivery webhooks (replace polling), or a second SMS provider is added (Africa's Talking is the planned fallback).

---

## Deferred Decisions

Decisions that were intentionally not made yet, and when they need to be made.

| # | Decision | Needed By | Owner |
|---|---|---|---|
| DD-001 | Product name | Before Sprint 1 | Product Owner |
| DD-002 | Primary domain | Same as DD-001 | Product Owner |
| DD-003 | Exact platform giving fee % | Sprint 3 | Product Owner |
| DD-004 | Exact SMS credits per plan tier | Sprint 3 | Product Owner |
| DD-005 | Exact GHS subscription prices | Sprint 4 | Product Owner |
| DD-006 | Custom sender IDs for churches (v2 feature or not?) | Sprint 2 | Product Owner |
