# Data Model

> Every table, every column, every relationship. This is the ground truth for the database. Migrations must match this document. If code and docs disagree, the docs are wrong — fix them.

---

## Conventions

- All primary keys are `CHAR(26)` ULIDs (`id`) — the only identifier. No auto-increment integers anywhere, so there is nothing guessable to leak. See D-016 in `13-decision-log.md`.
  - Laravel: `HasUlids` trait on every model; migrations use `$table->ulid('id')->primary()` and `$table->foreignUlid(...)`. Polymorphic tables (Sanctum tokens, Spatie pivots) use `ulidMorphs()`.
  - ULIDs are time-ordered, so inserts append to the clustered index (no UUID v4 fragmentation). Laravel generates them lowercase; store and compare them as-is.
  - Validate incoming IDs with Laravel's `ulid` rule.
- All foreign keys reference `id` on the parent table and use `ON DELETE RESTRICT` unless stated otherwise.
- All timestamps: `created_at`, `updated_at` (Laravel defaults). Soft deletes use `deleted_at` where applicable.
- Monetary amounts: `DECIMAL(12, 2)` — supports up to GHS 9,999,999,999.99. Always stored in GHS. See D-017.
- No native MySQL `ENUM` columns. Fixed-value fields are `VARCHAR(30)` columns cast to a PHP backed enum in `app/Enums/` (the class is named in the column's Notes). Adding a value is then a code change, not a schema migration. Enum values are lowercase snake_case strings; enum cases are TitleCase. Validate input with `Rule::enum(...)`.
- Phone numbers: `VARCHAR(20)`, stored in international format (`+233244123456`).
- Boolean columns: native PostgreSQL `boolean` via Laravel's `boolean()` method.
- Database: PostgreSQL (D-021). `JSON` columns are `jsonb`; "unique where not null" means a partial unique index; unique keys on soft-deleted tables use `WHERE deleted_at IS NULL`. Types below written MySQL-style (`INT(11)`, `TIMESTAMP`, `CHAR(26)`) map to Laravel's `integer()`, `timestamp()` and `ulid()` — write migrations with the Laravel schema builder, not raw DDL.
- Text fields containing PII that must be encrypted: marked with `[encrypted]`.
- All `church_id` foreign keys include an index for tenant-scoped query performance.

---

## Entity Relationship Overview

```
churches
  └── users (staff accounts)
  └── members
        └── families (member groups)
        └── member_milestones
        └── member_departments (pivot)
  └── departments
  └── services
        └── attendance_records (members ↔ services)
  └── fund_types
  └── contributions
        └── pledges
        └── disbursements (automatic payout of each digital gift)
  └── small_groups (v2)
        └── small_group_members (pivot, v2)
  └── message_logs
  └── webhook_logs
  └── sms_credit_ledger
  └── visitor_records
  └── audit_logs
```

---

## Tables

### `churches`

The root tenant record. Every other table references this.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `name` | VARCHAR(255) | No | Full church name |
| `slug` | VARCHAR(100) | No | Subdomain slug. Unique. Lowercase, hyphenated. |
| `custom_domain` | VARCHAR(255) | Yes | e.g. `portal.graceassembly.org.gh`. Unique where set. |
| `denomination` | VARCHAR(255) | Yes | e.g. "Assemblies of God", "Presbyterian" |
| `address` | TEXT | Yes | Physical address |
| `city` | VARCHAR(100) | Yes | |
| `region` | VARCHAR(100) | Yes | Ghana region |
| `phone` | VARCHAR(20) | Yes | Church office phone |
| `email` | VARCHAR(255) | Yes | Church admin email |
| `logo_url` | VARCHAR(500) | Yes | R2 path |
| `plan` | VARCHAR(30) | No | `ChurchPlan`: starter, growth, pro, enterprise. Default: `starter` |
| `plan_expires_at` | TIMESTAMP | Yes | Null = active (annual billing), set = expires |
| `paystack_subaccount_code` | VARCHAR(100) | Yes | Set during onboarding. Used for card payments only (Paystack split). |
| `payout_method` | VARCHAR(30) | Yes | `PayoutMethod`: momo, bank. Where automatic disbursements are sent. Null = digital giving not yet enabled. |
| `payout_network` | VARCHAR(30) | Yes | `MomoNetwork`: mtn, telecel, at (PayPerlez's code for AirtelTigo). Set when `payout_method` = momo. |
| `payout_account_number` | VARCHAR(50) | Yes | `[encrypted]` MoMo number or bank account number |
| `payout_account_name` | VARCHAR(255) | Yes | Account holder name as verified by the provider |
| `payout_verified_at` | TIMESTAMP | Yes | Set when the provider confirms the account. Digital giving stays disabled until set. |
| `sms_credits` | INT(11) | No | Current credit balance. Default: 0 |
| `whatsapp_opted_in` | BOOLEAN | No | Default: false. Set when Meta account connected. |
| `ussd_code` | VARCHAR(10) | Yes | Unique 4-digit church code for USSD giving (e.g. `1042` → dial `*713*1042#`). Auto-generated on onboarding. |
| `timezone` | VARCHAR(50) | No | Default: `Africa/Accra` |
| `member_number_prefix` | VARCHAR(10) | Yes | Letters in front of every member number (the `GRC` in `GRC-0042`). Typed by the admin at onboarding. Null until set; adding a member fails until it is (D-030). |
| `member_number_seq` | INT | No | Last member number handed out. Default: 0. Only incremented under a row lock, never reset, so a number is never reused (D-030). |
| `settings` | JSON | Yes | Configurable per-church settings (absence threshold, receipt preferences, etc.) |
| `is_active` | BOOLEAN | No | Default: true. False = suspended. |
| `created_at` | TIMESTAMP | No | |
| `updated_at` | TIMESTAMP | No | |

**Indexes:** `slug` (unique), `custom_domain` (unique, partial — where not null)

---

### `users`

Staff accounts. One user belongs to one church. Members are a separate table.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `name` | VARCHAR(255) | No | |
| `phone` | VARCHAR(20) | No | Used for OTP login |
| `email` | VARCHAR(255) | Yes | For system notifications |
| `is_active` | BOOLEAN | No | Default: true |
| `last_login_at` | TIMESTAMP | Yes | |
| `created_at` | TIMESTAMP | No | |
| `updated_at` | TIMESTAMP | No | |

**Indexes:** `(church_id, phone)` unique — no duplicate phone per church

**Spatie tables (auto-created):** `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

---

### `members`

The core entity. Represents every person the church knows about who has reached Active Member status.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `member_number` | VARCHAR(20) | No | Human-readable ID e.g. `GRC-0042`. Unique per church. |
| `first_name` | VARCHAR(100) | No | |
| `middle_name` | VARCHAR(100) | Yes | |
| `last_name` | VARCHAR(100) | No | |
| `preferred_name` | VARCHAR(100) | Yes | "Known as" |
| `gender` | VARCHAR(30) | No | `Gender`: male, female, other. |
| `date_of_birth` | DATE | Yes | |
| `phone` | VARCHAR(20) | No | Primary — used for OTP |
| `phone_secondary` | VARCHAR(20) | Yes | |
| `email` | VARCHAR(255) | Yes | |
| `address` | TEXT | Yes | `[encrypted]` |
| `nearest_landmark` | VARCHAR(255) | Yes | |
| `digital_address` | VARCHAR(20) | Yes | Ghana Post GPS code |
| `photo_url` | VARCHAR(500) | Yes | R2 path |
| `national_id_type` | VARCHAR(50) | Yes | e.g. "Ghana Card", "Voter ID" |
| `national_id_number` | VARCHAR(50) | Yes | `[encrypted]` |
| `marital_status` | VARCHAR(30) | Yes | `MaritalStatus`: single, married, widowed, divorced. |
| `occupation` | VARCHAR(255) | Yes | |
| `tribe` | VARCHAR(100) | Yes | |
| `language_preference` | VARCHAR(50) | Yes | e.g. "Twi", "Ga", "English" |
| `educational_level` | VARCHAR(30) | Yes | `EducationalLevel`: creche, kg, primary_1, primary_2, primary_3, primary_4, primary_5, primary_6, jhs_1, jhs_2, jhs_3, shs_1, shs_2, shs_3, tertiary, graduate. Null for adults where not tracked |
| `membership_type` | VARCHAR(30) | No | `MembershipType`: full, associate, affiliate. Default: `full` |
| `membership_date` | DATE | Yes | Date formally received into membership |
| `status` | VARCHAR(30) | No | `MemberStatus`: prospect, active, inactive, transferred, deceased, archived. Default: `active` |
| `status_changed_at` | TIMESTAMP | Yes | When status last changed |
| `status_note` | TEXT | Yes | Reason for status change |
| `family_id` | CHAR(26) | Yes | FK → families |
| `family_role` | VARCHAR(30) | Yes | `FamilyRole`: head, spouse, child, dependent. Role within the family |
| `assigned_pastor_id` | CHAR(26) | Yes | FK → users. The pastor responsible for this member. |
| `whatsapp_opted_in` | BOOLEAN | No | Default: false |
| `sms_opted_in` | BOOLEAN | No | Default: true |
| `created_by` | CHAR(26) | No | FK → users |
| `deleted_at` | TIMESTAMP | Yes | Soft delete (Laravel SoftDeletes) |
| `created_at` | TIMESTAMP | No | |
| `updated_at` | TIMESTAMP | No | |

**Indexes:**
- `(church_id, phone)` — unique
- `(church_id, member_number)` — unique
- `(church_id, status)` — for active member counts
- `(church_id, family_id)` — for family queries
- `(church_id, last_name, first_name)` — for directory search

---

### `families`

Groups members into family units.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `name` | VARCHAR(255) | No | e.g. "Mensah-Boateng Family" |
| `head_member_id` | CHAR(26) | Yes | FK → members. Head of household. |
| `notes` | TEXT | Yes | |
| `created_at` | TIMESTAMP | No | |
| `updated_at` | TIMESTAMP | No | |

**Index:** `(church_id)`

---

### `member_milestones`

Tracks spiritual milestones for members (dedications, baptisms, confirmation, etc.).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `member_id` | CHAR(26) | No | FK → members |
| `type` | VARCHAR(30) | No | `MilestoneType`: dedication, baptism, confirmation, first_communion, membership_class. |
| `occurred_at` | DATE | No | Date of milestone |
| `officiant` | VARCHAR(255) | Yes | Pastor/minister who officiated |
| `cohort` | VARCHAR(100) | Yes | e.g. "Confirmation Class 2024" |
| `notes` | TEXT | Yes | |
| `created_by` | CHAR(26) | No | FK → users |
| `created_at` | TIMESTAMP | No | |
| `updated_at` | TIMESTAMP | No | |

**Index:** `(church_id, member_id, type)`

---

### `departments`

Ministry departments within a church (choir, ushers, youth, etc.).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `name` | VARCHAR(255) | No | e.g. "Choir", "Ushering Ministry" |
| `description` | TEXT | Yes | |
| `head_user_id` | CHAR(26) | Yes | FK → users. The department head. |
| `is_active` | BOOLEAN | No | Default: true |
| `created_at` | TIMESTAMP | No | |
| `updated_at` | TIMESTAMP | No | |

---

### `member_departments` (Pivot)

Many-to-many between members and departments.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `member_id` | CHAR(26) | No | FK → members |
| `department_id` | CHAR(26) | No | FK → departments |
| `role` | VARCHAR(100) | Yes | e.g. "Leader", "Member", "Secretary" |
| `joined_at` | DATE | Yes | |
| `created_at` | TIMESTAMP | No | |

**Index:** `(church_id, member_id, department_id)` — unique

---

### `services`

Every church service or event that attendance can be recorded for.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `name` | VARCHAR(255) | No | e.g. "Sunday Morning Service", "Midweek Prayer" |
| `type` | VARCHAR(30) | No | `ServiceType`: sunday, midweek, special, youth, children. |
| `checkin_code` | VARCHAR(10) | Yes | Short code for WhatsApp CHECKIN. Unique per church. |
| `service_date` | DATE | No | |
| `starts_at` | TIME | Yes | |
| `ends_at` | TIME | Yes | |
| `is_open` | BOOLEAN | No | Default: false. True = check-in active. |
| `headcount_override` | INT | Yes | Manual count (e.g. paper count) if digital check-in not used |
| `notes` | TEXT | Yes | |
| `created_by` | CHAR(26) | No | FK → users |
| `created_at` | TIMESTAMP | No | |
| `updated_at` | TIMESTAMP | No | |

**Index:** `(church_id, service_date)`, `(church_id, checkin_code)` — unique

---

### `attendance_records`

One row per member per service.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `service_id` | CHAR(26) | No | FK → services |
| `member_id` | CHAR(26) | Yes | Null = anonymous (visitor walk-in) |
| `visitor_name` | VARCHAR(255) | Yes | For non-member attendees |
| `method` | VARCHAR(30) | No | `CheckInMethod`: manual, whatsapp, qr, biometric. How they checked in |
| `checked_in_at` | TIMESTAMP | No | |
| `created_at` | TIMESTAMP | No | |

**Index:** `(church_id, service_id, member_id)` — unique (prevents double check-in)

---

### `fund_types`

Configurable per church. Controls the categories contributions can be assigned to.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `name` | VARCHAR(100) | No | e.g. "Tithe", "Building Fund" |
| `description` | TEXT | Yes | |
| `is_active` | BOOLEAN | No | Default: true |
| `sort_order` | INT | No | Display ordering. Default: 0. |
| `created_at` | TIMESTAMP | No | |
| `updated_at` | TIMESTAMP | No | |

**Index:** `(church_id, is_active)`

---

### `contributions`

Every giving transaction — MoMo, card, or cash.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `member_id` | CHAR(26) | Yes | Null = anonymous or visitor |
| `service_id` | CHAR(26) | Yes | Service at which it was given |
| `fund_type_id` | CHAR(26) | No | FK → fund_types |
| `pledge_id` | CHAR(26) | Yes | FK → pledges. Set if fulfilling a pledge. |
| `amount` | DECIMAL(12,2) | No | GHS. The **gift amount** the church is credited with (what the giver chose). Excludes the processing fee. |
| `processing_fee` | DECIMAL(12,2) | Yes | GHS collection fee the giver paid on top (D-018). Null for cash and until the provider reports it. |
| `total_paid` | DECIMAL(12,2) | Yes | GHS the giver actually paid: `amount + processing_fee`. Null for cash. |
| `payment_method` | VARCHAR(30) | No | `PaymentMethod`: momo, card, cash, ussd. |
| `paystack_reference` | VARCHAR(100) | Yes | Unique Paystack transaction ref. Null for non-Paystack payments. |
| `paystack_status` | VARCHAR(50) | Yes | `success`, `failed`. Null for non-Paystack payments. |
| `payperlez_reference` | VARCHAR(100) | Yes | Unique PayPerlez transaction ref. Null unless processed via PayPerlez. |
| `ussd_reference` | VARCHAR(100) | Yes | Unique reference from USSD provider (Moolre). Null unless payment_method = ussd. |
| `is_voided` | BOOLEAN | No | Default: false |
| `voided_at` | TIMESTAMP | Yes | |
| `voided_by` | CHAR(26) | Yes | FK → users |
| `void_reason` | TEXT | Yes | |
| `recorded_by` | CHAR(26) | No | FK → users |
| `notes` | TEXT | Yes | |
| `contributed_at` | TIMESTAMP | No | When the giving occurred |
| `created_at` | TIMESTAMP | No | When the record was created |
| `updated_at` | TIMESTAMP | No | |

**Indexes:**
- `paystack_reference` — unique (where not null) — idempotency
- `payperlez_reference` — unique (where not null) — idempotency for PayPerlez webhooks
- `ussd_reference` — unique (where not null) — idempotency for USSD webhooks
- `(church_id, member_id)` — for member giving history
- `(church_id, service_id)` — for service offering reports
- `(church_id, contributed_at)` — for date-range financial reports
- `(church_id, fund_type_id, contributed_at)` — for fund-type reports

---

### `pledges`

Commitments from members to give a specific amount by a target date.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `member_id` | CHAR(26) | No | FK → members |
| `fund_type_id` | CHAR(26) | No | FK → fund_types |
| `description` | VARCHAR(255) | No | e.g. "Building Fund 2026 Pledge" |
| `amount_pledged` | DECIMAL(12,2) | No | GHS |
| `amount_paid` | DECIMAL(12,2) | No | GHS. Updated by trigger/observer on contribution. Default: 0. |
| `target_date` | DATE | No | |
| `status` | VARCHAR(30) | No | `PledgeStatus`: active, fulfilled, partial, lapsed. Default: `active` |
| `created_by` | CHAR(26) | No | FK → users |
| `created_at` | TIMESTAMP | No | |
| `updated_at` | TIMESTAMP | No | |

**Computed:** `amount_outstanding = amount_pledged - amount_paid` — calculated in application, not stored.

**Index:** `(church_id, member_id, status)`, `(church_id, target_date)`

---

### `disbursements`

One row per digital (MoMo/USSD) contribution that is automatically paid out to the church. Card payments settle through Paystack and have no row here. See D-018.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `contribution_id` | CHAR(26) | No | FK → contributions. **Unique** — one payout per contribution (idempotency). |
| `gross_amount` | DECIMAL(12,2) | No | GHS collected |
| `platform_fee` | DECIMAL(12,2) | No | GHS kept by the platform |
| `requested_amount` | DECIMAL(12,2) | No | GHS we ask the provider to send: `gross_amount − platform_fee`. The provider deducts its own fee from this (the church bears it). |
| `provider_fee` | DECIMAL(12,2) | Yes | GHS charged by the payout provider. Null until the provider reports it. |
| `net_amount` | DECIMAL(12,2) | Yes | GHS actually delivered to the church: `requested_amount − provider_fee`. Null until the payout is confirmed. |
| `status` | VARCHAR(30) | No | `DisbursementStatus`: pending, processing, paid, failed. Default: `pending` |
| `provider` | VARCHAR(30) | No | `PaymentProviderName`: payperlez, moolre |
| `provider_reference` | VARCHAR(100) | Yes | Unique payout reference from the provider |
| `attempts` | INT | No | Default: 0 |
| `failure_reason` | TEXT | Yes | Last provider error |
| `paid_at` | TIMESTAMP | Yes | |
| `created_at` | TIMESTAMP | No | |
| `updated_at` | TIMESTAMP | No | |

**Indexes:** `contribution_id` (unique), `provider_reference` (unique where not null), `(church_id, status)` — for the stuck-funds view, `(status, created_at)` — for the retry and reconciliation jobs.

---

### `visitor_records`

First-time visitors who haven't yet become members.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `name` | VARCHAR(255) | No | |
| `phone` | VARCHAR(20) | Yes | |
| `how_heard` | VARCHAR(255) | Yes | e.g. "Friend", "Facebook", "Signboard" |
| `first_visit_service_id` | CHAR(26) | Yes | FK → services |
| `first_visit_date` | DATE | No | |
| `visit_count` | INT | No | Default: 1. Incremented on each return. |
| `last_visit_date` | DATE | Yes | |
| `follow_up_status` | VARCHAR(30) | No | `FollowUpStatus`: pending, contacted, converted, lost. Default: `pending` |
| `converted_member_id` | CHAR(26) | Yes | FK → members. Set when visitor becomes a member. |
| `notes` | TEXT | Yes | |
| `assigned_to` | CHAR(26) | Yes | FK → users. Staff following up. |
| `created_at` | TIMESTAMP | No | |
| `updated_at` | TIMESTAMP | No | |

---

### `message_logs`

Every outbound SMS, WhatsApp, or email sent by the system.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `channel` | VARCHAR(30) | No | `MessageChannel`: sms, whatsapp, email. |
| `recipient_type` | VARCHAR(30) | No | `RecipientType`: member, user, visitor. |
| `recipient_id` | CHAR(26) | Yes | FK to the relevant table |
| `recipient_phone` | VARCHAR(20) | Yes | |
| `recipient_email` | VARCHAR(255) | Yes | |
| `message_type` | VARCHAR(100) | No | e.g. `otp`, `receipt`, `broadcast`, `attendance_alert` |
| `content` | TEXT | No | The actual message sent |
| `status` | VARCHAR(30) | No | `MessageStatus`: queued, sent, delivered, failed. Default: `queued` |
| `provider_reference` | VARCHAR(255) | Yes | AT message ID or Meta message ID |
| `provider_response` | JSON | Yes | Raw API response |
| `cost_units` | DECIMAL(8,4) | Yes | SMS units consumed |
| `sent_at` | TIMESTAMP | Yes | |
| `created_at` | TIMESTAMP | No | |
| `updated_at` | TIMESTAMP | No | |

**Index:** `(church_id, message_type, created_at)`, `(church_id, status)`

---

### `sms_credit_ledger`

Audit trail for every SMS credit movement (allocation, consumption, top-up).

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `type` | VARCHAR(30) | No | `CreditLedgerType`: allocation, topup, consumption, refund. |
| `credits` | INT | No | Positive = credits added, negative = credits consumed |
| `balance_after` | INT | No | Running balance after this transaction |
| `reference_id` | CHAR(26) | Yes | Related model ID (message_log, contribution, etc.) |
| `note` | VARCHAR(255) | Yes | |
| `created_at` | TIMESTAMP | No | |

**Index:** `(church_id, created_at)`

---

### `webhook_logs`

Raw incoming webhook payloads. Never lose a webhook event.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `source` | VARCHAR(30) | No | `WebhookSource`: paystack, payperlez, meta_whatsapp. (Add `moolre` only if USSD is built, D-019.) |
| `event` | VARCHAR(100) | No | e.g. `charge.success` |
| `payload` | TEXT | No | `[encrypted]` Raw JSON payload |
| `processed` | BOOLEAN | No | Default: false |
| `processed_at` | TIMESTAMP | Yes | |
| `error` | TEXT | Yes | Set if processing failed |
| `created_at` | TIMESTAMP | No | |

**Index:** `(source, event, processed)`, `(created_at)` — for pruning

**Retention:** 90 days (pruned by scheduled command)

---

### `audit_logs`

Append-only change history (`patterns/Pattern — Audit Trails.md`, D-030). Written once, never updated or deleted, so it has no `updated_at` and no `deleted_at`.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | CHAR(26) | No | ULID PK |
| `church_id` | CHAR(26) | No | FK → churches |
| `auditable_type` | VARCHAR | No | Model class, for example `App\Models\Member` |
| `auditable_id` | CHAR(26) | No | ULID of the changed record |
| `event` | VARCHAR(30) | No | `AuditEvent`: created, updated, status_changed, viewed. |
| `old_values` | JSONB | Yes | Previous values of the changed fields. Null for a create. Encrypted fields are never copied here. |
| `new_values` | JSONB | Yes | New values of the changed fields |
| `changed_fields` | JSONB | Yes | Names of the fields that changed |
| `actor_id` | CHAR(26) | Yes | FK → users. Null for a system change. |
| `actor_name` | VARCHAR(255) | Yes | Snapshot of the name at the time, not a join |
| `request_id` | VARCHAR(64) | Yes | Same id as `X-Request-ID` and the application log |
| `reason` | TEXT | Yes | For example the note on a status change |
| `created_at` | TIMESTAMP | No | |

**Indexes:** `(auditable_type, auditable_id)`, `(church_id, created_at)`, `(actor_id, created_at)`

---

## Key Relationships Summary

```
Church
├── hasMany Users
├── hasMany Members
│     ├── belongsTo Family
│     ├── hasMany Milestones
│     ├── belongsToMany Departments (via member_departments)
│     ├── hasMany AttendanceRecords
│     └── hasMany Contributions
│           ├── belongsTo Pledge
│           └── hasOne Disbursement
├── hasMany Departments
├── hasMany FundTypes
├── hasMany Services
│     └── hasMany AttendanceRecords
├── hasMany Pledges
├── hasMany VisitorRecords
├── hasMany MessageLogs
└── hasMany SmsCreditLedger entries
```

---

## Migration Order

Migrations must be run in this order to satisfy foreign key constraints:

1. `churches`
2. `departments` (no FKs beyond churches)
3. `users`
4. `families` (before members — member has FK to family)
5. `members`
6. `member_departments` (pivot)
7. `member_milestones`
8. `services`
9. `attendance_records`
10. `fund_types`
11. `pledges`
12. `contributions`
13. `disbursements` (after contributions)
14. `visitor_records`
15. `message_logs`
16. `sms_credit_ledger`
17. `webhook_logs`
18. `audit_logs` (after `users`; added in Sprint 2)
19. Spatie permission tables (via `php artisan vendor:publish`)

---

## Related Documents

- [`06-architecture.md`](./06-architecture.md) — how the data layer fits into the system
- [`08-api.md`](./08-api.md) — API resources that map to these tables
- [`15-security.md`](./15-security.md) — encryption details for `[encrypted]` fields
