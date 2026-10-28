# API Reference

> All routes are versioned under `/api/v1` (D-024). A breaking change gets `/api/v2`; the old version stays live until its announced sunset.

> The Laravel API is a RESTful JSON API. This document defines conventions, authentication, and every endpoint. Keep this in sync with the code — if an endpoint changes, update this doc in the same PR.

---

## Base URL

Every request targets the church's subdomain:

```
https://{church-slug}.yourapp.com/api/v1
```

Example: `https://grace-assembly.yourapp.com/api/v1/members`

---

## Conventions

### Request Format
- All request bodies: `application/json`
- All responses: `application/json`
- Dates: `YYYY-MM-DD` (ISO 8601 date)
- Timestamps: `YYYY-MM-DDTHH:MM:SSZ` (ISO 8601 UTC)
- Phone numbers: `+233XXXXXXXXX` (international format)
- Amounts: Numeric strings or floats, always GHS — never a currency symbol

### Response Envelope

**Success (single resource):**
```json
{
  "data": { ... }
}
```

**Success (collection):**
```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "per_page": 25,
    "total": 97
  },
  "links": {
    "first": "https://...",
    "last": "https://...",
    "prev": null,
    "next": "https://..."
  }
}
```

**Error:**
```json
{
  "message": "Human-readable error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### HTTP Status Codes
| Code | Meaning |
|---|---|
| `200` | OK — successful read or update |
| `201` | Created — resource created |
| `204` | No Content — successful delete |
| `400` | Bad Request — malformed request |
| `401` | Unauthenticated — missing or invalid token |
| `403` | Forbidden — authenticated but not authorised |
| `404` | Not Found |
| `422` | Unprocessable Entity — validation failed |
| `429` | Too Many Requests — rate limited |
| `500` | Server Error |

### Pagination
Default page size: `25`. Maximum: `100`.
Query params: `?page=2&per_page=50`

### Filtering & Sorting
Filters are query params. Example:
```
GET /api/v1/members?status=active&gender=female&department_id=ulid
GET /api/v1/contributions?from=2026-01-01&to=2026-03-31&fund_type_id=ulid
GET /api/v1/members?sort=last_name&direction=asc
```

### Authentication Header
All authenticated endpoints require:
```
Authorization: Bearer {token}
```

---

## Auth Endpoints

### Request OTP
```
POST /api/v1/auth/otp/request
```

**Body:**
```json
{
  "phone": "+233244123456"
}
```

**Response `200`:**
```json
{
  "message": "OTP sent",
  "expires_in": 600
}
```

**Rate limit:** 5 requests per phone per minute.

---

### Verify OTP
```
POST /api/v1/auth/otp/verify
```

**Body:**
```json
{
  "phone": "+233244123456",
  "otp": "382910"
}
```

**Response `200`:**
```json
{
  "token": "1|abc123...",
  "token_type": "Bearer",
  "user": {
    "id": "ulid",
    "name": "Akosua Mensah",
    "phone": "+233244123456",
    "roles": ["finance-secretary"],
    "permissions": ["giving.record", "giving.summary", "members.view", "reports.view", "reports.export"]
  },
  "church": {
    "id": "ulid",
    "name": "Grace Assembly International",
    "slug": "grace-assembly",
    "plan": "growth",
    "sms_credits": 420
  }
}
```

**Errors:**
- `422` — OTP invalid or expired
- `429` — Too many failed attempts (15-minute lockout)

---

### Logout
```
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

**Response `204`** — Token revoked.

---

### Get Authenticated User
```
GET /api/v1/auth/me
Authorization: Bearer {token}
```

**Response `200`:**
```json
{
  "data": {
    "id": "ulid",
    "name": "Akosua Mensah",
    "phone": "+233244123456",
    "email": "akosua@example.com",
    "roles": ["finance-secretary"],
    "permissions": ["giving.record", "giving.summary", "members.view"]
  }
}
```

---

## Members

### List Members
```
GET /api/v1/members
Authorization: Bearer {token}
Permission: members.view
```

**Query params:**
| Param | Type | Example |
|---|---|---|
| `status` | string | `active`, `inactive`, `prospect` |
| `gender` | string | `male`, `female` |
| `department_id` | ulid | |
| `family_id` | ulid | |
| `educational_level` | string | `jhs_1`, `shs_3` |
| `search` | string | Name or phone search |
| `sort` | string | `last_name`, `created_at`, `membership_date` |
| `direction` | string | `asc`, `desc` |

**Response `200`:** Paginated collection of member summaries.

```json
{
  "data": [
    {
      "id": "ulid",
      "member_number": "GRC-0042",
      "full_name": "Emmanuel Mensah-Boateng",
      "preferred_name": "Emmanuel",
      "gender": "male",
      "phone": "+233244123456",
      "status": "active",
      "photo_url": "https://r2.yourapp.com/...",
      "departments": ["Choir", "Ushering Ministry"],
      "family_id": "ulid",
      "membership_date": "2023-04-15"
    }
  ],
  "meta": { ... }
}
```

---

### Get Member
```
GET /api/v1/members/{id}
Authorization: Bearer {token}
Permission: members.view
```

**Response `200`:** Full member resource.

```json
{
  "data": {
    "id": "ulid",
    "member_number": "GRC-0042",
    "first_name": "Emmanuel",
    "middle_name": "Kwasi",
    "last_name": "Mensah-Boateng",
    "preferred_name": "Emmanuel",
    "gender": "male",
    "date_of_birth": "1985-07-12",
    "phone": "+233244123456",
    "phone_secondary": null,
    "email": "emensah@example.com",
    "address": "[decrypted on response]",
    "nearest_landmark": "Near the Total filling station",
    "digital_address": "GA-042-1234",
    "photo_url": "https://...",
    "marital_status": "married",
    "occupation": "Teacher",
    "tribe": "Akan",
    "language_preference": "Twi",
    "educational_level": null,
    "membership_type": "full",
    "membership_date": "2023-04-15",
    "status": "active",
    "family": {
      "id": "ulid",
      "name": "Mensah-Boateng Family",
      "role": "head"
    },
    "departments": [
      { "id": "ulid", "name": "Choir", "role": "Member" }
    ],
    "milestones": [
      { "type": "baptism", "occurred_at": "2010-06-05", "officiant": "Rev. Dr. Acheampong" }
    ],
    "assigned_pastor": { "id": "ulid", "name": "Pastor Emmanuel Adjei" },
    "giving_summary": {
      "this_year": 2400.00,
      "this_month": 200.00,
      "all_time": 8650.00
    },
    "attendance_summary": {
      "last_4_weeks": 3,
      "last_8_weeks": 7,
      "last_12_weeks": 10
    },
    "whatsapp_opted_in": true,
    "sms_opted_in": true,
    "created_at": "2023-04-15T10:30:00Z"
  }
}
```

**Note:** `giving_summary` is omitted for roles without `giving.summary` permission. `address` is decrypted server-side before returning (never the encrypted blob).

---

### Create Member
```
POST /api/v1/members
Authorization: Bearer {token}
Permission: members.edit
```

**Body:**
```json
{
  "first_name": "Abena",
  "last_name": "Asante",
  "gender": "female",
  "phone": "+233200987654",
  "date_of_birth": "1992-03-18",
  "marital_status": "single",
  "membership_date": "2026-10-19",
  "membership_type": "full",
  "family_id": "ulid",
  "family_role": "spouse",
  "department_ids": ["ulid-choir", "ulid-ushers"]
}
```

**Response `201`:** Full member resource.

---

### Update Member
```
PUT /api/v1/members/{id}
Authorization: Bearer {token}
Permission: members.edit
```

**Body:** Any subset of member fields. Only provided fields are updated.

**Response `200`:** Updated member resource.

---

### Update Member Status
```
PATCH /api/v1/members/{id}/status
Authorization: Bearer {token}
Permission: members.edit
```

**Body:**
```json
{
  "status": "inactive",
  "note": "Relocated to Kumasi — expected to transfer to a local branch"
}
```

**Response `200`:** Updated member resource.

---

### Upload Member Photo
```
POST /api/v1/members/{id}/photo
Authorization: Bearer {token}
Permission: members.edit
Content-Type: multipart/form-data
```

**Body:** `photo` field — JPG or PNG, max 5MB.

**Response `200`:**
```json
{ "photo_url": "https://r2.yourapp.com/..." }
```

---

### Add Member Milestone
```
POST /api/v1/members/{id}/milestones
Authorization: Bearer {token}
Permission: members.edit
```

**Body:**
```json
{
  "type": "baptism",
  "occurred_at": "2026-10-19",
  "officiant": "Rev. Mensah",
  "notes": "Water baptism by immersion"
}
```

**Response `201`:** Milestone resource.

---

## Families

### List Families
```
GET /api/v1/families
Authorization: Bearer {token}
Permission: members.view
```

### Get Family
```
GET /api/v1/families/{id}
```
Returns family record + all linked members with their status and basic profile.

### Create / Update Family
```
POST /api/v1/families
PUT /api/v1/families/{id}
```

---

## Services & Attendance

### List Services
```
GET /api/v1/services
Authorization: Bearer {token}
```

**Query params:** `?from=2026-10-01&to=2026-10-31&type=sunday`

### Create Service
```
POST /api/v1/services
Authorization: Bearer {token}
Permission: members.edit
```

**Body:**
```json
{
  "name": "Sunday Morning Service",
  "type": "sunday",
  "service_date": "2026-10-26",
  "starts_at": "09:00",
  "ends_at": "11:30"
}
```

**Response `201`:** Service resource including auto-generated `checkin_code`.

---

### Open / Close Service for Check-in
```
PATCH /api/v1/services/{id}/checkin
Authorization: Bearer {token}
```

**Body:**
```json
{ "is_open": true }
```

---

### Record Attendance (Manual)
```
POST /api/v1/services/{id}/attendance
Authorization: Bearer {token}
Permission: members.view
```

**Body:**
```json
{
  "member_ids": ["ulid1", "ulid2", "ulid3"],
  "method": "manual"
}
```

**Response `201`:**
```json
{
  "recorded": 3,
  "already_checked_in": 0,
  "errors": []
}
```

---

### WhatsApp Check-in (Internal — called by webhook handler)
```
POST /api/v1/checkin/whatsapp
```
Internal endpoint called by the WhatsApp webhook processor. Not called by the SPA directly.

---

### Get Attendance for a Service
```
GET /api/v1/services/{id}/attendance
Authorization: Bearer {token}
```

**Response `200`:**
```json
{
  "service": { "id": "ulid", "name": "Sunday Morning Service", "service_date": "2026-10-26" },
  "summary": {
    "total": 124,
    "members": 118,
    "visitors": 6,
    "by_method": { "manual": 80, "whatsapp": 30, "qr": 14 }
  },
  "data": [ ... attendance records ... ]
}
```

---

## Contributions

### List Contributions
```
GET /api/v1/contributions
Authorization: Bearer {token}
Permission: giving.summary
```

**Query params:**
| Param | Type | Example |
|---|---|---|
| `member_id` | ulid | Filter by member |
| `service_id` | ulid | Filter by service |
| `fund_type_id` | ulid | |
| `payment_method` | string | `momo`, `card`, `cash` |
| `from` | date | `2026-01-01` |
| `to` | date | `2026-12-31` |
| `is_voided` | boolean | `false` (default excludes voided) |

---

### Get Contribution
```
GET /api/v1/contributions/{id}
Authorization: Bearer {token}
Permission: giving.summary
```

---

### Record Cash Contribution
```
POST /api/v1/contributions
Authorization: Bearer {token}
Permission: giving.record
```

**Body:**
```json
{
  "member_id": "ulid",
  "service_id": "ulid",
  "fund_type_id": "ulid",
  "amount": 200.00,
  "payment_method": "cash",
  "pledge_id": null,
  "contributed_at": "2026-10-26T10:15:00Z",
  "notes": ""
}
```

**Response `201`:** Full contribution resource.

**Side effect:** `ContributionReceived` event fired → `SendContributionReceiptJob` queued.

---

### Initiate MoMo / Card Payment
```
POST /api/v1/contributions/initiate
Authorization: Bearer {token}
Permission: giving.record
```

**Body:**
```json
{
  "member_id": "ulid",
  "fund_type_id": "ulid",
  "amount": 200.00,
  "payment_method": "momo",
  "phone": "+233244123456"
}
```

**Response `200`:**
```json
{
  "reference": "01j8k2m4n5p6q7r8s9t0u1v2w3",   // our ULID for this attempt; also sent to the provider as its client reference / idempotency key
  "authorization_url": null,                    // only set for card payments (Paystack hosted checkout)
  "status": "pending"
}
```

The contribution record is created when the provider's callback (or our reconciliation poll) confirms success. Do not create a duplicate record manually.

---

### Void Contribution
```
PATCH /api/v1/contributions/{id}/void
Authorization: Bearer {token}
Permission: giving.edit
```

**Body:**
```json
{
  "reason": "Incorrect amount entered — see corrected record GRC-C-0199"
}
```

**Response `200`:** Updated contribution resource with `is_voided: true`.

---

### Sunday Offering Report
```
GET /api/v1/services/{id}/offering-report
Authorization: Bearer {token}
Permission: giving.summary
```

**Response `200`:**
```json
{
  "service": { "id": "ulid", "name": "Sunday Morning Service", "service_date": "2026-10-26" },
  "totals": {
    "grand_total": 3150.00,
    "by_fund": [
      { "fund_type": "Tithe", "cash": 1200.00, "momo": 850.00, "card": 0.00, "total": 2050.00 },
      { "fund_type": "Offering", "cash": 430.00, "momo": 220.00, "card": 0.00, "total": 650.00 }
    ],
    "by_method": { "cash": 1930.00, "momo": 1220.00, "card": 0.00 },
    "transaction_count": 42,
    "anonymous_count": 7
  }
}
```

---

## Pledges

### List Pledges
```
GET /api/v1/pledges
Authorization: Bearer {token}
Permission: giving.summary
```

**Query params:** `?member_id=ulid&status=active&fund_type_id=ulid`

### Get Pledge
```
GET /api/v1/pledges/{id}
```
Returns pledge + all linked contributions.

### Create Pledge
```
POST /api/v1/pledges
Authorization: Bearer {token}
Permission: giving.record
```

**Body:**
```json
{
  "member_id": "ulid",
  "fund_type_id": "ulid",
  "description": "Building Fund 2026 Pledge",
  "amount_pledged": 1000.00,
  "target_date": "2026-12-31"
}
```

---

## Communications

### Send Broadcast
```
POST /api/v1/messages/broadcast
Authorization: Bearer {token}
Permission: sms.send
```

**Body:**
```json
{
  "channel": "sms",
  "recipient_filter": {
    "status": "active",
    "department_id": "ulid"
  },
  "message": "Dear member, you are warmly invited to our Annual Harvest Service this Sunday at 9am. God bless you."
}
```

**Response `202`:**
```json
{
  "queued": true,
  "estimated_recipients": 87,
  "estimated_credit_cost": 87,
  "current_credit_balance": 420
}
```

Recipients are confirmed and messages dispatched asynchronously. Credits are deducted per delivered message.

---

### Get Message Log
```
GET /api/v1/messages
Authorization: Bearer {token}
Permission: members.view
```

**Query params:** `?channel=sms&message_type=broadcast&from=2026-10-01`

---

## Reports

### Attendance Trends
```
GET /api/v1/reports/attendance
Authorization: Bearer {token}
Permission: reports.view
```

**Query params:** `?weeks=12` (default 12)

**Response:**
```json
{
  "data": [
    { "service_date": "2026-10-19", "service_name": "Sunday Morning Service", "total": 118 },
    { "service_date": "2026-10-12", "service_name": "Sunday Morning Service", "total": 124 }
  ],
  "summary": {
    "average": 121,
    "highest": 156,
    "lowest": 98
  }
}
```

---

### Giving Trends
```
GET /api/v1/reports/giving
Authorization: Bearer {token}
Permission: giving.summary
```

**Query params:** `?months=12`

---

### Member Growth
```
GET /api/v1/reports/members
Authorization: Bearer {token}
Permission: reports.view
```

---

### Export Members (CSV)
```
POST /api/v1/exports/members
Authorization: Bearer {token}
Permission: members.view
```

**Body:** Any filter set (same as `GET /api/members` query params).

**Response `202`:**
```json
{
  "export_id": "ulid",
  "message": "Export queued. You will receive a download link via SMS when ready."
}
```

---

### Export Contributions (CSV)
```
POST /api/v1/exports/contributions
Authorization: Bearer {token}
Permission: giving.export
```

---

## Fund Types

### List Fund Types
```
GET /api/v1/fund-types
Authorization: Bearer {token}
```

### Create / Update / Deactivate Fund Type
```
POST   /api/v1/fund-types
PUT    /api/v1/fund-types/{id}
PATCH  /api/v1/fund-types/{id}   { "is_active": false }
```
Permission: `church-admin` only.

---

## Departments

### List / Create / Update
```
GET    /api/v1/departments
POST   /api/v1/departments
PUT    /api/v1/departments/{id}
```

### Add / Remove Member from Department
```
POST   /api/v1/departments/{id}/members   { "member_id": "ulid", "role": "Member" }
DELETE /api/v1/departments/{id}/members/{member_id}
```

---

## Church Settings

### Get Settings
```
GET /api/v1/church/settings
Authorization: Bearer {token}
```

### Update Settings
```
PUT /api/v1/church/settings
Authorization: Bearer {token}
Permission: church-admin
```

**Body (subset):**
```json
{
  "settings": {
    "absence_threshold_weeks": 3,
    "receipt_on_cash": true,
    "checkin_method": ["whatsapp", "manual"],
    "weekly_report_day": "monday",
    "birthday_alert_days_before": 7
  }
}
```

---

## Webhooks (Incoming)

These endpoints receive payloads from external services. They do not require a Bearer token — they verify authenticity via payload signatures.

### PayPerlez Webhook (MoMo collections and payouts)
```
POST /api/v1/webhooks/payperlez
Header: X-Payperlez-Signature: t=<unix-seconds>,v1=<hmac_sha256 of "t.body">
```

Rejected if the signature is invalid or the timestamp is more than 5 minutes old. Event names are still to be confirmed with PayPerlez; until then processing also relies on polling (see `14-integrations.md`).

### Paystack Webhook (cards)
```
POST /api/v1/webhooks/paystack
Header: X-Paystack-Signature: {hmac_sha512}
```

Handled events: `charge.success`, `charge.failed`, `refund.processed`

### Meta / WhatsApp Webhook
```
GET  /api/v1/webhooks/whatsapp   (Meta verification challenge)
POST /api/v1/webhooks/whatsapp   (Incoming messages)
```

Handled message types: `text` (for CHECKIN command parsing), `status` (delivery receipts)

---

## Rate Limits

| Endpoint group | Limit |
|---|---|
| `POST /api/auth/otp/request` | 5 per phone per minute |
| `POST /api/auth/otp/verify` | 5 attempts per phone per window |
| `POST /api/messages/broadcast` | 10 per hour per church |
| All other authenticated endpoints | 300 per minute per token |

Rate limit headers returned on every response:
```
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 287
X-RateLimit-Reset: 1761264000
```

---

## Related Documents

- [`06-architecture.md`](./06-architecture.md) — how the API is structured and deployed
- [`07-data-model.md`](./07-data-model.md) — the database tables these endpoints read/write
- [`09-engineering-principles.md`](./09-engineering-principles.md) — API design rules
- [`15-security.md`](./15-security.md) — auth, rate limiting, and webhook signature verification
