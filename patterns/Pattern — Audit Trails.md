# Pattern — Audit Trails

> Read this file before building any feature that tracks changes to important records — orders, payments, user roles, settings, or any field that could be disputed.

## 1. When to Read This

Read this file before:

- Building a feature where a customer or admin might dispute what happened (orders, payments, KYC)
- Adding status or lifecycle tracking to any model (order progresses through stages, user roles change)
- Building an admin interface where you need to know who changed what and when
- Implementing compliance requirements that need a change history
- Any feature where "what was it before?" is a question you will need to answer in production

## 2. What Needs an Audit Trail

**Always audit these:**

| Entity | What to track |
| --- | --- |
| Orders | Status changes, amount changes, cancellations, refunds |
| Payments | All payment events, failures, refunds, disputes |
| User accounts | Role changes, KYC status changes, suspensions, email/phone changes |
| Sensitive settings | Pricing changes, commission rates, system configuration |
| Admin actions | Any admin-initiated change to user or financial data |
| Authentication | Login, logout, failed login, password change, token revocation |
| File access | Access to KYC documents, invoices, sensitive files |

**Why audit trails matter:**

- **Disputes** — a customer says "I never changed my address" — you have the record that they did, with a timestamp and IP
- **Debugging** — something is wrong in production — the audit trail shows exactly what changed and when
- **Compliance** — regulators require a record of who accessed or changed what financial data
- **Security** — detect unusual patterns — an admin changed 100 user roles in 5 minutes
- **Trust** — auditable systems earn trust from customers, regulators, and partners

**What does NOT need a full audit trail:**

- High-frequency, low-sensitivity fields (last\_seen\_at, view\_count, search\_query)
- Temporary state (session data, cache entries)
- Data you already log elsewhere (application logs, access logs)
- Large binary data — audit the fact of a change, not the binary content

## 3. Audit Trail Schema Design

**Single audit log table — recommended for most applications:**

```sql
CREATE TABLE audit_logs (
  id              bigserial PRIMARY KEY,
  ulid            text NOT NULL UNIQUE,

  -- What changed
  auditable_type  text NOT NULL,          -- 'Order', 'User', 'Payment'
  auditable_id    bigint NOT NULL,         -- internal FK to the record
  auditable_ulid  text NOT NULL,           -- external identifier
  event           text NOT NULL,           -- 'created', 'updated', 'deleted', or a domain event like 'status_changed'

  -- What the values were
  old_values      jsonb,                   -- previous state (null for creates)
  new_values      jsonb NOT NULL,          -- new state
  changed_fields  text[],                  -- which fields actually changed

  -- Who did it
  actor_type      text NOT NULL,           -- 'user', 'admin', 'system', 'api_key'
  actor_id        bigint,                  -- nullable — system-initiated changes have no actor
  actor_ulid      text,
  actor_name      text,                    -- snapshot of the name at the time — not a join

  -- Context
  ip_address      text,
  user_agent      text,
  request_id      text,                    -- correlation ID for tracing
  reason          text,                    -- optional human-readable reason for the change
  created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX ON audit_logs (auditable_type, auditable_id, created_at DESC);
CREATE INDEX ON audit_logs (actor_type, actor_id, created_at DESC);
CREATE INDEX ON audit_logs (created_at DESC);
```

**Key design decisions:**

- Store `actor_name` as a snapshot — not a foreign key — so renaming a user does not change audit history
- Store `old_values` and `new_values` as JSON — flexible, no schema change needed when models change
- `changed_fields` array lets you query "show me all changes to the status field" without parsing JSON
- `request_id` links the audit entry to the application log for the same request
- The table is append-only — never update or delete audit log entries

## 4. Implementation Patterns

**Pattern 1 — Model Observer (Laravel / ActiveRecord-style ORMs)**

Hook into the ORM's lifecycle events to capture changes automatically:

```php
// app/Observers/OrderObserver.php
class OrderObserver
{
    public function updating(Order $order): void
    {
        if ($order->isDirty()) {
            AuditLog::create([
                'auditable_type' => 'Order',
                'auditable_id'   => $order->id,
                'auditable_ulid' => $order->ulid,
                'event'          => 'updated',
                'old_values'     => $order->getOriginal(),
                'new_values'     => $order->getDirty(),
                'changed_fields' => array_keys($order->getDirty()),
                'actor_type'     => 'user',
                'actor_id'       => auth()->id(),
                'actor_name'     => auth()->user()?->name,
                'ip_address'     => request()->ip(),
                'request_id'     => request()->header('X-Request-ID'),
            ]);
        }
    }
}
```

**Pattern 2 — Explicit audit in Action classes**

For domain events that carry meaning beyond a simple field change:

```ts
// In CreateOrderAction
const order = await orderRepo.create(dto, tx);

await auditLog.record(tx, {
  auditableType: 'Order',
  auditableUlid: order.ulid,
  event: 'order_placed',
  newValues: { status: order.status, amount: order.amountCents },
  actorUlid: actor.ulid,
  actorName: actor.name,
  reason: 'Customer placed order via checkout',
});
```

**Pattern 3 — Database triggers (last resort)**

- Triggers capture changes at the DB level — no application code needed
- Cannot capture who made the change (no application context in the DB)
- Hard to maintain and test
- Use only when you cannot modify the application layer

**What NOT to put in the audit log:**

- Passwords, tokens, or any credential — even hashed
- Full file contents — audit that a file was changed, not the contents
- High-frequency automated updates (heartbeats, cache writes) — they generate noise and cost
- Fields you explicitly exclude: add an exclusion list to the observer for non-auditable fields

## 5. Querying & Displaying Audit History

**Common queries:**

```sql
-- Full history of a specific order
SELECT * FROM audit_logs
WHERE auditable_type = 'Order' AND auditable_ulid = 'ord_01J8K...'
ORDER BY created_at ASC;

-- All changes made by a specific admin
SELECT * FROM audit_logs
WHERE actor_ulid = 'usr_01J8K...' AND actor_type = 'admin'
ORDER BY created_at DESC
LIMIT 50;

-- All status changes across all orders today
SELECT * FROM audit_logs
WHERE auditable_type = 'Order'
  AND 'status' = ANY(changed_fields)
  AND created_at >= NOW() - INTERVAL '24 hours'
ORDER BY created_at DESC;

-- Detect suspicious: admin changed more than 10 records in an hour
SELECT actor_ulid, COUNT(*) as change_count
FROM audit_logs
WHERE actor_type = 'admin'
  AND created_at >= NOW() - INTERVAL '1 hour'
GROUP BY actor_ulid
HAVING COUNT(*) > 10;
```

**Displaying to the user:**

- Show a timeline of changes — most recent first
- Display human-readable field names — not database column names
- Show before and after values side by side for updates
- Include who made the change and when
- For admin views: include IP address and request ID
- For customer views: show only their own changes; never expose internal admin changes

**Diff display:**

```ts
// Compute a human-readable diff from old_values and new_values
function buildDiff(oldValues: Record<string, unknown>, newValues: Record<string, unknown>) {
  return Object.keys(newValues).map(field => ({
    field: humanizeFieldName(field),
    from: formatValue(field, oldValues[field]),
    to: formatValue(field, newValues[field]),
  }));
}
```

## 6. Compliance & Retention

**The audit log is immutable:**

- Never UPDATE or DELETE rows from the audit log
- Application code must not have permission to delete from this table
- If a change was logged in error, add a corrective entry — do not remove the original
- The immutability is what makes the audit log trustworthy

**Sensitive data in audit logs:**

- The audit log stores old and new values — this means PII flows through it
- If a user exercises their right to erasure (GDPR/NDPA), you must anonymize their PII in the audit log too
- Do NOT delete the audit entry itself — replace personal values with `[deleted]` while keeping the structural record
- Fields that should never be stored in audit logs even temporarily: passwords, tokens, raw card numbers
- Consider encrypting the entire `old_values` / `new_values` JSON at the column level for high-sensitivity tables

**Retention policy:**

| Audit data type | Recommended retention | Reason |
| --- | --- | --- |
| Financial changes (payments, refunds) | 7 years | Tax and financial regulation |
| Order status changes | 5 years | Consumer protection / dispute window |
| User account changes (role, KYC) | 5 years | Compliance and anti-fraud |
| Authentication events | 2 years | Security investigation window |
| Admin actions | 5 years | Internal accountability |
| General field changes | 2 years | Operational value after this is low |

**Automated archival:**

- Run a scheduled job to archive old audit entries to cold storage (S3, GCS) after the active retention period
- Delete from the hot table after archival — the audit log will grow indefinitely without this
- Archive before deleting — never delete first

**Access control:**

- Audit logs are read-only for all users — including superadmins
- Audit log access itself should be logged — who looked at the audit trail and when
- Customer-facing audit views show only that user's own changes
- Admin audit views show all changes with full actor context

## 7. Audit Trail Checklist

**Schema:**

- [ ] `audit_logs` table created with `auditable_type`, `auditable_ulid`, `event`, `old_values`, `new_values`, `changed_fields`, `actor_ulid`, `actor_name` (snapshot), `request_id`?
- [ ] Indexes on `(auditable_type, auditable_id, created_at DESC)` and `(actor_type, actor_id)`?
- [ ] Application code does not have DELETE permission on `audit_logs`?

**Coverage:**

- [ ] All financial record changes (payments, refunds, orders) audited?
- [ ] All user role and permission changes audited?
- [ ] All admin-initiated changes to user data audited?
- [ ] Authentication events (login, logout, password change) logged?
- [ ] Sensitive file access logged?

**Data quality:**

- [ ] Actor name stored as a snapshot — not a foreign key that changes?
- [ ] `changed_fields` array populated for update events?
- [ ] `old_values` populated for updates (null only for creates)?
- [ ] Passwords, tokens, and credentials excluded from audit values?
- [ ] Request ID included for correlation with application logs?

**Compliance:**

- [ ] PII anonymization on erasure — replaces values, does not delete entries?
- [ ] Retention policy defined per audit type?
- [ ] Automated archival to cold storage scheduled?
- [ ] Audit log is append-only — no UPDATE or DELETE paths in the application?

**Display:**

- [ ] Customer audit view shows only their own changes?
- [ ] Admin audit view shows full actor context?
- [ ] Human-readable field names displayed — not column names?
- [ ] Before/after diff displayed for update events?

**Testing:**

- [ ] Audit entry created for every audited action?
- [ ] Audit entry NOT created for excluded fields (passwords, tokens)?
- [ ] Audit entry correctly identifies the actor?
- [ ] PII anonymization test — erasure replaces values but preserves structure?
