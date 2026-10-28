# Data Safety

> This document defines how we handle, protect, and retain the data entrusted to us by churches and their members. Church data is not ordinary SaaS data — it includes giving history, pastoral care notes, family records, and personal contact information. We treat it accordingly.

---

## Guiding Principles

**1. Data belongs to the church, not to us.**
A church's member records, contribution history, and communications are their data. We store it on their behalf. If a church leaves the platform, they get their data back in full. We do not retain it for any commercial purpose.

**2. The least privilege that works.**
Every role in the system sees only what they need. A department head does not need access to giving records. A finance secretary does not need pastoral care notes. Access is not granted by default — it is granted specifically.

**3. Sensitive data is encrypted at rest.**
Personally identifiable information (PII), financial records, and pastoral notes are encrypted in the database. If someone walks off with the database file, the data is useless without the encryption keys.

**4. We do not touch the money.**
Payments are processed entirely by Paystack. No card numbers, MoMo PINs, or bank account details are ever stored in our system. The only financial data we hold is the contribution record (amount, fund type, date) after Paystack confirms the transaction.

**5. Transparency with churches.**
Data handling practices are disclosed in plain language in the Terms of Service and the onboarding checklist. No surprises.

---

## What Data We Hold

### Member Data (PII)
- Full name, date of birth, gender
- Phone number (used for authentication)
- Residential address
- Photo (stored in Cloudflare R2)
- National ID type/number (where provided — optional)
- Educational level, tribe/language preference

**Classification: Sensitive PII**
**Retention: For the life of the church's subscription + 90-day grace period after cancellation**

### Financial Data
- Contribution amounts, dates, fund types, payment methods
- Pledge records and status
- Paystack transaction references (not card/MoMo credentials)

**Classification: Financial records**
**Retention: 7 years** — matches standard accounting record retention in Ghana. Non-negotiable. Even if a church leaves, their financial records are retained in an archived, inaccessible-to-them state for this period.

### Pastoral Care Data (v2)
- Visit logs, prayer requests, welfare notes
- These are the most sensitive records in the system — personal circumstances, health, family problems

**Classification: Highly sensitive**
**Retention: For the life of the subscription + 90-day grace period**
**Access: Pastor and Church Admin only. No export available for bulk pastoral notes.**

### Authentication Data
- Phone numbers (already in member/user records)
- OTP hashes (never stored — OTPs are one-time, expiring, and hashed in transit)
- Session tokens (Sanctum personal access tokens)

**Classification: Authentication credentials**
**Retention: Session tokens invalidated on logout or after 30 days of inactivity**

### Communication Logs
- SMS/WhatsApp message content, recipients, timestamps, delivery status
- These logs exist for the church admin's visibility — not for our use

**Classification: Operational data**
**Retention: 12 months rolling**

### System Logs
- API request logs, error logs, queue job logs
- No PII in logs — member IDs (ULIDs) are used, not names or phone numbers

**Classification: Operational/infrastructure**
**Retention: 90 days**

---

## Data Isolation (Multi-Tenancy)

Every record in the database carries a `church_id`. Laravel global scopes ensure that every query automatically filters by the authenticated church. There is no mechanism in the application code to query across churches.

**Testing requirement:** Every pull request that touches a data-access layer must include a test that confirms cross-tenant data leakage is impossible for that endpoint. This is non-negotiable.

The `BelongsToChurch` trait is applied to every Eloquent model that holds church data. Any model missing this trait is a security defect.

---

## Encryption

### At Rest
Sensitive fields are encrypted using Laravel's `encrypted` cast (AES-256-CBC via `APP_KEY`):

- `members.national_id_number`
- `members.address` (full residential address)
- `pastoral_notes.content` (v2)
- `webhook_logs.payload` (contains Paystack data)

The `church_id`, `member_id`, amounts, and fund types are **not** encrypted — they are needed for queries, aggregations, and indexing. The encryption targets fields that would cause direct harm if exposed raw.

### In Transit
All traffic is HTTPS. Let's Encrypt wildcard certificate managed by Coolify. HTTP is permanently redirected to HTTPS at the Nginx layer. There is no plaintext endpoint anywhere in the system.

### Keys
- Application encryption key: `APP_KEY` in `.env`, never committed to version control
- Separate key rotation procedure documented in [`16-deployment-and-ops.md`](./16-deployment-and-ops.md)

---

## Access Controls

### Authentication
- Staff use phone number + OTP (no passwords — no password resets, no "forgot password" flows to attack)
- Members use phone number + OTP
- OTPs are 6 digits, expire in 10 minutes, single-use
- Failed OTP attempts: 5 allowed, then 15-minute lockout

### Authorisation
- Roles assigned via Spatie Laravel Permission
- Every API endpoint checks both authentication (is this user logged in?) and authorisation (does this user's role/permissions allow this action?)
- Role checks happen in the controller; permission checks happen in Policy classes
- No admin backdoor or master key — even the platform operator cannot access a church's data through the app (only directly in the database, which requires infrastructure access and is audit-logged)

### Session Management
- Sanctum tokens are tied to the `church_id` at creation
- A staff member who is moved to a different role has their existing token invalidated
- A deactivated user's token is invalidated immediately on deactivation

---

## Backup Strategy

### Database Backups
- **Frequency:** Daily automated backup at 02:00 UTC
- **Retention:** 30 daily backups kept
- **Location:** Separate Cloudflare R2 bucket from the application (different credentials)
- **Encryption:** Backups are encrypted before upload using GPG with a platform-held key
- **Test restore:** Full restore test performed monthly, documented in the ops log

### File Backups (Cloudflare R2)
- Member photos and uploaded receipts are in Cloudflare R2
- R2 has built-in redundancy — no additional backup required
- Files are versioned (soft-delete, not hard-delete) to allow recovery from accidental deletion

### Recovery Time Objective (RTO)
- Target: restore to operational within 4 hours of a declared data loss event
- Recovery Point Objective (RPO): maximum 24 hours of data loss (one backup window)

---

## Data Export (Church Portability)

A church must be able to leave the platform and take their data with them. We support this through:

**Member export:** Full member directory as CSV (all fields including inactive and archived)

**Contribution export:** Full contribution history as CSV (all records, all time)

**Available to:** Church Admin only, via the settings panel

**Format:** CSV, UTF-8 encoded (handles Ghanaian names with special characters correctly)

**Processing:** Export jobs are queued (not synchronous — large churches may have thousands of records). Admin receives a download link via email/SMS when ready.

**After cancellation (built, D-042):** `php artisan church:cancel {slug}` starts a 90-day grace period (`config/retention.php`). The church keeps working during it, so its admin can export their data, and `church:reinstate {slug}` takes the cancellation back. A daily job (`churches:purge-cancelled`, 03:30 UTC) then queues a purge of every church whose grace period is over. The purge erases every member as a single erasure does, removes staff, family, visitor and attendance-visitor details, deletes the church's messages and stored files, masks its change history, removes its contact and payout details, soft-deletes it and frees its web address. Contributions, pledges and disbursements stay for the 7-year rule, with their free-text notes removed and no name left to link them to a person. The purge cannot be undone.

---

## Incident Response

If a data breach or unauthorised access is suspected:

1. **Detect** — automated alerts fire from the monitoring system (see [`17-monitoring-and-alerting.md`](./17-monitoring-and-alerting.md))
2. **Contain** — the affected church's account is suspended immediately pending investigation
3. **Assess** — determine what data was accessed, by whom, and for how long
4. **Notify** — affected churches are notified within 72 hours of confirmed breach. Notification includes what happened, what data was involved, and what steps we are taking.
5. **Remediate** — fix the vulnerability, rotate credentials, restore from backup if needed
6. **Post-mortem** — documented in `docs/reference/post-mortems/` within 7 days

**Who handles this:** The lead engineer is the first responder. The product owner handles church communications. No breach is handled without both roles involved.

---

## Compliance Notes

Ghana does not yet have comprehensive data protection legislation equivalent to GDPR (the Data Protection Act 2012 exists but enforcement is limited). Regardless, we build to a GDPR-aligned standard because:

- Diaspora churches in the UK and EU are a target market — they are GDPR-bound
- It is the right way to handle personal data
- Regulatory tightening in Ghana is likely — building correctly now avoids costly remediation later

**Specific GDPR-aligned practices we follow:**
- Data minimisation — we only collect what we need
- Purpose limitation — member data is used for church management, not sold or used for marketing
- Right to erasure — a member can request deletion of their record (except where financial retention rules apply). Built (D-039): the church admin erases a member's personal data; the record stays, archived and soft-deleted, so money and attendance records keep working.
- Right to access — built (D-039): the church admin can export everything held about a member as JSON.
- Consent — WhatsApp broadcast opt-in is explicit, not assumed

---

## What We Do NOT Do

- Store card numbers, CVVs, or MoMo PINs — ever
- Sell or share member data with third parties
- Use member data for our own marketing or analytics beyond aggregate, anonymised platform metrics
- Access a church's data without an explicit, logged, justified reason
- Send marketing communications to church members on behalf of the platform

---

## Related Documents

- [`06-architecture.md`](./06-architecture.md) — infrastructure and network security
- [`15-security.md`](./15-security.md) — application-level security controls
- [`16-deployment-and-ops.md`](./16-deployment-and-ops.md) — backup procedures and key management
- [`17-monitoring-and-alerting.md`](./17-monitoring-and-alerting.md) — breach detection alerts
