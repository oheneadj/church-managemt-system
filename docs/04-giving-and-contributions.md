# Giving & Contributions

> The giving module is the most financially sensitive part of the system. It must be accurate, auditable, and trustworthy. A church admin who can't trust the numbers will leave the platform. A church that catches a discrepancy they can't explain will leave loudly.

---

## Overview

"Giving" in a Ghanaian church context is broader than a single weekly offering. It includes:

- **Sunday offering** — general offering taken at a service
- **Tithe** — 10% of income, given by committed members
- **Pledges** — commitments to give a specific amount by a target date (building fund, anniversary, harvest)
- **Special offerings** — directed giving for a specific cause (benevolence, missions, pastor's appreciation)
- **Welfare / benevolence** — contributions earmarked to support members in need

Each of these has its own tracking requirements. The system handles all of them through a unified contribution record with a **fund type** field.

---

## Payment Methods

### Mobile Money (MoMo)
The primary digital payment method in Ghana. Supported networks via Paystack:

| Network | Product Name |
|---|---|
| MTN | MTN MoMo |
| Vodafone / Telecel | Telecel Cash |
| AirtelTigo | AirtelTigo Money |

**Flow:**
1. Finance secretary or member initiates a contribution
2. System generates a Paystack charge request with the member's phone number and amount
3. Member receives a USSD prompt on their phone → approves the transaction
4. Paystack webhook fires → system creates the contribution record automatically
5. Confirmation SMS sent to member

No card details or MoMo PINs are ever handled by the system — Paystack manages the entire payment flow.

### USSD Giving

> **Deferred past the MVP (D-019).** The flow below is a reference design only; nothing in v1 implements it. The Moolre details are unverified and parts of this section (for example "verifies the signature" and "no platform fee") are out of date: Moolre uses a body secret plus IP allowlist, and any future USSD gift would need to follow D-018's fee and payout rules.
Works on any phone — no internet, no smartphone, no MoMo app required. Members dial a shortcode to give.

**Flow:**
1. Member dials `*713*[churchcode]#` (e.g. `*713*1042#` for Grace Assembly)
2. USSD menu shown: select fund type → enter amount → confirm
3. USSD provider (Moolre) sends a webhook to the platform
4. `ProcessUssdWebhookJob` verifies the signature, looks up the church by `ussd_code`, matches the member by phone number
5. Contribution record created with `payment_method: ussd`
6. `ContributionReceived` event fires → SMS receipt sent to member

**Anonymous USSD giving:** If the member's phone number is not in the system, the contribution is recorded as anonymous (no member_id). The amount and fund type are still captured.

**Idempotency:** `ussd_reference` from the provider is checked before creating any record — duplicate webhooks are silently discarded.

**Provider:** Currently Moolre. Switching providers requires updating only the signature verification middleware and the webhook job — the contribution record, event, and receipt flow are unchanged.

**Platform fee:** USSD giving does not go through Paystack. No platform fee applies. The money moves via the USSD provider's settlement directly to the church.

### Card (Debit / Credit)
Supported via Paystack's card processing. Member or admin initiates, Paystack handles. Useful for members in the diaspora who want to give remotely.

### Cash
The most common method for most churches. The finance secretary records:
- Amount
- Fund type
- Member (or marks as anonymous/visitor)
- Service/date
- Notes (optional)

Cash records are manual entry only — there is no automatic verification. The integrity of cash records depends on the finance secretary.

**Cash recording rule:** Cash contributions must be recorded on the same day they are collected. The system timestamps every record and flags same-week edits to cash records for review.

---

## Platform Fee

Every digital contribution (MoMo, card or USSD) carries a platform fee. How it is collected depends on the payment path (see D-018):

**MoMo (PayPerlez) — automatic disbursement** (USSD via Moolre would follow the same flow if added later, D-019):
1. The gift settles to the platform's provider account (these providers credit collections immediately).
2. The success webhook creates the `contribution`, calculates the fee, and creates a `disbursements` record.
3. A payout job on the `payments` queue immediately sends the net amount (gross − platform fee − provider transfer fee, see D-018) to the church's registered MoMo wallet or bank account.
4. The payout provider's webhook marks the disbursement `paid`. A failed payout is retried, then raised as an alert, because until it succeeds the church's money is sitting in the platform account.

The platform never keeps the church's share and does not wait for a batch: payout is automatic and per transaction.

**Card (Paystack) — subaccount split:**
Paystack settles the next business day, so card payments cannot be paid out instantly. Each church keeps a Paystack subaccount; the platform's cut is split off at charge time and the church's share is settled by Paystack directly. No disbursement record is created for these.

**How the platform fee is calculated (MoMo/USSD):**
- The fee is calculated on every transaction: `platform_fee = clamp(round(gross × rate, 2), floor, cap)`, using decimal arithmetic (never floats) and half-up rounding to two decimals.
- Values (D-018): **rate 1%**, **cap GHS 10.00**, **floor GHS 0.10**. Minimum digital gift **GHS 1.00**; maximum GHS 30,000 per transaction.
- These live in platform configuration (`giving.platform_fee_rate`, `giving.platform_fee_cap`, `giving.platform_fee_floor`, `giving.min_digital_gift`), not hard-coded, so they can change without a deploy. Each `disbursements` row stores the fee actually charged, so changing the config never alters past records.
- The cap and floor apply to the platform's fee only, not to the payment provider's fees.
- The church bears the transaction fees: the payout provider's transfer fee is deducted from what the church receives, in addition to the platform fee.
- We ask the provider to send `gross − platform_fee`; the church receives that less the provider's fee.

**No refunds after payout:** once the money has been paid out to the church, the platform does not issue or fund refunds or chargebacks. Any refund is between the church and the person who gave. The platform has no clawback.

**Transaction limit:** a single digital (MoMo/USSD) gift is limited to GHS 30,000. There is no daily limit.

**What the giver sees (D-018, all digital payments):** the processing fee is shown openly and paid by the giver on top of the gift. For a GHS 100 gift the giver sees "Gift GHS 100.00 + processing fee GHS 0.80 = GHS 100.80". The church always receives the full gift amount (less only the platform fee and its own payout fee), never a reduced one.
- The provider's collection fee is only known exactly once the payment settles, so the giving screen shows an estimate (once we have the live fee schedule) and the **receipt always shows the exact fee and total paid**.
- The minimum gift (GHS 1.00) and maximum (GHS 30,000) apply to the **gift amount**, not the total including the fee.
- The same rule applies to card payments through Paystack; the fee mechanics for cards will be designed when cards are built.

Both paths are disclosed to every church during onboarding. It is not a hidden charge.

**Cash contributions have no platform fee** — they are free to record, because we're not processing the transaction.

---

## Fund Types

Fund types are configurable per church. The system ships with sensible defaults; the admin can rename, add, or deactivate them.

**Default fund types:**
- Tithe
- Sunday Offering
- Building Fund
- Pledge (links to a specific pledge record)
- Benevolence
- Missions
- Special Offering
- Other

---

## Contribution Record

Every contribution — whether MoMo, card, or cash — creates a single `contributions` record:

| Field | Type | Notes |
|---|---|---|
| `id` | ULID | Primary key |
| `church_id` | Foreign key | Multi-tenancy scoping |
| `member_id` | Foreign key (nullable) | Null = anonymous or visitor |
| `service_id` | Foreign key (nullable) | Which service it was given at |
| `amount` | Decimal (10, 2) | Always in GHS |
| `fund_type` | Enum / FK | Tithe, Offering, Pledge, etc. |
| `payment_method` | Enum | `momo`, `card`, `cash`, `ussd` |
| `paystack_reference` | String (nullable) | For Paystack MoMo/card; null otherwise |
| `payperlez_reference` | String (nullable) | For PayPerlez collections; null otherwise |
| `ussd_reference` | String (nullable) | For USSD giving (Moolre); null for all other methods |
| `pledge_id` | Foreign key (nullable) | If this contribution fulfils a pledge |
| `recorded_by` | Foreign key (users) | Who entered / confirmed the record |
| `notes` | Text (nullable) | Optional free-text |
| `contributed_at` | Datetime | When the contribution occurred |
| `created_at` | Datetime | When the record was created |

**Critical rules:**
- `amount` is always stored in GHS (no currency conversion, ever)
- Contribution records are **never deleted** — only voided (see Corrections below)
- `contributed_at` and `created_at` are separate fields — a cash record entered Monday for Sunday's collection has different values for both

---

## Pledge Tracking

A pledge is a commitment from a member to give a specific amount by a target date.

**Pledge record:**
- Member
- Description ("Building Fund 2026 Pledge")
- Total pledged amount
- Target date
- Status: `active`, `fulfilled`, `partial`, `lapsed`

As contributions linked to a pledge are recorded, the system tracks:
- Amount paid to date
- Outstanding balance
- Progress percentage

When the outstanding balance reaches zero before the target date, the pledge status auto-updates to `fulfilled`.

**Pledge reports:**
- Per-member pledge status
- Aggregate pledge vs. actual for a campaign (e.g. "Building Fund: GHS 45,000 pledged, GHS 31,200 received so far")
- Members with active pledges and no contributions in the last 30 days (follow-up list)

---

## Corrections and Voids

Mistakes happen. The system handles them without destroying the audit trail.

**Voiding a contribution:**
The admin cannot delete a contribution record. They can **void** it:
1. Mark the record as `voided`
2. Record a reason (free text, required)
3. Record who voided it and when

Voided records are excluded from all financial totals but remain in the database and are visible in the full audit log.

**Correcting an amount:**
If the wrong amount was entered:
1. Void the original record (with reason: "Incorrect amount entered")
2. Create a new correct record

This produces a clean audit trail: original record (voided), corrected record (active).

**Rule:** Only `church-admin` and `finance-secretary` with `giving.edit` permission can void contributions. All voids are logged.

---

## Sunday Offering Report

The most frequently needed report. Should take one click after a service.

**Inputs:** Select a service

**Output:**
```
Grace Assembly International — Sunday Service
12 October 2026

TITHE
  Cash:         GHS 1,200.00
  MoMo:         GHS   850.00
  Subtotal:     GHS 2,050.00

OFFERING
  Cash:         GHS   430.00
  MoMo:         GHS   220.00
  Subtotal:     GHS   650.00

BUILDING FUND
  Cash:         GHS   300.00
  MoMo:         GHS   150.00
  Subtotal:     GHS   450.00

─────────────────────────────
TOTAL:          GHS 3,150.00
  Cash:         GHS 1,930.00
  MoMo:         GHS 1,220.00

Transactions:   42
Anonymous:       7
```

Exportable as PDF. Shareable directly from the system.

---

## Giving Receipts

Every recorded contribution triggers an automatic confirmation to the member:

**SMS format:**
```
Grace Assembly: GHS 200.00 received (Tithe) on 12 Oct 2026.
Your total giving this month: GHS 450.00.
Thank you for your faithfulness. — Grace Assembly Intl
```

**Rules:**
- Receipts go out for MoMo/card immediately on Paystack webhook confirmation
- Receipts for cash are sent when the admin clicks "Send receipts" (batch, per service) or on individual record save (configurable)
- Anonymous contributions do not generate a receipt
- SMS credit is consumed per receipt — churches are aware of this through the credit dashboard

---

## Member Giving History

A member's profile shows their full giving history:

- Chronological list of all contributions (date, amount, fund type, method)
- Monthly totals for the last 12 months
- Year-to-date total
- All-time total since joining

**Access rules:**
- Finance Secretary → sees full named records
- Pastor → sees summary totals only (no individual amounts visible by default; configurable per church)
- Church Admin → full access
- Member → their own history only

This distinction matters. Some churches want the pastor to be shielded from knowing exactly who gives what to avoid it influencing pastoral treatment.

---

## Giving Trends and Analytics

Available in the reports module:

**For Finance Secretary / Church Admin:**
- Monthly giving chart (last 12 months) by fund type
- Year-over-year comparison
- Top contributors (by total amount, current year) — named list
- Giving by payment method trend (cash vs. MoMo over time)
- Members with no giving record in the last 90 days

**For Pastor:**
- Aggregate totals by fund type (no individual data)
- Month-over-month trend
- Pledge campaign progress summaries

---

## Paystack Integration Details

### Subaccount Setup
Each church gets a Paystack subaccount during onboarding. The onboarding flow collects:
- Business name (church name)
- Settlement account (bank account details for payouts)
- Account contact person

The platform calls the Paystack subaccount creation API. The `subaccount_code` is stored against the church record.

### Webhook Handling
Paystack sends webhooks to our server on every transaction event:
- `charge.success` → create contribution record, send receipt
- `charge.failed` → log failure, notify admin
- `refund.processed` → void the contribution record, note refund

Webhooks are verified using Paystack's HMAC-SHA512 signature before processing. All webhook payloads are stored in a `webhook_logs` table for debugging.

### Idempotency
Paystack webhooks can fire more than once for the same transaction (network retries). The system checks `paystack_reference` uniqueness before creating a contribution record. Duplicate webhooks are logged and silently discarded.

---

## What This Module Does NOT Do

- **No double-entry accounting.** This is a contributions tracker, not an accounting system.
- **No expenditure tracking.** That is a v2 feature (`expenditure_log`).
- **No income vs. expense balance sheet.** Use an accounting tool for that.
- **No recurring giving setup.** v2 consideration — manually record recurring gifts for now.
- **No tax receipts / statutory filings.** The CSV export is enough for a church's accountant to handle this.
- **No refunds initiated from this system.** Once a gift has been paid out to the church (MoMo/USSD) the platform does no refunds or clawbacks; that is between the church and the giver. Card refunds are handled directly in the Paystack dashboard by the church.

---

## Related Documents

- [`03-member-lifecycle.md`](./03-member-lifecycle.md) — member records that giving links to
- [`07-data-model.md`](./07-data-model.md) — `contributions`, `pledges`, `fund_types` table schemas
- [`08-api.md`](./08-api.md) — `/api/contributions` and `/api/pledges` endpoints
- [`06-architecture.md`](./06-architecture.md) — Paystack subaccount setup, webhook queue handling
- [`decisions/product-and-architecture-decisions.md`](../decisions/product-and-architecture-decisions.md) — Decision 6 (pricing/platform fee) and Decision 7 (accounting scope)
