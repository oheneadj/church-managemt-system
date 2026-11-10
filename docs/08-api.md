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

### Idempotency (writes that record money)
Endpoints that record a gift or start a payment require an `Idempotency-Key` header (8 to 100 letters, digits, dashes or underscores): one new value for each thing the person means to do, for example a ULID made when the form opens. The first successful answer is kept for 24 hours and sent again, with the header `Idempotent-Replayed: true`, if the same key arrives again (a double click, a retry after a timeout), so nothing is recorded twice. The same key with a different request is `422`; two requests with one key at the same moment take turns, and one that waits too long is `409` (try again); a missing or badly shaped key is `400`. A failed request is not kept, so the corrected request can reuse its key. Keys are private to the person and the church.

### Dates
The API always writes and reads dates as `YYYY-MM-DD` (and times as `H:i`, or ISO 8601 UTC for moments). The React app uses the browser's own date input (`type="date"`) wherever a person picks a date, so the calendar and the order of day, month and year follow the person's phone or computer; its value is already `YYYY-MM-DD`, so it is sent as it is (D-066). Dates shown on screen as text are written day first ("25 Oct 2026"). The calendar is also limited with `min` and `max` to the days the API accepts. Where a date has a limit, the API enforces it with a `422` on that field:

| Date | Limit |
|---|---|
| Date of birth | not in the future, not before 1900-01-01 (`config/members.php`) |
| Member since (`membership_date`) | not in the future, not before the member's date of birth |
| Milestone date (`occurred_at`) | not in the future, not before the member's date of birth |
| Service date | from 30 days ago to 366 days ahead (`config/attendance.php`); an old service saved again with its own date is fine |
| Gift date (`contributed_at`) | not in the future; no limit into the past |
| Pledge target date | today or later when a pledge is made, and when it is changed to a new date |

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
  "admin_email": "kofi@example.com",
  "password": "a long passphrase",
  "password_confirmation": "a long passphrase",
  "otp": "123456"
}
```
`slug`, `denomination` and `city` are optional. Without a `slug` the address is built from the church name (`grace-assembly`, then `grace-assembly-2` if taken). A typed `slug` is lowercase letters, numbers and single hyphens, 3 to 40 characters, and not one of the names kept for the platform (`config/onboarding.php`). `member_number_prefix` is 2 to 6 letters and is stored in capitals. `admin_email` and `password` are how the admin signs in (D-054): the email is stored in lowercase, the password needs 12 to 72 bytes, must match `password_confirmation` and must not equal the email. The phone is still proven with the texted `otp`, and stays as a contact number. The password is checked against known breaches afterwards in a queued job, and the admin is emailed if it was found.

**Response `201`:**
```json
{
  "church": { "id": "ulid", "name": "Victory Chapel International", "slug": "victory-chapel", "member_number_prefix": "VCI", "url": "https://victory-chapel.yourapp.com" },
  "admin": { "id": "ulid", "name": "Kofi Mensah" },
  "login_url": "https://victory-chapel.yourapp.com/login"
}
```

The church (plan `starter`, no payment) and its first admin (role `church-admin`) are created together, or not at all. The admin then signs in on `login_url` with the email and password, then a code emailed to them.

**Errors:** `422` with `{"message": "Invalid code"}` for a wrong, used or expired code; `429` after five wrong codes for a phone (15-minute lockout); `422` on `slug` when the address is taken, with free alternatives in the message (the code is not used up); `422` per field for the rest.

**Rate limit:** 10 per IP per hour.

---

## Auth Endpoints

> **D-054, built.** Staff sign in with email, password and a code emailed to them: `auth/login`, `auth/login/verify`, `auth/password/forgot` and `auth/password/reset` below, used by the React sign-in, forgot-password and sign-up screens. The old phone and SMS staff endpoints (`auth/otp/request`, `auth/otp/verify`) are removed. Members, QR check-in and WhatsApp check-in keep phone and SMS codes.

### Sign in, step 1: password
```
POST /api/v1/auth/login
```
**Body:** `{ "email": "akosua@example.com", "password": "..." }`

**Response `200`:** `{ "message": "Code sent", "expires_in": 600 }`: the password was right and a 6-digit code was emailed. **Errors:** `422` `{"message": "Invalid email or password"}` for an unknown email, a wrong password or a user with no password (one message for all, nothing is sent); `429` after 5 wrong passwords for an email (15-minute lockout).

**Rate limits (D-054):** emailing a code is a cost-triggering action, so it has its own limit, counted only when a code is really sent (a mistyped password never uses it up): 1 code per email per minute and 5 per hour, 20 per IP per hour, each `429` with a `Retry-After`. Guessing passwords is limited to 20 attempts a minute per IP, and 5 wrong passwords lock that email for 15 minutes (`429`, `Retry-After: 900`). The limits are per church.

### Sign in, step 2: emailed code
```
POST /api/v1/auth/login/verify
```
**Body:** `{ "email": "akosua@example.com", "code": "382910" }`

**Response `200`:** the same as before. A browser request from the church's own page is signed in by cookie and the response has **no `token` or `token_type`**. Any other client gets:
```json
{
  "token": "01j...|abc123...",
  "token_type": "Bearer",
  "user": {
    "id": "ulid",
    "name": "Akosua Mensah",
    "email": "akosua@example.com",
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
**Errors:** `422` `{"message": "Invalid code"}` (wrong, used or expired, one message); `429` after 5 wrong codes (15-minute lockout); `10` calls a minute per IP.

### Forgot password
```
POST /api/v1/auth/password/forgot      { "email": "akosua@example.com" }
POST /api/v1/auth/password/reset       { "email": "...", "code": "382910", "password": "...", "password_confirmation": "..." }
```
**`forgot`** always answers `200` `{ "message": "If that email belongs to a staff member, a code has been sent.", "expires_in": 600 }`, whether or not the email is known or the account is active, so emails cannot be listed. It emails a reset code (queued on `critical`) only to an active staff member. The limit on emailed codes (1 a minute and 5 an hour per email, 20 an hour per IP) is counted for every email, known or not, so a `429` reveals nothing either.

**`reset`** answers `204` with no body. It does **not** sign anyone in: the person signs in with the new password. It ends every session and token of that user at once, and marks the email proven. Errors: `422 "Invalid code"` for a wrong, expired, reused, other-church or other-purpose code, an unknown email and a deactivated account alike; `429` after five wrong codes (15-minute lockout) or more than 10 checks a minute from one address; `422` on `password` when it is shorter than 12 characters, longer than 72 (bcrypt ignores everything after byte 72, so a longer one would silently be weaker), the same as the email, or does not match `password_confirmation`. A refused password does not use the code up, so the person can try again. The same flow sets the first password for a staff member who has none.

**The breach check is never done while the person waits.** After the password is set, a queued, encrypted job checks it against the Have I Been Pwned list (only the first five characters of its SHA-1 hash leave the server, `docs/14` section 8) and, if it was exposed, emails the person to choose another with "Forgot password". The plaintext password is never put on a queue, and nothing in this check can lock anyone out: if the list is unreachable the failure is reported and the password stays.

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

`401` when there is no valid session or token, or when the staff member was deactivated or changed their email or password.

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
**Permission:** `members.data-export` (church admin only, D-055), and the member must be visible to the caller. `403` otherwise; `404` for another church's member.

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

`type` is one of `dedication`, `baptism`, `confirmation`, `first_communion`, `membership_class`. `occurred_at` cannot be in the future, or before the member's date of birth. `cohort` is optional (for example "Confirmation Class 2026"). The acting user is recorded as `created_by`.

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

**Query params:** `from` and `to` (dates, `Y-m-d`), `type` (`sunday`, `midweek`, `special`, `youth`, `children`), `is_open` (true or false), `per_page`. Newest first, paginated. Every signed-in role may read services (QA-10). Another church's services never appear.

**Each service:** `id`, `name`, `type` and `type_label`, `checkin_code`, `schedule_id` (the weekly schedule that made it, or `null` for one made by hand), `service_date`, `starts_at`, `ends_at` (`H:i` or null), `is_open`, `headcount_override`, `notes`, `attendance_count`, `created_at`.

### Get Service
```
GET /api/v1/services/{id}
```
`404` for a service of another church.

### Update Service
```
PUT /api/v1/services/{id}
Permission: members.edit
```
Any subset of `name`, `type`, `service_date`, `starts_at`, `ends_at`, `headcount_override` (a paper count that stands in for the digital count), `notes`. The check-in code cannot be changed.

### Create Service
The `service_date` must be from 30 days ago to 366 days ahead (so a missed Sunday can still be added and a wrong year is caught; D-049). Moving a service to another date follows the same rule.

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

**Response `201`:** Service resource including the auto-generated `checkin_code`. A code sent in the body is ignored. The service starts closed. Two services on the same day are allowed.

---

### Service Schedules (weekly)
A church sets a schedule up once ("every Sunday 9:00, Sunday Morning Service") and the services are made ahead of time (D-050). Permission `members.edit` (church admin and pastor) for all three.

```
GET  /api/v1/service-schedules          ?is_active=1&per_page=25   (by name, paginated)
POST /api/v1/service-schedules          (Idempotency-Key required)
PUT  /api/v1/service-schedules/{id}     (any subset of the fields)
```
**Body:** `name` (required), `type` (required: `sunday`, `midweek`, `special`, `youth`, `children`), `weekday` (required: `sunday` to `saturday`), `starts_at` and `ends_at` (`H:i`, optional; the end after the start), `is_active` (default true). **Each schedule:** `id`, `name`, `type` and `type_label`, `weekday` and `weekday_label`, `starts_at`, `ends_at`, `is_active`, `created_at`.

- **Making services.** When a schedule is set up or changed, and again every day at 05:00 UTC (`services:generate-scheduled`), the next 8 weeks of its services are made (`config/attendance.php`), starting with the first of its weekday from today in the church's own calendar (today counts). A date gets one service per schedule, ever: a unique index on (schedule, date) counts cancelled services too, so a date that was cancelled is never made again. A service made by a schedule is an ordinary service: it starts closed, has its own check-in code, and can be opened, edited and cancelled like any other. A schedule off makes none.
- **Changing a schedule.** The change is carried to its future services that nobody has touched (not open, never opened, nothing recorded at them) and, for each field, only to those that still have the old value, so a service renamed by hand keeps its name. A new `weekday` moves those services to the first such day on or after their date (a service stays put if the schedule already has, or had, a service on the new date). Past services never change.
- **Turning a schedule off** (`is_active: false`) stops new services. The ones already made stay; cancel them one by one (`DELETE /services/{id}`). Turning it on again picks up from today.
- There is no delete: a schedule that is no longer wanted is turned off. `404` for a schedule of another church.

### Cancel Service
```
DELETE /api/v1/services/{id}
Authorization: Bearer {token}
Permission: members.edit
```
Takes a service off the calendar, for example a Sunday the church does not meet. **Response `204`**, no body. The service is soft-deleted, never erased: it disappears from the list and `GET /services/{id}` is `404`, its check-in code is free again, and a weekly schedule will not make that date again (D-050). It is refused with `409` when check-in is still open ("Close it before cancelling") and when anything is recorded at it: a member or visitor checked in, or a gift, voided ones included ("Close it instead"), because that history must stay reachable. `404` for a service of another church; `403` without `members.edit` (church admin and pastor).

### Open / Close Service for Check-in
```
PATCH /api/v1/services/{id}/checkin
Authorization: Bearer {token}
Permission: members.edit
```
Asking for the state the service is already in changes nothing. **Check-in can only be opened on the day of the service** in the church's own calendar (`409` "Check-in can only be opened on the day of the service." for a service dated another day, D-052); closing is always allowed, and two services on the same day may both be open. A service left open is closed by the hourly `services:close-stale` command once its day has ended (24 hours, `config/attendance.php`).

**Body:**
```json
{ "is_open": true }
```

---

### Record Attendance (Manual)
```
POST /api/v1/services/{id}/attendance
Authorization: Bearer {token}
Permission: members.edit
```

**Body:**
```json
{
  "member_ids": ["ulid1", "ulid2", "ulid3"],
  "method": "manual"
}
```
`member_ids` has 1 to 500 different ids (`config/attendance.php`). `method`, when sent, can only be `manual`: the QR code and WhatsApp check-ins have their own entrances.

**Response `201`:**
```json
{
  "recorded": 3,
  "already_checked_in": 0,
  "errors": []
}
```
It answers `201` even when everyone was already checked in; the counts say what happened. Checking in twice is not an error: the second time is counted in `already_checked_in` and no row is added (a unique index in the database, so two requests at once also leave one row). An id that is not a member of this church, or belongs to a deceased member, is listed in `errors` as `{ "member_id", "message" }` and does not stop the others. Inactive members can be checked in, because they came. It works on a closed service, so a missed person can be added afterwards (QA-05 TC-05-14); there is no note field for that in v1. `404` for a service of another church.

---

### Capture Visitor
```
POST /api/v1/services/{id}/visitors
Permission: members.edit
```
**Body:** `{ "name": "Yaw Boateng", "phone": "0244999000", "how_heard": "A friend" }`. Only `name` is required.

**Response `201`:** the visitor: `id`, `name`, `phone`, `how_heard`, `first_visit_date`, `last_visit_date`, `visit_count`, `follow_up_status` (`pending`) and its label. The visitor is counted at the service (an attendance row with their name, apart from members).

A phone number that matches an earlier visitor of the church makes this a return visit (`visit_count` goes up, `last_visit_date` moves) instead of a new record; sending the same form twice for one service counts it once. Without a phone number there is nothing to match, so each form is a new record. Visitor details are personal data kept for follow-up; they are removed when a cancelled church is purged (D-042).

---

### QR Code Check-in

The church shows a QR code on a screen at the venue. A member scans it with the phone camera, enters their phone number, is texted a code and confirms. The QR code proves they are at the venue; the texted code proves the phone is theirs. Neither needs an app or a sign-in.

```
GET /api/v1/services/{id}/qr                 (staff, members.edit)
```
**Response `200`:** `token`, `url` (the address to put in the QR code: `https://{church}.yourapp.com/checkin/{token}`), `expires_at`, `expires_in` (120 seconds) and `refresh_in` (60). Ask again every `refresh_in` seconds; older codes keep working until they expire, so a member who is about to scan is not turned away. `409` when the service is not open for check-in.

```
GET  /api/v1/checkin/qr/{token}             (public) which service the code is for: name, type_label, service_date, is_open
POST /api/v1/checkin/qr/otp                 (public) { "token": "...", "phone": "0244123456" }
POST /api/v1/checkin/qr                     (public) { "token": "...", "phone": "0244123456", "otp": "123456" }
```
- An unknown or expired code is `404` ("This code has expired. Please scan the screen again."). A closed service is `409`.
- The request for a code answers `200` the same for any phone number and sends the text only to a member of this church who is not deceased, so nobody can find out who attends. The text goes on the `critical` queue and the code is kept as a hash, valid 10 minutes, five tries, then a 15-minute lockout (the same rules as sign-in). A code belongs to one phone and one service.
- **Rate limits (cost control):** one text per phone and code per minute, five per hour, 20 per IP address per hour; ten verifications a minute per IP address.
- The check-in answers `200`: `{ "checked_in": true, "already_checked_in": false, "service_name": "...", "first_name": "Emmanuel" }`. Scanning twice is harmless (`already_checked_in: true`, one record). Nothing else about the member or the congregation is returned.

---

### WhatsApp Check-in (through the webhook)

There is no endpoint for the app to call. A member texts `CHECKIN` or `CHECKIN GRA7F2` (the service's check-in code) to the platform's one WhatsApp number, and Meta posts it to the webhook below. A worker then checks them in and answers on WhatsApp. The webhook answers `200` at once and the work happens on the `notifications` queue (D-045).

- **Command:** the word `CHECKIN` in any capitals, then an optional service code. Anything else a member sends is ignored (no answer). Images, reactions and delivery receipts are ignored too.
- **Who:** the sender's phone is matched to a living member (inactive members can check in; a deceased member cannot) of any active church, because one shared number serves every church. The code names one service in one church. Without a code it is the service opened first in the member's church (QA-05 TC-05-13); a number that is a member of two churches that both have a service open must send a code.
- **Answers** (wording in `config/whatsapp.php`): the welcome `✅ Checked in to {service} — {date}. Welcome, {name}! God bless you. 🙏`; `You're already checked in! See you inside. 🙏`; `We couldn't find your number in our records. Please contact the church office.` (unknown number, a code of another church, a deceased member, a switched-off church: all the same words, so nothing is revealed); `No service is currently open for check-in.`; and, for the two-church case, a request to add the code.
- **Twice:** Meta's message id is stored in the same transaction as the attendance, so a repeat delivery is ignored, and the unique row per member and service is the second guard. A member who sends `CHECKIN` again gets the "already checked in" answer.
- **Agreement:** a member's first message counts as agreeing to WhatsApp messages (docs/14, Opt-In): `whatsapp_opted_in` is set and a consent record is written (D-041).
- **Cost control:** a number gets at most 5 answers a minute (`config/whatsapp.php`); past that the check-in still counts but nobody answers.

---

### Get Attendance for a Service
```
GET /api/v1/services/{id}/attendance?per_page=25
Authorization: Bearer {token}
```
Every signed-in role may read it (QA-10), with two limits that protect people: visitors' names are shown only to staff who may edit members (church admin, pastor), and everyone else sees only the members they are allowed to see in the directory. The `summary` is a headcount, not a list of people, so it always counts everyone.

**Response `200`** (paginated like every list):
```json
{
  "service": { "id": "ulid", "name": "Sunday Morning Service", "service_date": "2026-10-26", "...": "..." },
  "summary": {
    "total": 124,
    "members": 118,
    "visitors": 6,
    "by_method": { "manual": 80, "whatsapp": 30, "qr": 14, "biometric": 0 },
    "active_members": 140,
    "attendance_rate": 84
  },
  "data": [
    { "id": "ulid", "method": "whatsapp", "method_label": "WhatsApp", "checked_in_at": "2026-10-26T09:12:03+00:00",
      "member": { "id": "ulid", "member_number": "GRC-0042", "full_name": "Abena Asante" }, "visitor_name": null }
  ],
  "meta": { "current_page": 1, "per_page": 25, "total": 124, "last_page": 5 }
}
```
Newest check-in first. `attendance_rate` is the whole percent of active members who were checked in (members only, visitors not counted), rounded to the nearest percent.

### Get Member Attendance
```
GET /api/v1/members/{id}/attendance?weeks=12
```
`weeks` is 1 to 52 (default 12). **Response `200`:** `weeks`, `services_held` (services in the window that anyone was checked in to), `attended`, `attendance_rate` (whole percent, `0` when nothing was held) and `records` (each `service_id`, `service_name`, `service_date`, `method`, `checked_in_at`). `404` for another church's member; `403` if the viewer may not see the member.

### List Absent Members
```
GET /api/v1/attendance/absent?consecutive=3&per_page=25
Permission: reports.view
```
Active members who missed each of the last `consecutive` services (default: the church's own `absence_threshold` setting, else 3; allowed 2 to 12). Only Sunday services count, because a midweek meeting is always smaller (`config/attendance.php`), and a service nobody was checked in to is treated as not held. A member who joined after the oldest of those services is left out. Each member: `id`, `member_number`, `full_name`, `phone`, `last_attended_at` (null if never). It is worked out when asked, so it is always up to date. The automatic text to these members (church setting `absent_alert`, off until switched on) is the daily command `attendance:alert-absent`, not an endpoint: see D-067.

---

## Contributions

### List Contributions
```
GET /api/v1/contributions
Authorization: Bearer {token}
Permission: giving.view
```
Church admin and finance secretary have `giving.view`; the pastor does not and gets `403` (QA-06 TC-06-18): the pastor sees totals through the reports, which need `giving.summary` (D-046). Newest first by the day the giving happened, in pages (`per_page`). Each gift: `id`, `member` (`id`, `full_name`) or `null`, `is_anonymous`, `service` (`id`, `name`, `service_date`) or `null`, `fund_type` (`id`, `name`: the fund's name when the gift was recorded, D-048), `amount` (a decimal string such as `"150.00"`) with `amount_formatted` (`"GHS 150.00"`), `payment_method` and its label, `is_voided`, `voided_at`, `void_reason`, `voided_by`, `recorded_by`, `notes`, `contributed_at`, `created_at`.

**Query params:**
| Param | Type | Example |
|---|---|---|
| `member_id` | ulid | Filter by member |
| `service_id` | ulid | Filter by service |
| `fund_type_id` | ulid | |
| `payment_method` | string | `momo`, `card`, `cash` |
| `from` | date | `2026-01-01` |
| `to` | date | `2026-12-31` |
| `is_voided` | boolean | `false` (default: voided gifts are left out; `true` lists only the voided ones) |
| `status` | string | `completed` (default: pending and failed digital payments are left out), `pending` or `failed` to see them (D-053) |

---

### Get Contribution
```
GET /api/v1/contributions/{id}
Authorization: Bearer {token}
Permission: giving.view
```
`404` for a gift of another church.

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

**Header:** `Idempotency-Key` is required (see Conventions), so a double click records one gift.

**Rules:** `fund_type_id` and `amount` are required. `pledge_id` is optional and names the pledge this gift pays (see How a pledge is paid). The fund must be one of this church's and switched on; `member_id` and `service_id` must belong to this church, and a member who has died cannot be given a gift (an inactive member can). A gift with no `member_id` is anonymous. `amount` is GHS with at most two decimals, above 0 and at most 999,999.99 (`config/giving.php`). `payment_method` must be `cash`: mobile money and cards start a payment instead (below). `contributed_at` is when the giving happened, never in the future, and defaults to now; it is kept apart from the day the record was entered. The church comes from the sign-in, never from the body. The gift and its change-history line are saved together.

**Response `201`:** Full contribution resource. Cash has no processing fee and no payment reference, and its `status` is `completed` from the start. `422` for bad values, `400` without an `Idempotency-Key`, `403` without `giving.record`.

**Side effect:** the `ContributionReceived` event fires, and a queued listener texts the member a receipt after the commit (docs/04 Giving Receipts, D-051): not for an anonymous gift, not when the church switched `receipt_on_cash` off, not for a member who has died, has no phone or turned SMS off, and not when the church has too few SMS credits (a warning is logged; this request is never failed by it). Each receipt is one `message_logs` row of type `contribution_receipt` and costs one credit per part of the text.

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

The contribution row is created now with `status: pending`, and `reference` is its `id` (D-053). When the provider confirms, by webhook or by our polling fallback, it becomes `completed` (the receipt and payout follow) or `failed` (nothing follows, and the finance secretary can record the gift as cash instead). Only `completed` gifts count in totals, reports and pledges. Do not create a duplicate record manually; a retry with the same `Idempotency-Key` returns the same `reference`.

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

**Response `200`:** Updated contribution resource with `is_voided: true`, `voided_at`, `void_reason` and `voided_by`. The reason is required (3 to 2,000 characters). A gift is never deleted (`DELETE` is `405`); it stays, marked, and drops out of every total. Voiding twice is `422` ("This contribution has already been voided."); the row is locked while it is checked, so two voids at the same moment cannot both succeed. Each void is written to the change history with its reason. To correct an amount, void the gift and record a new one. `403` without `giving.edit`, `404` for a gift of another church.

---

### Sunday Offering Report
```
GET /api/v1/services/{id}/offering-report
Authorization: Bearer {token}
Permission: giving.summary
```
What one service received, by fund and by payment method. The church admin, the finance secretary and the pastor may read it (QA-10): it holds totals only, with no giver's name or id. `404` for a service of another church.

**Response `200`:**
```json
{
  "data": {
    "service": { "id": "ulid", "name": "Sunday Morning Service", "service_date": "2026-10-26" },
    "totals": {
      "grand_total": "3150.00",
      "grand_total_formatted": "GHS 3,150.00",
      "by_fund": [
        { "fund_type": "Tithe", "by_method": { "cash": "1200.00", "momo": "850.00", "card": "0.00", "ussd": "0.00" }, "by_method_formatted": { "cash": "GHS 1,200.00", "momo": "GHS 850.00", "card": "GHS 0.00", "ussd": "GHS 0.00" }, "total": "2050.00", "total_formatted": "GHS 2,050.00" },
        { "fund_type": "Offering", "by_method": { "cash": "430.00", "momo": "220.00", "card": "0.00", "ussd": "0.00" }, "total": "650.00", "total_formatted": "GHS 650.00" }
      ],
      "by_method": { "cash": "1930.00", "momo": "1220.00", "card": "0.00", "ussd": "0.00" },
      "by_method_formatted": { "cash": "GHS 1,930.00", "momo": "GHS 1,220.00", "card": "GHS 0.00", "ussd": "GHS 0.00" },
      "transaction_count": 42,
      "anonymous_count": 7
    },
    "no_service": {
      "counted_here": true,
      "grand_total": "75.50",
      "grand_total_formatted": "GHS 75.50",
      "by_fund": [],
      "by_method": { "cash": "0.00", "momo": "50.00", "card": "25.50", "ussd": "0.00" },
      "transaction_count": 2,
      "anonymous_count": 1
    },
    "day_grand_total": "3225.50",
    "day_grand_total_formatted": "GHS 3,225.50"
  }
}
```
- Every amount is an exact decimal string (GHS), never a float. Each `by_method` object has a `by_method_formatted` sibling with the same four keys as ready-to-show text, so the app never formats money itself (it sits beside `by_method` rather than replacing it, which keeps version 1 unchanged). Within one block `grand_total`, each fund `total` and every `by_method` are sums of the same gifts, so they always agree.
- **`totals`** are the gifts recorded **at this service**. They equal the sum of `GET /contributions?service_id=` for the same service.
- **`no_service`** are the gifts given **that day with no service** (mobile money and card gifts, and cash nobody attached to a service). "That day" is the church's own day in its timezone. They are counted with the **day's first service only** (the earliest start time; a service with no start time comes after the ones that have one; then the lowest id), so a day with two services never counts a gift twice. For any other service of the day `counted_here` is `false` and the block is all zeros. `day_grand_total` is `totals` plus `no_service`, so adding up the `day_grand_total` of every report of a day gives the day's giving exactly once. A day with gifts but no service has no report.
- **Voided gifts are in no number**, including the counts (QA-06 TC-06-17).
- Every payment method is listed for every fund, even at `"0.00"` (`ussd` stays `"0.00"` until USSD giving exists, D-019), so no gift can fall outside the columns. Funds are listed in their display order and only if they received something.
- **Fund names are the names the gifts were given to** (D-048). A fund renamed later does not change an old report; a fund renamed in the middle of one service's gifts shows as two lines, one per name.
- `anonymous_count` is the gifts with no member. The printable page and the PDF come with the screens (story 4.16).

---

## Pledges

### List Pledges
```
GET /api/v1/pledges
Authorization: Bearer {token}
Permission: giving.view
```

A pledge names a member and an amount, so it is individual giving data like a gift: church admin and finance secretary have `giving.view`, the pastor does not and gets `403` (D-046, D-047).

**Query params:** `?member_id=ulid&status=active&fund_type_id=ulid&per_page=25`. Paginated, those due soonest first. Each pledge: `id`, `member` (`id`, `full_name`), `fund_type` (`id`, `name`), `description`, `amount_pledged`, `amount_paid` and `amount_outstanding` (each an exact decimal string with a `_formatted` sibling such as `"GHS 1,000.00"`), `progress_percent` (0 to 100, a part of a percent is dropped), `target_date`, `status` and `status_label`, `created_at`.

### Get Pledge
```
GET /api/v1/pledges/{id}
Permission: giving.view
```
Returns the pledge and `contributions`: every gift that pays it, newest first, voided ones included and marked `is_voided: true` (they do not count toward `amount_paid`). `404` for a pledge of another church.

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
**Header:** `Idempotency-Key` is required (see Conventions). **Rules:** the member and fund must belong to this church; a member who has died cannot make a pledge (inactive is fine) and a fund that is switched off cannot be chosen. `amount_pledged` is GHS above 0 with at most two decimals and at most 999,999.99. `target_date` is `YYYY-MM-DD`, today or later. **Response `201`:** the pledge, `status: active`, `amount_paid: "0.00"`. The creation is written to the change history.

### Update Pledge
```
PUT /api/v1/pledges/{id}
Permission: giving.edit
```
Any of `description`, `amount_pledged` and `target_date` (same rules, except the date may be any date). The member and fund cannot be changed, because gifts are linked to them; `amount_paid` and `status` cannot be set by hand. The paid amount and status are recalculated in the same transaction (for example lowering the amount to what was paid fulfils the pledge) and the change, with old and new values, is written to the change history. There is no delete: a pledge that is no longer wanted is lowered to what was paid.

### How a pledge is paid, and its status (D-047)
A cash gift names its pledge with `pledge_id` (see Record Cash Contribution). The pledge must be one of this church's, for the same member and the same fund as the gift, and not already paid in full; otherwise the gift is `422` on `pledge_id`. Nothing is linked by matching a member and a fund.

`amount_paid` is not counted up or down. After a gift is recorded against a pledge, or voided, the pledge is locked and its paid amount is added up again from its gifts that are not voided, so it cannot drift, a void is undone exactly, and two gifts at the same moment take turns.

Status: `fulfilled` as soon as `amount_paid` reaches `amount_pledged` (even after the date); otherwise `active` until the target date has passed; then `partial` if something was paid or `lapsed` if nothing was. A daily job (`pledges:refresh`, 04:00 UTC) moves pledges over when their date passes. Paying a lapsed or partial pledge in full fulfils it; voiding a gift can move a fulfilled pledge back. A pledge can be paid over (`amount_outstanding` never goes below `"0.00"`).

---

## Communications

### Estimate a Broadcast
```
POST /api/v1/messages/broadcast/estimate
Authorization: Bearer {token}
Permission: sms.send
```
Says who a broadcast would reach and what it would cost, so the compose page can show "This message will be sent to 48 members and use about 48 SMS credits. You have 50 credits." as the text is typed (QA-08 TC-08-08, D-065). **Nothing is queued, logged or charged.** It takes the same body as Send Broadcast below.

**Response `200`:**
```json
{ "recipients": 48, "parts_per_message": 1, "credits_needed": 48, "credit_balance": 50, "enough_credits": true }
```
**Who is reached:** members of the `recipient_filter.status` (default `active`) and, if given, of the `recipient_filter.department_id`, who have a phone number and have not turned SMS off. A deceased member is never reached, whatever the status asked for. **What it costs:** one credit is one part of the text for each person (D-051): 160 characters in the plain alphabet (153 each when longer), or 70 (67 each) if the text has any letter outside it, such as ɔ or ɛ. A text may use at most 4 parts (`config/messages.php`). **Errors:** `422` for a missing or empty `message`, a text over the part limit, a `channel` other than `sms` (WhatsApp broadcasts are not open yet), a `status` that does not exist or a `department_id` that is not an id; `403` without `sms.send`.

### Send Broadcast
```
POST /api/v1/messages/broadcast
Authorization: Bearer {token}
Permission: sms.send
Idempotency-Key: <one new value for each broadcast>
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
`recipient_filter` is optional (all active members). The body is exactly the one Estimate a Broadcast takes, and the same rules apply: who is reached, the cost, the part limit and `channel` being `sms` for now.

**Response `202`:**
```json
{
  "queued": true,
  "broadcast_id": "ulid",
  "estimated_recipients": 87,
  "estimated_credit_cost": 87,
  "current_credit_balance": 420
}
```
The broadcast is recorded (who sent it, the text, the filter, the parts) and a worker on the `notifications` queue turns it into **one text for each member**, as things are when it runs: a `queued` message log line (`message_type: broadcast`, shown in Get Message Log) and an SMS job. **Credits are taken text by text just before each is sent (D-051)**, one for each part, and given back if a text fails for good, so a broadcast can never overspend the church. The worker is safe to run twice: a member who already has a line for the broadcast is skipped, and the database allows only one.

**Errors:** `400` without an `Idempotency-Key` (the same key again returns the first answer and sends nothing more); `422` `{"message": "Insufficient SMS credits. You need 60 credits, you have 30. Purchase more credits to proceed."}` when the church cannot pay (nothing is recorded); `422` `{"message": "No members match: there is nobody to send this to."}`; `422` per field as for the estimate; `403` without `sms.send`; `429` `{"message": "You can send at most 10 broadcasts per hour."}` with `Retry-After` (for the whole church).

---

### Get Message Log
```
GET /api/v1/messages
Authorization: Bearer {token}
Permission: sms.send
```
Church admin and pastor only (D-055): the log holds phone numbers and message text. Anyone else gets `403`.

**Query params:** `?channel=sms&message_type=broadcast&from=2026-10-01&to=2026-10-31&per_page=25`. `channel` is `sms`, `whatsapp` or `email`; `from` and `to` are whole days (`Y-m-d`, `to` not before `from`).

**Response `200`:** a page (newest first) of
```json
{
  "id": "ulid", "channel": "whatsapp", "channel_label": "WhatsApp",
  "recipient_type": "member", "recipient_id": "ulid", "recipient_phone": "+233244123456", "recipient_email": null,
  "message_type": "checkin_reply", "content": "You are checked in.",
  "status": "sent", "status_label": "Sent", "cost_units": "1.0000",
  "sent_at": "2026-11-05T14:30:00+00:00", "created_at": "2026-11-05T14:30:00+00:00"
}
```
Only the church's own messages are listed (another church's, and the sign-up code that has no church, never are). The provider's reference and raw answer are not shown. **The codes are left out** (`config/messages.php`, `hidden_types`): sign-in, password reset, invitation, check-in and sign-up codes, the breached-password warning and the export-ready email (it holds a link) carry no text worth reading and would only list other people's phone numbers and emails; they stay in the table for support until the 12-month clean-up.

---

## Platform (the owner's own side)

> **D-069.** Everything here is on the platform host (the bare base domain), never on a church's address (`404` there), and has nothing to do with any church: it is for the platform owner's own accounts. A church's staff can never sign in to it (`401`), and a platform admin has no access to any church (`401` on every church call). It is used from a browser: a sign-in is an httpOnly session cookie, there are no tokens.

```
POST /api/v1/platform/auth/login          { "email", "password" }   -> 200 { "message": "Code sent", "expires_in": 600 }
POST /api/v1/platform/auth/login/verify   { "email", "code" }       -> 200 { "data": { "id", "name", "email" } }
GET  /api/v1/platform/auth/me             -> 200 { "data": { "id", "name", "email" } }     (signed in)
POST /api/v1/platform/auth/logout         -> 204                                            (signed in)
```
Sign-in is the same two steps as church staff (D-054): the right password emails a 6-digit code (10 minutes, single use), and the code signs the browser in. A wrong password, an unknown email and a switched-off account all give one `422` "Invalid email or password" and send nothing; five wrong passwords for an email, or five wrong codes, lock it for 15 minutes (`429`); asking for a code is limited to 1 a minute and 5 an hour for one email and 20 an hour from one address (`429`, `Retry-After`). The codes and counts are the platform's own, so a church's code never opens this side.

There is no sign-up. An account is created on the server with `php artisan platform:create-admin {email} {name}`, which asks for the password (at least 12 characters, never given as an argument) and refuses an email already in use. An account is switched off, never deleted.

### SMS Credit Packages
```
GET  /api/v1/platform/sms-packages                 paginated; every package, switched off ones too
POST /api/v1/platform/sms-packages                 { "name", "credits", "price", "sort_order"?, "is_active"? }   -> 201
PUT  /api/v1/platform/sms-packages/{id}            any subset of the same fields                                -> 200
```
The bundles of SMS credits a church can buy (D-069, story 5.4), set by the platform owner. Signed-in platform admins only (`401` for anyone else, a church's staff included). Listed by `sort_order` (lowest first), then name. Each package: `id`, `name`, `credits`, `price` (an exact decimal string in GHS, for example `"12.50"`) with `price_formatted` (`"GHS 12.50"`), `is_active`, `sort_order`.

**Rules:** `name` is required, up to 100 characters, and unique **whatever its capitals** (`422`; the database holds it too). `credits` is a whole number from 1 to 1,000,000. `price` is above 0, with at most two decimals, up to 99,999.99: a free package, a fraction of a pesewa and an extra zero are refused (`config/credits.php`). `sort_order` is 0 to 1000 (default 0) and `is_active` defaults to true. An update changes only the fields sent. There is **no delete**: a package no longer sold is switched off with `is_active: false`, because purchases will point at it. A church sees only the active ones, when buying is built.

---

## SMS Credits (buying credits, D-069, story 5.4)

Permission for all three: `church.settings` (the church admin). Other roles get `403`; a purchase of another church is `404`.

### List the Packages on Sale
```
GET /api/v1/sms-credits/packages
```
The packages the platform owner has switched on, in the owner's order. **Response `200`:** `{ "data": [ { "id", "name", "credits", "price": "12.50", "price_formatted": "GHS 12.50" } ] }` (empty when nothing is on sale).

### Start a Purchase
```
POST /api/v1/sms-credits/purchases
Idempotency-Key: <8 to 100 letters, digits, dashes or underscores>   (required: `400` without it)
{ "sms_package_id": "ulid", "phone": "0244123456", "network": "mtn" }
```
`phone` is the MoMo number the payment prompt goes to (a local number is turned into `+233...`); `network` is `mtn`, `telecel` or `at`. `422` when the package is missing, not on sale, or the phone or network is wrong. **Response `202`:** `{ "data": { "id", "sms_package_id", "credits", "amount": "12.50", "amount_formatted", "network", "status": "pending", "failure_reason": null, "created_at", "completed_at": null } }`; the phone number is never sent back.

**Side effects:** records the purchase as `pending` with the credits and price copied from the package (a later price change never changes it) and queues the call to PayPerlez (queue `payments`), which sends the prompt to the phone. Nothing is charged and no credits are added by this request. When PayPerlez confirms the exact amount (by the check that runs every minute), the church's credits go up and one `topup` line is written in the credit ledger, once; if the payment fails the purchase becomes `failed` and nothing is added. The same `Idempotency-Key` again returns the first answer and starts nothing more. The giver also pays PayPerlez's own fee on top of the package price.

### Get a Purchase
```
GET /api/v1/sms-credits/purchases/{id}
```
**Response `200`:** the same shape as above, so a screen can follow `status` (`pending`, `completed`, `failed`) and read `failure_reason`. The new balance is in the signed-in staff's church (`sms_credits`).

---

## Recent Activity

### Get Recent Activity
```
GET /api/v1/activity?per_page=10
Authorization: Bearer {token}
Permission: reports.view
```
The change history as plain sentences for the home page, newest first, paged (D-068). Church admin, pastor and finance secretary may read it, but each kind of line is shown only to people who also hold the permission that guards what it is about, so the page and `meta.total` count only what the person may see.

**Response `200`:** a page of
```json
{ "id": "ulid", "kind": "gift_recorded", "text": "A gift of GHS 1,250.50 to Building Fund was recorded.", "actor_name": "Kofi Admin", "occurred_at": "2026-11-09T09:00:00+00:00" }
```
| `kind` | Shown to | Sentence |
|---|---|---|
| `member_added` | `members.view` | "Kwame Boateng was added as a member." |
| `member_status_changed` | `members.view` | "Kwame Boateng is now Inactive." |
| `gift_recorded`, `gift_voided` | `giving.view` (not the pastor) | "A gift of GHS 1,250.50 to Building Fund was recorded." |
| `pledge_made` | `giving.view` | "A pledge of GHS 3,000.00 to Building Fund was made." |
| `staff_invited`, `staff_status_changed` | `staff.manage` | "Esi Owusu was invited as staff." / "was deactivated." / "was reactivated." |
| `settings_changed` | `church.settings` | "The church details or settings were changed." |

A sentence holds only a name, an amount and a fund: never a void reason, a note, an email, a phone number, a member's name beside a gift, or the old and new values. A member whose data was erased is "A member". Member edits, record views, exports and erasures are not shown. `422` for `per_page` outside 1 to the largest page; `401` without a token; `403` without `reports.view`.

---

## Reports

### Attendance Trends
```
GET /api/v1/reports/attendance
Authorization: Bearer {token}
Permission: reports.view
```
Church admin, pastor and finance secretary (`reports.view`). It is worked out when asked, so it is always current.

**Query params:** `?weeks=12&group_by=week`. `weeks` is how far back to look, 1 to 52 (default 12, `config/reports.php`), counting the current week. `group_by` is `week` (default) or `service`.

**Response, `group_by=week`:** one row for each ISO week (Monday to Sunday, in the church's own time zone), newest first. A week with no service is a row with `total: 0`, never left out.
```json
{
  "group_by": "week",
  "data": [
    { "week": "2026-W43", "week_start": "2026-10-19", "total": 0, "first_timers": 0, "services": 0 },
    { "week": "2026-W42", "week_start": "2026-10-12", "total": 124, "first_timers": 3, "services": 2 }
  ],
  "summary": { "average": 121, "highest": 156, "lowest": 98 }
}
```
`total` adds up every service of the week; `first_timers` is the visitors whose first visit date is in the week; `services` is how many services were counted.

**Response, `group_by=service`:** one row for each service in the same weeks, newest first.
```json
{
  "group_by": "service",
  "data": [
    { "service_id": "ulid", "service_date": "2026-10-18", "service_name": "Sunday Morning Service", "total": 118 }
  ],
  "summary": { "average": 121, "highest": 156, "lowest": 98 }
}
```

**What counts.** A service's total is its paper headcount (`headcount_override`) if it has one, otherwise everyone checked in, members and visitors together (a removed check-in is not counted). A service counts only once something is recorded for it, and only up to today, so a service nobody recorded and one still to come are left out. The summary (whole numbers, the average rounded) covers only the weeks (or services) in which something was recorded, so empty weeks do not pull it down; with none it is all 0. `422` for `weeks` outside 1 to 52 or an unknown `group_by`.

---

### Giving Trends
```
GET /api/v1/reports/giving
Authorization: Bearer {token}
Permission: giving.summary
```
Church admin, pastor and finance secretary (`giving.summary`, D-046). It holds **totals only**: no member and no individual gift is named, which is why the pastor may read it although not the gift list.

**Query params:** `?months=12&fund_type_id=ulid`. `months` is how far back to look, 1 to 24 (default 12, `config/reports.php`), counting the current month. `fund_type_id` narrows every figure to one fund.

**Response `200`:** one row for each calendar month in the church's own time zone, newest first. A month with nothing given is `"0.00"` with no funds, never left out.
```json
{
  "data": [
    {
      "month": "2026-10", "month_start": "2026-10-01",
      "total": "15450.00", "total_formatted": "GHS 15,450.00",
      "by_fund": [
        { "fund_type_id": "ulid", "name": "Tithe", "total": "8000.00", "total_formatted": "GHS 8,000.00" },
        { "fund_type_id": "ulid", "name": "Building Fund", "total": "7450.00", "total_formatted": "GHS 7,450.00" }
      ]
    }
  ],
  "summary": {
    "total": "98200.00", "total_formatted": "GHS 98,200.00",
    "previous_total": "90000.00", "previous_total_formatted": "GHS 90,000.00",
    "change_percent": 9.1
  }
}
```
Every amount is an exact decimal string in cedis with a formatted sibling (never a float). Gifts that were voided are left out. `by_fund` lists the funds that received something that month, in the funds' display order, under each fund's current name. The summary sets the whole period against the **same number of months immediately before it**: `change_percent` is the rise or fall to one decimal place (`-66.7` is a fall), and `null` when nothing was given in those months. `422` for `months` outside 1 to 24 or a `fund_type_id` that is not an id.

---

### Member Growth
```
GET /api/v1/reports/members
Authorization: Bearer {token}
Permission: reports.view
```
Church admin, pastor and finance secretary (`reports.view`, D-055 point 5). Counts only: no member is named.

**Query params:** `?months=12`: how far back to look, 1 to 24 (default 12, `config/reports.php`), counting the current month.

**Response `200`:** one row for each calendar month in the church's own time zone, newest first. A quiet month is a row of zeros, never left out.
```json
{
  "data": [
    { "month": "2026-10", "month_start": "2026-10-01", "new": 4, "lost": 1, "net": 3, "active_total": 212 },
    { "month": "2026-09", "month_start": "2026-09-01", "new": 0, "lost": 0, "net": 0, "active_total": 209 }
  ],
  "summary": { "new": 14, "lost": 5, "net": 9 }
}
```
What the numbers mean (D-063): **`new`** is the people who became *active* that month (added as active, a prospect confirmed as a member, or a member who came back); a prospect is not counted until they are confirmed. **`lost`** is the active members who left active membership that month (to inactive, transferred, deceased or archived, or back to prospect, so the figures add up). **`net`** is `new` minus `lost`. **`active_total`** is the number of active members at the end of the month: this month's is today's real count and each earlier month is worked back from the next. It is read from the member change history, so a member whose data was erased has no readable history and is not in the earlier months. `422` for `months` outside 1 to 24.

---

### Export Members (CSV)
```
POST /api/v1/exports/members
Authorization: Bearer {token}
Permission: members.export
```
Church admin and finance secretary by default (D-055). The national ID, the address, the landmark and the pastoral notes are never in the file (D-064).

**Body:** The filters of `GET /members`: `status`, `gender`, `educational_level`, `department_id`, `family_id`, `search`. Nothing else is read: the file is always in name order and holds everyone the filters find, with the same default as the list (prospects and active members unless a `status` or a `search` is given). A value that is not valid is `422`.

**Response `202`:**
```json
{
  "export_id": "ulid",
  "message": "Export queued. You will receive a download link by email when it is ready."
}
```
The file is built by a worker on the `exports` queue, stored privately at `churches/{church_id}/exports/{export_id}/members-{date}.csv`, and the staff member who asked is **emailed a link that works for 1 hour** (`config/exports.php`). The link is never kept in the message log. The file is deleted after 7 days; the record of the export (who, when, filters, rows) stays. If the link expires, ask again.

**Columns:** `id, member_number, first_name, last_name, phone, email, status, gender, date_of_birth, department, family, joined_date, educational_level`. Phones are in international form (`+233...`), dates are `YYYY-MM-DD`, a member in several departments has them joined with `; `. The file is UTF-8. A text that a spreadsheet would run as a formula (it starts with `=`, `+`, `-` or `@`) has a leading `'`, so a member named `=HYPERLINK(...)` cannot run anything; the phone column is left as it is.

**Errors:** `403` without `members.export`; `422` for a bad filter; `429` after 5 exports in an hour by the same staff member: `{"message": "You can export at most 5 times per hour."}` with `Retry-After`. A church with no members gets a file with only the header.

---

### Export Contributions (CSV)
```
POST /api/v1/exports/contributions
Authorization: Bearer {token}
Permission: giving.export
```
Church admin and finance secretary (`giving.export`; the pastor sees giving totals but not individual gifts, so not this, D-046). It works exactly like the member export above (D-064): `202` with an `export_id`, a worker on the `exports` queue builds the private file, the staff member is emailed a link that works for 1 hour, the file is deleted after 7 days, `403` without the permission, and the same limit of 5 exports an hour (members and giving records together) with `429`.

**Body:** The filters of `GET /contributions`: `member_id`, `service_id`, `fund_type_id`, `payment_method`, `from`, `to` (whole days, `Y-m-d`, `to` not before `from`). The voided switch and the page size do not apply: the file **always includes voided gifts, flagged**, so it can be audited. A value that is not valid is `422`.

**Columns:** `id, date, member_name, fund_type, amount_ghs, payment_method, reference, is_voided, recorded_by`, **oldest first**. `date` is the day the gift was given in the church's own time zone; `member_name` is the member's name, `Anonymous` for a gift with no member, and `[deleted]` for a member whose data was erased; `fund_type` is the fund's name when the gift was recorded (a later rename does not change it, D-048); `amount_ghs` is an exact two-place decimal in cedis; `reference` is the payment provider's reference for a digital gift and empty for cash; `is_voided` is `true` or `false`. The notes, the void reason and phone numbers are never in the file. A text starting with `=`, `+`, `-` or `@` gets a leading `'`, as in the member export. A church with no gifts gets a file with only the header.

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
Permission: `church-admin` only (`fund-types.manage`).

**List:** every signed-in role may read it (the giving screens and reports show fund names). Paginated (`per_page`), in display order (`sort_order`, then name), optional `?is_active=1`. Each fund: `id`, `name`, `description`, `is_active`, `sort_order`.

**Body (create; any subset on update):** `name` (required on create, up to 100 characters), `description`, `sort_order` (0 to 1000), `is_active`. PUT and PATCH do the same thing: change the fields that were sent.

- A name is unique among a church's fund types **whatever its capitals** ("Tithe" and "TITHE" clash); another church may use it. A clash is `422` on `name`, and a unique index in the database holds it when two requests race.
- There is no delete. A fund that is no longer used is switched off (`is_active: false`): it stays in the history and past gifts keep pointing at it.
- Every new church starts with the funds in `config/giving.php`: Tithe, Sunday Offering, Building Fund, Benevolence, Missions, Special Offering and Other. "Pledge" is not a fund: a gift that pays a pledge is linked to the pledge itself.
- A fund type of another church is `404`.

---

## Departments

Permissions: `members.view` to list, `members.edit` for everything else. A department of another church returns `404`.

### List Departments
```
GET /api/v1/departments
```
**Query params:** `is_active` (`0` or `1`), `per_page` (1 to 100, default 25). Paginated, ordered by name. Each department has `id`, `name`, `description`, `head_user_id`, `head` (`{ "id", "name" }` of the staff member who heads it, or `null`), `is_active` and `members_count`; the count only includes members the caller may see (D-031).

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

## Church Profile

### Get Church Profile
```
GET /api/v1/church
Authorization: Bearer {token}
```
Any signed-in staff member may read the details of their own church (D-055). Nothing in them is secret, and payout, billing and credit details are never part of it.

**Response `200`:**
```json
{
  "data": {
    "id": "ulid", "name": "Grace Assembly International", "slug": "grace-assembly", "denomination": "Pentecostal",
    "address": "12 Ring Road", "city": "Kumasi", "region": "Ashanti", "phone": "+233302000000", "email": "office@grace.example",
    "timezone": "Africa/Accra"
  }
}
```
A detail the church never gave is `null`.

### Update Church Profile
```
PUT /api/v1/church
Authorization: Bearer {token}
Permission: church.settings (church admin)
```

**Body:** any of `name` (cannot be emptied, up to 255), `denomination` (255), `address` (1000), `city` (100), `region` (100), `phone`, `email`, and at least one. Sending `null` or an empty text clears an optional detail. The phone is put into international form (`0302111222` becomes `+233302111222`) and the email into lowercase; a bad phone or email is `422`.

**Response `200`:** the same shape as Get Church Profile, after the change. Only the details sent are changed; every change is written to the change history with the old and new values, and sending what is already there saves and writes nothing. `403` for anyone but the church admin. The web address (`slug`), `timezone`, `member_number_prefix`, `plan` and `sms_credits` are not the church's to change here: sending one is `422` (it is refused, not silently ignored). Service times are the weekly schedules (`/service-schedules`), and the logo is not built yet.

---

## Church Settings

### Get Settings
```
GET /api/v1/church/settings
Authorization: Bearer {token}
```
Any signed-in staff member may read the settings of their own church (D-055). Nothing in them is secret.

**Response `200`:** every setting, with the church's own choice or, where it has made none, the default:
```json
{ "data": { "youth_age_limit": 25, "absence_threshold": 3, "receipt_on_cash": true, "absent_alert": false } }
```

### Update Settings
```
PUT /api/v1/church/settings
Authorization: Bearer {token}
Permission: church.settings (church admin)
```

**Body:** at least one of the settings below, nothing else:
```json
{ "settings": { "youth_age_limit": 25, "absence_threshold": 3, "receipt_on_cash": true, "absent_alert": false } }
```
| Setting | Meaning | Allowed | Default |
|---|---|---|---|
| `youth_age_limit` | A member younger than this many years counts as youth (the youth pastor's view, D-031) | whole number, 10 to 40 (`config/members.php`) | 25 |
| `absence_threshold` | How many services in a row a member must miss to be listed as absent (QA-05 TC-05-12) | whole number, 2 to 12 (`config/attendance.php`) | 3 |
| `receipt_on_cash` | Whether a recorded cash gift texts the member a receipt (D-051) | `true` or `false` | `true` |
| `absent_alert` | Whether members who missed that many services in a row are texted automatically, once for each stretch of absence, paid for from the church's SMS credits (story 3.13, D-067) | `true` or `false` | `false` |

**Response `200`:** the same shape as Get Settings, after the change. Only the settings sent are changed; the others are kept. A name that is not a setting, an empty `settings`, or a value outside its range is `422`, so a typo is never silently ignored. Every change is written to the change history with the old and new values (a value the church never chose counts as the default), and sending what is already in force saves and writes nothing. `403` for anyone but the church admin. Other settings in the old sketch (`checkin_method`, `weekly_report_day`, `birthday_alert_days_before`) have no feature behind them yet and are added with that feature.

---

## Payout Account

> **D-069 step d2, story 4.1.** Where the church's digital gifts are paid out. Both need `church.settings` (church admin); anyone else is `403`. It is always the signed-in church's own account.

### Get the payout account
```
GET /api/v1/church/payout-account
```
**Response `200`:**
```json
{ "data": { "status": "verified", "method": "momo", "network": "mtn", "bank_code": null, "account_number_hint": "***456", "account_name": "GRACE ASSEMBLY CHURCH", "verified_at": "2026-11-10T09:30:00+00:00", "error": null } }
```
`status` is `not_set`, `checking` (saved, waiting for the provider), `verified` (the provider named the account) or `failed` (it could not; `error` says why). Digital giving needs `verified`. The number is never sent in full, only `account_number_hint` (the last three digits).

### Set the payout account
```
PUT /api/v1/church/payout-account
```
**Body:** a wallet `{ "method": "momo", "network": "mtn|telecel|at", "account_number": "0244123456" }` (a Ghana number, local or international) or a bank account `{ "method": "bank", "bank_code": "058", "account_number": "0123456789" }` (6 to 20 digits). `network` is refused on a bank and `bank_code` on a wallet.

**Response `202`:** the same shape as Get, with `status: checking`. The check is queued on the `payments` queue (a request never waits on the provider): a worker asks the provider to name the account (`POST /account-name`) and keeps the name and `verified_at`, or the reason in `error`. Changing the account clears the earlier name and check, so giving stays off until the new account is named; saving the same account again changes nothing. A check that finishes after the account was changed again is thrown away. The history records that the account changed, with the number hidden. `422` for anything missing or wrong.

---

## Staff

> **D-055.** All need `staff.manage` (church admin). A staff member of another church is `404`. **Built:** list, invite, change name, phone and role, deactivate or reactivate, and extra permissions.

```
GET   /api/v1/staff                         paginated; each: id, name, email, phone, roles, role_permissions, extra_permissions, is_active, last_login_at
POST  /api/v1/staff                         { "name", "email", "phone", "role" }   (Idempotency-Key required)
PUT   /api/v1/staff/{id}                    any of name, phone, role
PATCH /api/v1/staff/{id}/status             { "is_active": false }
PUT   /api/v1/staff/{id}/permissions        { "grant": ["members.export"], "revoke": ["giving.export"] }
GET   /api/v1/staff/directory               paginated; each: id, name (active staff only)
```
`POST` creates the user without a password, with the given role, and emails an invitation code (`201`; the response is the staff member). The email is made lowercase, the phone is put into international form, and both must not already belong to staff of this church (`422`; a deleted staff member's are free again). The code is a password reset code, so the person enters it in `auth/password/reset` to choose their first password (same attempt limit and lockout, D-054); it expires after 10 minutes, and "Forgot password" sends a new one. The web app has a page for it, `/choose-password` (linked as "Have an invitation code?" on the sign-in page), and the invitation email tells the person where to find it. Invitations are limited to 20 an hour per church (`429`, `Retry-After`). `PUT` changes any of name, phone and role; the email cannot be changed here. A role or permission change applies on the user's next request. Deactivating a user ends their sessions and tokens at once (`EnsureStaffIsCurrent`). Deactivating, or removing the church admin role from, the last active church admin is `409`. `422` for a role that does not exist or an email already used by a staff member of this church. Every change is written to the change history. **`PATCH .../status`** answers `200` with the staff member. Deactivating ends their tokens at once and their browser session on their next request; reactivating does not sign anyone in. Asking for the state they are already in changes and records nothing. `role_permissions` lists the permissions the person's role already gives, and `extra_permissions` the ones granted on top, so a screen can show which can be taken away (only the extras). **`GET /staff/directory`** is the one staff call that does not need `staff.manage`: it needs `members.edit` (church admin and pastor), lists only the church's **active** staff by name, and returns nothing but `id` and `name`, never an email or phone, so a screen that edits departments can offer them as a department's head (`per_page` as for the staff list; `401` without a token, `403` for roles without `members.edit`). **`PUT .../permissions`** gives or takes away *extra* permissions on top of the role (D-055): `grant` adds a permission (nothing happens if the role already gives it), `revoke` removes an extra that was granted earlier (nothing happens if the person never had it). At least one list is needed, a permission cannot be in both, and a name that is not a permission is `422`. A permission that comes from the role cannot be removed this way (`422` on `revoke.N`): change the person's role instead. Extras stay when the role changes. Both answer with the staff member and apply on the person's next request.

---

## Webhooks (Incoming)

These endpoints receive payloads from external services. They do not require a Bearer token — they verify authenticity via payload signatures.

### PayPerlez Webhook (MoMo collections and payouts)
```
POST /api/v1/webhooks/payperlez
Header: X-Payperlez-Signature: t=<unix-seconds>,v1=<hmac_sha256 of "t.body">
```

Served on the platform address, not a church's, because PayPerlez calls one URL for every church. Answers `401` unless the signature matches the raw body and the signed time is within 5 minutes of ours (`PAYPERLEZ_WEBHOOK_SECRET`, the full secret including `whsec_`; with none set nothing passes). A valid post is kept exactly as received in `webhook_logs` (encrypted) and answered `200 {"status":"ok"}` at once, even for a body we cannot read, because PayPerlez resends anything else. An event whose `id` was already stored is dropped (still `200`). Limited to 600 a minute per address.

**Built so far (D-069 step b3):** receiving, proving and keeping. The event name is read from `type` or `event` and the id from `id`, only as labels; both are unconfirmed until PayPerlez gives the real shape (B2). Turning an event into a paid, failed or paid-out gift comes with the gift and payout flow (steps d and e), so the logs stay unprocessed until then and polling covers payments.

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
Header: X-Hub-Signature-256: sha256={hmac_sha256 of the raw body with the app secret}
```
Served on the platform address (`yourapp.com`), not on a church's address, because Meta calls one URL for every church.

- **GET:** Meta sends `hub.mode=subscribe`, `hub.verify_token` and `hub.challenge`. The challenge is echoed as plain text when the token is the one in `META_VERIFY_TOKEN`; anything else is `403`.
- **POST:** `403` unless the signature matches the body (checked before anything is read; with no `META_APP_SECRET` set nothing passes). A valid post is kept exactly as received in `webhook_logs` (encrypted), queued, and answered `200 {"status":"ok"}` at once, even for a body that is not JSON or holds nothing we use, because Meta resends anything else. Limited to 600 a minute per address.
- Handled: `text` messages (the CHECKIN command, see above). Delivery receipts (`status`) are logged and ignored.

---

## Rate Limits

| Endpoint group | Limit |
|---|---|
| `POST /api/v1/auth/login` (D-054) | 20 attempts per minute per IP; 1 code per email per minute, 5 per hour, 20 per IP per hour (counted when a code is sent); 5 wrong passwords per email, then a 15-minute lockout |
| `POST /api/v1/auth/login/verify` (D-054) | 5 wrong codes per email, then a 15-minute lockout; 10 calls per minute per IP |
| `POST /api/v1/auth/password/forgot` and `/reset` (D-054) | same as `auth/login` |
| `POST /api/v1/staff` | 20 invitations per hour per church |
| `POST /api/v1/onboarding/otp` | 1 per phone per minute, 3 per phone per hour, 10 per IP per hour |
| `POST /api/v1/onboarding/churches` | 10 per IP per hour; 5 wrong codes per phone, then a 15-minute lockout |
| `POST /api/v1/messages/broadcast` | 10 per hour per church |
| `POST /api/v1/sms-credits/purchases` | 10 per hour per church |
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
