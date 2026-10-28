# Pattern — Zero-Downtime Migrations

> Read this file before running any schema change on a live production database with real traffic.

## 1. When to Read This

Read this file before:

- Adding a `NOT NULL` column to a table that already has data
- Renaming a column on a live table
- Changing a column's data type
- Removing a column
- Adding an index to a large table
- Any schema change on a table with millions of rows
- Any migration that will run on a production database with active traffic

**Why naive migrations cause downtime:** A migration like `ALTER TABLE orders ADD COLUMN status text NOT NULL DEFAULT 'pending'` acquires a full table lock while it runs. On a small table this is milliseconds. On a table with 10 million rows this can be minutes — during which every query to that table blocks, your app returns 500 errors, and your users see a broken site.

**The rule:**

> Schema changes on live production databases must be backward-compatible and non-locking. The deploy can happen before or after the migration without breaking running instances of the application.

## 2. The Expand/Contract Pattern

The expand/contract (also called parallel change) pattern breaks a breaking schema change into three safe phases, each deployed independently with no downtime.

**Phase 1 — Expand (additive, backward compatible)**

- Add the new column, table, or index alongside the existing one
- The new column is nullable — no NOT NULL constraint yet
- Deploy the new application code that writes to both old and new
- No existing code breaks — the old column/table still works
- The database accepts writes using both old and new structures

**Phase 2 — Migrate (backfill)**

- Run a background job to backfill the new column for existing rows
- Done in batches — not a single UPDATE on all rows (see Section 5)
- Both old and new code run simultaneously during this phase — writes continue to both
- Verify the backfill is complete before moving to Phase 3

**Phase 3 — Contract (remove old)**

- Deploy application code that only uses the new column/table
- Add the NOT NULL constraint (now safe — all rows have a value)
- Remove the old column in a separate migration after all instances are updated
- The old column can now be dropped safely

**Visual timeline:**

```
Phase 1: Old code reads old column
         New code reads old, writes to both
         ↓ deploy
Phase 2: Backfill running
         New code reads new (falls back to old if null), writes to both
         ↓ backfill complete
Phase 3: New code reads new only, writes new only
         ↓ deploy
         Drop old column
```

**Key principle:** at any point during the process, the previous version of the application must still work. This means you can roll back the deploy without a data migration.

## 3. Common Scenarios

**Add a NOT NULL column with a default:**

Naive (causes table lock on large tables):

```sql
-- BAD — locks the entire table while it rewrites every row
ALTER TABLE orders ADD COLUMN priority text NOT NULL DEFAULT 'normal';
```

Safe (3-phase):

```sql
-- Migration 1: Add nullable (fast, no rewrite)
ALTER TABLE orders ADD COLUMN priority text;

-- Background job: Backfill existing rows in batches
UPDATE orders SET priority = 'normal'
WHERE id BETWEEN $start AND $end AND priority IS NULL;

-- Migration 2: Add NOT NULL constraint (after backfill is complete)
ALTER TABLE orders ALTER COLUMN priority SET NOT NULL;
-- PostgreSQL 12+: ALTER TABLE orders ADD CONSTRAINT orders_priority_not_null CHECK (priority IS NOT NULL) NOT VALID;
-- Then: ALTER TABLE orders VALIDATE CONSTRAINT orders_priority_not_null;
-- This validates without a full table lock in PostgreSQL
```

---

**Rename a column:**

Naive (breaks running app instances immediately):

```sql
-- BAD — old code immediately breaks
ALTER TABLE orders RENAME COLUMN status TO order_status;
```

Safe (3-phase):

```sql
-- Phase 1: Add new column
ALTER TABLE orders ADD COLUMN order_status text;

-- Deploy: app writes to both status and order_status

-- Phase 2: Backfill
UPDATE orders SET order_status = status WHERE order_status IS NULL;

-- Phase 3: Deploy app reading only order_status
-- Then: drop old column
ALTER TABLE orders DROP COLUMN status;
```

---

**Remove a column:**

```sql
-- Phase 1: Deploy app code that no longer reads this column
-- Phase 2: Drop the column (now safe — no running code references it)
ALTER TABLE orders DROP COLUMN legacy_field;
```

Never drop a column in the same deploy as removing the code — old instances are still running and will fail.

---

**Change a column type:** This is the most complex scenario — always go through a new column:

```sql
-- Phase 1: Add new column with new type
ALTER TABLE orders ADD COLUMN amount_cents integer;

-- Phase 2: Backfill — convert old float values
UPDATE orders SET amount_cents = ROUND(amount_float * 100)
WHERE amount_cents IS NULL;

-- Phase 3: Deploy app using new column, drop old
ALTER TABLE orders DROP COLUMN amount_float;
```

## 4. Index Changes Without Locking

Adding an index on a large table with a standard `CREATE INDEX` blocks all writes on the table while the index is built. On a busy table with millions of rows, this can take minutes and cause an outage.

**PostgreSQL — use CONCURRENTLY:**

```sql
-- Builds the index without locking writes
-- Takes longer, but the table remains available
CREATE INDEX CONCURRENTLY idx_orders_status ON orders (status);

-- Drop without locking
DROP INDEX CONCURRENTLY idx_orders_status;
```

**Rules for concurrent index builds:**

- `CONCURRENTLY` cannot run inside a transaction block — run it as a standalone statement
- In Laravel migrations, wrap in `DB::statement()` or use `Schema::create` with a raw statement
- The index build takes longer than a regular build — plan for it
- If the build fails midway, an `INVALID` index is left — drop it and retry
- Always check `pg_indexes` after building to confirm the index is valid

**MySQL / MariaDB:**

- MySQL's InnoDB engine builds indexes online by default — `ALTER TABLE ... ADD INDEX` does not block
- For very large tables, still use `pt-online-schema-change` (Percona Toolkit) for safety

**Unique indexes:**

- `CREATE UNIQUE INDEX CONCURRENTLY` is safe in PostgreSQL
- But adding a unique constraint via `ADD CONSTRAINT ... UNIQUE` still locks — use the index approach then reference it:

```sql
CREATE UNIQUE INDEX CONCURRENTLY idx_orders_ref ON orders (external_ref);
ALTER TABLE orders ADD CONSTRAINT orders_external_ref_unique UNIQUE USING INDEX idx_orders_ref;
```

**Removing unused indexes:**

- Unused indexes slow down writes without helping reads
- Review and remove indexes that have not been used in production
- In PostgreSQL, `pg_stat_user_indexes.idx_scan = 0` after weeks of traffic means the index is unused

## 5. Large Table Migrations

A single `UPDATE orders SET new_col = old_col` on a 10 million row table locks rows for minutes, generates enormous transaction log, and can cause replication lag. Always batch large data migrations.

**Batched backfill pattern:**

```sql
-- Process in batches of 1000 rows
-- Run in a loop until no rows remain
UPDATE orders
SET new_column = compute(old_column)
WHERE id BETWEEN :start AND :end
  AND new_column IS NULL
LIMIT 1000;
```

**Application-level batching (preferred):**

```ts
async function backfillOrders() {
  let lastId = 0;
  const batchSize = 1000;

  while (true) {
    const rows = await db.select().from(orders)
      .where(and(gt(orders.id, lastId), isNull(orders.newColumn)))
      .limit(batchSize)
      .orderBy(orders.id);

    if (rows.length === 0) break;

    await db.update(orders)
      .set({ newColumn: computeValue(rows) })
      .where(inArray(orders.id, rows.map(r => r.id)));

    lastId = rows[rows.length - 1].id;
    await sleep(10); // small pause to avoid overwhelming the DB
  }
}
```

**Rules for large data migrations:**

- Never run a large backfill synchronously in a migration file — it will time out or block deploys
- Run backfills as a background job or a standalone script during off-peak hours
- Process in batches of 500–2000 rows — never all at once
- Add a small sleep between batches (10–50ms) — prevents overwhelming the database and replication
- Log progress — know how far the backfill has gotten if it needs to be resumed
- The backfill must be resumable — use the last processed ID, not an offset
- Monitor replication lag during the backfill — pause if lag exceeds a threshold
- Run on a read replica first if possible to test impact before production

**Tracking backfill progress:**

```sql
-- Quick count of remaining rows
SELECT COUNT(*) FROM orders WHERE new_column IS NULL;
```

Check this periodically to estimate completion time.

## 6. Zero-Downtime Migration Checklist

**Before every migration on a live table:**

- [ ] Is this table large (>100k rows)? If yes, follow the expand/contract pattern
- [ ] Does this migration acquire a full table lock? Check with `EXPLAIN` or test on a copy
- [ ] Is there a rollback plan if the migration needs to be reversed?
- [ ] Have you tested this migration on a copy of production data with similar volume?
- [ ] Is the migration backward compatible — will the previous app version still work during deploy?

**Adding a column:**

- [ ] Column added as nullable first — not `NOT NULL` immediately?
- [ ] Default applied in the application layer during the backfill phase — not as a DB default that rewrites all rows?
- [ ] NOT NULL constraint added only after the backfill is 100% complete?
- [ ] Large table backfill runs as a background job — not in the migration file?

**Renaming a column:**

- [ ] New column added alongside old (expand phase)?
- [ ] Application writes to both old and new during the transition?
- [ ] Backfill complete before removing the old column?
- [ ] Old column dropped in a separate migration after all app instances are updated?

**Removing a column:**

- [ ] Application code that reads this column removed in a prior deploy?
- [ ] No running app instance references the column before it is dropped?

**Indexes:**

- [ ] Large table indexes created with `CONCURRENTLY` (PostgreSQL) or equivalent?
- [ ] Index validity confirmed after creation?
- [ ] Unused indexes identified and scheduled for removal?

**Large data migrations:**

- [ ] Backfill runs in batches (500–2000 rows)?
- [ ] Small sleep between batches to prevent DB overload?
- [ ] Backfill is resumable — using last processed ID, not offset?
- [ ] Progress tracked and logged?
- [ ] Replication lag monitored during backfill?
