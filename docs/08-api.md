# API Reference

> All routes are versioned under `/api/v1` (D-024). A breaking change gets `/api/v2`; the old version stays live until its announced sunset.

> The Laravel API is a RESTful JSON API. This document defines conventions, authentication, and every endpoint. Keep this in sync with the code — if an endpoint changes, update this doc in the same PR.

---

## Machine-readable spec

The OpenAPI 3.1 spec of this API is committed at `docs/openapi.json` and generated from the code by Scramble, so it cannot drift: `php artisan test` and a CI step fail when it is out of date. After changing an endpoint, run `php artisan scramble:export` and commit the result. The interactive docs are at `/docs/api` (and the JSON at `/docs/api.json`) on every church host; they are open in local development and, everywhere else, only for staff who may change church settings (D-035). This hand-written page explains the conventions; the spec is the contract.

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

### Authentication
All authenticated endpoints accept **either**:

- **A session cookie** (the church's web app). Sign-in sets an httpOnly cookie; send `Accept: application/json`, the cookie, and the `X-XSRF-TOKEN` header on writes (call `GET /sanctum/csrf-cookie` first). Requests must come from the church's own host (D-027).
- **A bearer token** (any other client): `Authorization: Bearer {token}`.

---

## Onboarding Endpoints (start a church)

These two calls go to the **platform's own host** (`https://yourapp.com/api/v1/...`, no church subdomain), because no church exists yet. On a church subdomain they answer `404`. They need no sign-in.

### Request a sign-up code
```
POST /api/v1/onboarding/otp
```

**Body:** `{ "admin_phone": "+233244300001" }` (a local number such as `0244300001` is accepted).

**Response `200`:** `{ "message": "OTP sent", "expires_in": 600 }`

**Rate limit:** 1 per phone per minute, 3 per phone per hour, 10 per IP per hour. The code is texted on the `critical` queue; it proves the person owns the phone (D-037).

### Start a church
```
POST /api/v1/onboarding/churches
```

**Body:**
```json
{
  "church_name": "Victory Chapel International",
  "slug": "victory-chapel",
  "denomination": "Pentecostal",
  "city": "Kumasi",
  "member_number_prefix": "VCI",
  "admin_name": "Kofi Mensah",
  "admin_phone": "+233244300001",
  "otp": "123456"
}
```
`slug`, `denomination` and `city` are optional. Without a `slug` the address is built from the church name (`grace-assembly`, then `grace-assembly-2` if taken). A typed `slug` is lowercase letters, numbers and single hyphens, 3 to 40 characters, and not one of the names kept for the platform (`config/onboarding.php`). `member_number_prefix` is 2 to 6 letters and is stored in capitals.

**Response `201`:**
```json
{
  "church": { "id": "ulid", "name": "Victory Chapel International", "slug": "victory-chapel", "member_number_prefix": "VCI", "url": "https://victory-chapel.yourapp.com" },
  "admin": { "id": "ulid", "name": "Kofi Mensah" },
  "login_url": "https://victory-chapel.yourapp.com/login"
}
```

The church (plan `starter`, no payment) and its first admin (role `church-admin`) are created together, or not at all. The admin then signs in on `login_url` with a new code.

**Errors:** `422` with `{"message": "Invalid code"}` for a wrong, used or expired code; `429` after five wrong codes for a phone (15-minute lockout); `422` on `slug` when the address is taken, with free alternatives in the message (the code is not used up); `422` per field for the rest.

**Rate limit:** 10 per IP per hour.

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

**Rate limit:** 1 request per phone per minute and 5 per hour (D-025). Always returns `200`, whether or not the phone belongs to a staff member, so numbers cannot be enumerated.

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

**Response `200`:** A browser request from the church's own page is signed in by cookie and the response has **no `token` or `token_type`**. Any other client gets:
```json
{
  "token": "01j...|abc123...",
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
```

**Response `204`** — the cookie session is ended, or the bearer token is revoked.

---

### Get Authenticated User
```
GET /api/v1/auth/me
```

The web app calls this on load to restore its session after a refresh.

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
  },
  "church": { "id": "ulid", "name": "Grace Assembly International", "slug": "grace-assembly", "plan": "growth", "sms_credits": 420 }
}
```

`401` when there is no valid session or token, or when the staff member was deactivated or changed phone number.

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
| `search` | string | Every word must match the first, middle, last or preferred name, the phone or the member number, ignoring case. `%` and `_` are plain characters. |
| `sort` | string | `last_name` (default), `first_name`, `created_at`, `membership_date` |
| `direction` | string | `asc` (default), `desc` |
| `per_page` | integer | 1 to 100, default 25 |

**Default view:** with no `status` and no `search`, only `prospect` and `active` members are listed. Inactive, transferred, deceased and archived members stay out of the default view but are found by `search` or an explicit `status` (docs/03).

**Who sees whom (D-031):** the church admin, pastor and finance secretary see every member. A department head sees members of the departments they head. A youth pastor sees youth members: younger than the church's youth age limit (default 25), or, with no date of birth, any educational level except `graduate`. Asking for a member outside that view returns `403`. Invalid filters return `422`.

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

**Note:** every enum field is sent as its value plus a `_label` sibling for display, for example `"status": "active"`, `"status_label": "Active"`. `giving_summary` is omitted (the key is absent) for roles without `giving.summary` permission. Its amounts are decimal strings in GHS, for example `"2400.00"`, each with a `_formatted` sibling, for example `"this_year_formatted": "GHS 2,400.00"`, and voided contributions never count. Opening a member's full record writes a `viewed` line to the change log, because it returns the encrypted address (D-034). `address` is decrypted server-side before returning (never the encrypted blob). `national_id_number` is never returned. `photo_url` arrives with the photo endpoint. The record also carries `status_changed_at` and `status_note`.

**Errors:** `403` for a member outside the caller's view; `404` for a member that does not exist, is deleted, or belongs to another church.

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

`member_number` is assigned by the server (`GRC-0042`, see D-030) and is ignored if sent. `status` may be `prospect` or `active` (default `active`). `department_ids` and `family_id` must belong to this church. A phone number may be sent as `0244123456` or `+233244123456`; it is stored in international form.

**Response `201`:** Full member resource.

**Errors:** `403` without `members.edit`. `422` for validation failures, including: a `phone` already used by a non-deleted member of this church; a `family_role` without a `family_id`; a second `head` in one family; or a church that has not yet set its member number prefix. Each create is written to the change log.

---

### Update Member
```
PUT /api/v1/members/{id}
Authorization: Bearer {token}
Permission: members.edit
```

**Body:** Any subset of member fields. Only provided fields are updated. `member_number` and `status` are ignored (status has its own endpoint). Sending `department_ids` makes the member's departments exactly that list. Sending `family_id: null` also clears `family_role`.

**Response `200`:** Updated member resource.

**Errors:** `403` without `members.edit`, or when the member is deceased (the record is read-only). Every change is written to the change log with the old and new values and the acting user; encrypted fields (`address`, `national_id_number`) are logged as `[changed]`, never by value.

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

**Permissions:** `members.edit` to move a member to `prospect` or `active`. Moving to `inactive`, `transferred`, `deceased` or `archived` also needs `members.deactivate` (church admin only), otherwise `403`. The note is kept in the change log as the reason.

---

### Upload Member Photo
```
POST /api/v1/members/{id}/photo
Authorization: Bearer {token}
Permission: members.edit
Content-Type: multipart/form-data
```

**Body:** `photo` field: JPG or PNG, up to 5 MB and 4096 pixels on a side. The type is read from the file's contents, so a renamed script or SVG is rejected.

The photo is rotated to match the camera, cropped to a 512 pixel square and re-encoded as JPEG, which removes EXIF data such as GPS positions. It is stored in the private bucket at `churches/{church_id}/members/{member_id}/photo.jpg`; a new upload replaces the old one. The client's file name is never used.

**Response `200`:**
```json
{ "photo_url": "https://<signed link, valid for 1 hour>" }
```

`photo_url` on Get Member and List Members is a fresh signed link on every response, or `null` when the member has none. **Errors:** `403` without `members.edit` or for a deceased member; `404` for another church's member; `422` for an invalid file; `429` after 20 uploads a minute.

---

### Export Member Data (subject access)
```
GET /api/v1/members/{id}/data-export
```
**Permission:** `members.export`, and the member must be visible to the caller. `403` otherwise; `404` for another church's member.

**Response `200`:** `exported_at`, `member` (every personal field, with the encrypted address and ID number readable), `family` (name and role), `departments`, `milestones`, `consents` (each agreement to receive texts or WhatsApp messages: purpose, when, withdrawn when, policy version) and `history` (each change: event, field names, who, reason, when). The photo file is not part of the JSON. Each export is recorded in the member's change log (`exported`), without the data.

---

### Erase Member (right to erasure)
```
POST /api/v1/members/{id}/erasure
```
**Body:** `{ "reason": "Member asked in writing on 2026-10-20" }` (3 to 1000 characters; the reason is kept in the change log, so it must not contain personal details).

**Permission:** `members.erase` (church admin only). **Response `204`.** It cannot be undone.

The member's name becomes `[deleted]`, and their phone, birth date, email, address, landmark, GPS code, ID details, occupation, tribe, language, education, marital status, membership date and notes are removed. The photo file is deleted, the phone and text are removed from the messages sent to them, milestone notes are cleared and a family they head loses its head. The record stays: it is archived and soft-deleted, its member number is kept, and giving and attendance records that point to it keep working. In the member's history every line and field name stays, but the values become `[deleted]`, and an `erased` line is added. The phone number can be used by a new member straight away. A repeat call is `404`.

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

`type` is one of `dedication`, `baptism`, `confirmation`, `first_communion`, `membership_class`. `occurred_at` cannot be in the future. `cohort` is optional (for example "Confirmation Class 2026"). The acting user is recorded as `created_by`.

**Response `201`:** `{ "data": { "id", "type", "occurred_at", "officiant", "cohort", "notes" } }`

**Errors:** `403` without `members.edit`, or when the member is deceased. `404` for another church's member.

Milestones appear on Get Member only for roles with `milestones.view` (church admin, pastor, youth pastor); for other roles the key is absent (docs/03).

---

## Families

### List Families
```
GET /api/v1/families
Authorization: Bearer {token}
Permission: members.view
```

**Query params:** `search` (name, ignoring case), `per_page` (1 to 100, default 25). Paginated, ordered by name. Each family has `id`, `name`, `notes`, `head_member_id` and `members_count`; the count only includes members the caller may see (D-031).

### Get Family
```
GET /api/v1/families/{id}
Permission: members.view
```
Returns the family and the members the caller may see, each with `id`, `member_number`, `full_name`, `gender`, `status` and `family_role`. `404` for another church's family.

### Create Family
```
POST /api/v1/families
Permission: members.edit
```

**Body:** `{ "name": "Mensah-Boateng Family", "notes": "optional", "head_member_id": "ulid (optional)" }`

`head_member_id` must be a member of this church who is not already in a family (`422` otherwise). The member becomes the head: their `family_id` and `family_role` are set and the change is written to their change log. **Response `201`:** the family.

### Update Family
```
PUT /api/v1/families/{id}
Permission: members.edit
```

**Body:** `name` and/or `notes`. The head is not changed here: change each member's `family_role` with Update Member, which enforces one head per family. **Response `200`:** the family.

Members are linked to a family through `family_id` and `family_role` on Create Member and Update Member. A member belongs to one family at a time.

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

Permissions: `members.view` to list, `members.edit` for everything else. A department of another church returns `404`.

### List Departments
```
GET /api/v1/departments
```
**Query params:** `is_active` (`0` or `1`), `per_page` (1 to 100, default 25). Paginated, ordered by name. Each department has `id`, `name`, `description`, `head_user_id`, `is_active` and `members_count`; the count only includes members the caller may see (D-031).

### Create / Update
```
POST /api/v1/departments
PUT  /api/v1/departments/{id}
```
**Body:** `name` (required on create, unique among this church's departments), `description`, `head_user_id` (an active staff member of this church), `is_active`. Update accepts any subset; `is_active: false` retires a department. **Response:** `201` on create, `200` on update, with the department.

### Add / Remove Member from Department
```
POST   /api/v1/departments/{id}/members   { "member_id": "ulid", "role": "Member" }
DELETE /api/v1/departments/{id}/members/{member_id}
```
Adding returns `201` with `{ "data": { "department_id", "member_id", "role", "joined_at" } }`. If the member already serves in the department their role is changed and the reply is `200`, so a retried request is harmless. `member_id` must belong to this church (`422` otherwise). Removing returns `204` and soft deletes the link so the history is kept; `404` if the member is not in the department. A member can also be placed in departments with `department_ids` on Create Member and Update Member.

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
    "birthday_alert_days_before": 7,
    "youth_age_limit": 25
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
| `POST /api/v1/auth/otp/request` | 1 per phone per minute, 5 per phone per hour, 20 per IP per hour |
| `POST /api/v1/auth/otp/verify` | 5 wrong codes per phone, then a 15-minute lockout; 10 calls per minute per IP |
| `POST /api/v1/onboarding/otp` | 1 per phone per minute, 3 per phone per hour, 10 per IP per hour |
| `POST /api/v1/onboarding/churches` | 10 per IP per hour; 5 wrong codes per phone, then a 15-minute lockout |
| `POST /api/v1/messages/broadcast` | 10 per hour per church |
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
