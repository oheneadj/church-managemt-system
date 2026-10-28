# Member Lifecycle

> The member record is the centre of gravity for the entire system. Every other module — attendance, giving, communications, pastoral care — anchors to a member. Get this right and everything else builds cleanly on top.

---

## Overview

A "member" in a Ghanaian church context is not the same as a user account. Most members will never log in. They are people the church knows, tracks, and cares for. The system must represent them faithfully even when they have no digital presence of their own.

The lifecycle has five stages:

```
Visitor → Prospect → Active Member → Transferred / Inactive → Archived
```

A member can move forwards and backwards between states. The system never deletes a member record — it only changes their status and removes them from active views.

---

## Stage 1: Visitor

Someone attends a service for the first time. The usher or admin captures:

- Full name
- Phone number
- How they heard about the church (word of mouth, social media, signpost, etc.)
- Date of first visit

A **Visitor** record is created — separate from a Member record. The system flags them for follow-up after 7 days with no second visit.

**Status transitions from Visitor:**
- Attends a second time → flagged as `Prospect` in the follow-up queue
- Admin converts manually → becomes a Member
- No return after 30 days → auto-archived (configurable per church)

---

## Stage 2: Prospect

The church has made contact. The admin starts building a richer record. At this point the system expects:

- Basic demographic details (gender, age/DOB, marital status)
- Contact address
- Assigned pastoral contact (who is following up with them)

Prospects appear in the pastoral care follow-up queue. They are not yet counted in the active membership total.

---

## Stage 3: Active Member

The church has formally received the person into membership. This is a deliberate act — not automatic. The admin clicks "Confirm Membership" and the record transitions to `active`.

### Member Record Fields

**Identity**
- Full name (given name, middle name, surname — all three)
- Preferred name / known as
- Gender
- Date of birth
- Profile photo (stored Cloudflare R2)
- National ID type + number (optional — for churches that track this)
- Unique member ID (system-generated, e.g. `GRC-0042`)

**Contact**
- Primary phone (used for OTP login, SMS, WhatsApp)
- Secondary phone (optional)
- Email address (optional)
- Residential address
- Nearest landmark (critical in Ghana addresses — "near the Total filling station")
- Digital address (Ghana Post GPS code, if available)

**Church Details**
- Membership date (when formally received)
- Membership class / type (full member, associate, affiliate)
- Departments / ministries (many-to-many — a member can serve in choir and ushers)
- Small group / cell group (v2)
- Assigned pastor / shepherd

**Family**
- Linked family record (optional — see Family Grouping below)
- Role in family (head, spouse, child, dependent)

**Ghana-Specific Fields**
- Educational level (Crèche / KG / Primary 1–6 / JHS 1–3 / SHS 1–3 / Tertiary / Graduate) — tracked for children and youth
- Occupation
- Tribe / language preference (for churches that conduct multi-lingual services)
- Confirmation status (confirmed / not yet confirmed)
- Baptism status (baptised / unbaptised) + date and officiant
- Dedication date (for infants) + officiant

---

## Stage 4: Inactive / Transferred

A member leaves the active roster through one of three paths:

**Transferred Out**
The member has moved to another branch or another church. The admin records:
- Date of transfer
- Destination church (free text)
- Transfer letter issued (yes/no)

The record is retained. Giving history and attendance history remain intact.

**Inactive**
The member has stopped attending but has not formally transferred. Typically triggered after a configurable absence threshold (default: 3 months of zero attendance). The admin can also mark manually.

Inactive members are hidden from default directory views but remain searchable. They can be reactivated at any time.

**Deceased**
Admin records date of passing. The record becomes read-only. It remains in the system for historical completeness (giving records, family links, pastoral history).

---

## Stage 5: Archived

Visitors with no follow-up conversion and members who have been inactive beyond a configurable threshold (default: 12 months) are moved to archived status. Archived records are excluded from all active views and reports unless explicitly searched. They are never deleted.

---

## Family Grouping

Churches think in families, not just individuals. The system supports this.

A **Family** record groups two or more members together:

```
Family: Mensah-Boateng
├── Emmanuel Mensah-Boateng (Head)
├── Abena Mensah-Boateng (Spouse)
├── Kofi Mensah-Boateng (Child, age 12, JHS 2)
└── Ama Mensah-Boateng (Child, age 8, Primary 3)
```

**Rules:**
- One head of household per family
- A member can only belong to one family at a time
- Children inherit the family's record by default but can be unlinked at adulthood
- Family view shows aggregate giving, combined attendance summary, and family-level pastoral notes

**Why this matters:** When a pastor wants to follow up with a family, he needs to see the whole picture — not four separate records. A family where the head is a top giver but two children have stopped attending confirmation class is a pastoral alert.

---

## Youth Lifecycle Tracking

Children and youth in Ghanaian churches follow a recognisable progression through educational levels. The system tracks this explicitly.

### Educational Levels (Ghana Curriculum)
| Level | Age Range (Typical) |
|---|---|
| Crèche / Nursery | 0–3 |
| Kindergarten (KG 1–2) | 4–5 |
| Primary 1–6 | 6–11 |
| JHS 1–3 (Junior High) | 12–14 |
| SHS 1–3 (Senior High) | 15–17 |
| Tertiary | 18–22 |
| Graduate / Adult | 22+ |

### Tracked Milestones
- **Dedication** — infant dedication to the church. Date + officiant recorded.
- **Baptism** — date, mode (immersion / sprinkling), and officiant.
- **Confirmation** — completion of confirmation class. Date + class cohort.
- **First Communion** (where applicable)
- **Level-up promotion** — admin manually promotes a child to the next educational level, or the system prompts annually based on DOB.

### Birthday Alerts
The system generates a notification to the assigned pastor or department head 7 days before a youth member's birthday. Configurable per church.

---

## Member Authentication

Members authenticate via OTP — no password required.

**Flow:**
1. Member visits the church portal URL (`churchname.yourapp.com`)
2. Enters their phone number
3. Receives a 6-digit OTP via SMS or WhatsApp (church configurable)
4. OTP verified → session created
5. Member sees: their own profile, giving history, and check-in option

**Scope of member access:**
- View and update their own profile (photo, phone, address)
- View their own giving history and giving receipts
- View their attendance history
- Check in to an active service
- No access to other members' records under any circumstances

---

## Roles and Access to Member Data

| Field / Action | Church Admin | Pastor | Finance Secretary | Dept Head | Youth Pastor | Member |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| View directory | ✓ | ✓ | ✓ | Own dept | Youth only | Own record |
| Add / edit member | ✓ | ✓ | ✗ | ✗ | ✗ | Own only |
| View giving history | ✓ | Summary only | ✓ Full | ✗ | ✗ | Own only |
| View pastoral notes | ✓ | ✓ | ✗ | ✗ | ✗ | ✗ |
| Export member list | ✓ | ✗ | ✓ | ✗ | ✗ | ✗ |
| Deactivate member | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ |
| View youth milestones | ✓ | ✓ | ✗ | ✗ | ✓ | Own only |

---

## Data Integrity Rules

1. **Phone number is unique per church.** No two active members in the same church can share a phone number. This is enforced at the database level (unique constraint on `phone + church_id`).

2. **Member ID is immutable.** Once assigned, a member's system ID (`GRC-0042`) never changes, even through status transitions.

3. **Deletions are soft only.** No `DELETE` statements on member records in application code. Status flags control visibility.

4. **Audit trail on sensitive edits.** Changes to phone number, giving records, or membership status are logged with the acting user's ID and timestamp.

5. **Family links are preserved on member deactivation.** A deceased or inactive member remains visible in their family record, marked with their status.

---

## Search & Filtering

The member directory must be fast and flexible. Search requirements:

- Full-text search across name, phone, and member ID
- Filter by: status, gender, marital status, department, educational level, membership class, date range of joining
- Sort by: name (A–Z), join date, last attendance, giving total (Finance Secretary only)
- Results paginated — no full-table loads

Performance target: directory search returns results in under 500ms for a church with 1,000 members.

---

## Related Documents

- [`04-giving-and-contributions.md`](./04-giving-and-contributions.md) — how member giving is tracked
- [`07-data-model.md`](./07-data-model.md) — `members`, `families`, `member_milestones` table schemas
- [`08-api.md`](./08-api.md) — `/api/members` endpoints
- [`15-security.md`](./15-security.md) — data access controls and encryption
