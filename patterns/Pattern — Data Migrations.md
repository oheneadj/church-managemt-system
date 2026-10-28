# Pattern — Data Migrations

> Read this file before moving, transforming, or backfilling existing production data at scale.

## 1. When to Read This

Read this file before:

- Backfilling a new column for existing rows (e.g. computing a `slug` for all existing products)
- Transforming data from one format to another (converting float prices to integer cents)
- Normalizing denormalized data (splitting a `full_name` column into `first_name` + `last_name`)
- Anonymizing PII for a user who requested erasure
- Moving data from one table to another
- Any operation that modifies existing rows at scale in production

**Schema migrations vs data migrations:**

- **Schema migration** — changes the database structure: adds a column, creates an index, renames a table. Run by your migration tool (`php artisan migrate`, `drizzle-kit migrate`).
- **Data migration** — changes the content of existing rows: backfills a new column, transforms values, moves records. Run as a standalone script, artisan command, or background job — never inside a schema migration file.

## 2. Planning a Data Migration

**Before writing a single line of migration code, answer these questions:**

**Scope:**

- How many rows will this migration touch?
- Which tables and which columns?
- Are there related tables that also need updating?

**Risk:**

- What happens if this migration runs partially (crashes halfway)?
- Can it run while the application is live, or does it need a maintenance window?
- Will it lock rows that the application also needs to write to?
- Is the transformation reversible?

**Rollback plan:**

- If the migration produces wrong results, how do you undo it?
- Do you need to keep the old column / old data until the migration is verified?
- Can you roll back the application code independently of the data migration?

**Testing:**

- Have you tested on a copy of production data (or a representative sample)?
- How long did it take on the test data? Extrapolate to production volume.
- Did the test run affect application performance? (watch replication lag, query times)

**The three phases of a safe data migration:**

```
Phase 1: Prepare
  → Add the new column (nullable, no NOT NULL yet)
  → Deploy code that reads old column, writes to both old and new
  → Verify both columns are being written correctly

Phase 2: Migrate
  → Run the data migration in batches
  → Monitor progress, DB performance, and replication lag
  → Verify a sample of migrated rows before declaring done

Phase 3: Clean up
  → Add NOT NULL constraint once all rows have a value
  → Deploy code that only reads the new column
  → Schedule removal of the old column in a future migration
```

## 3. Running Safely

**Never run a large data migration as a single query.** A single `UPDATE orders SET new_col = compute(old_col)` on a million rows locks rows for minutes, generates enormous write-ahead log, and can crash the database.

**Batched migration pattern:**

```ts
async function migrateInBatches() {
  const BATCH_SIZE = 1000;
  const SLEEP_MS   = 50;    // pause between batches — let the DB breathe
  let lastId       = 0;
  let totalMigrated = 0;

  console.log('Starting migration...');

  while (true) {
    // Fetch next batch
    const rows = await db
      .select()
      .from(products)
      .where(and(
        gt(products.id, lastId),
        isNull(products.newColumn),  // only unmigrated rows
      ))
      .orderBy(products.id)
      .limit(BATCH_SIZE);

    if (rows.length === 0) break;  // done

    // Transform and update
    await db.transaction(async (tx) => {
      for (const row of rows) {
        await tx.update(products)
          .set({ newColumn: transform(row.oldColumn) })
          .where(eq(products.id, row.id));
      }
    });

    lastId         = rows[rows.length - 1].id;
    totalMigrated += rows.length;
    console.log(`Migrated ${totalMigrated} rows (last id: ${lastId})`);

    await sleep(SLEEP_MS);
  }

  console.log(`Migration complete. Total: ${totalMigrated} rows.`);
}
```

**Idempotency — the migration must be safe to run twice:**

- Always filter for rows that have not yet been migrated (`WHERE new_col IS NULL`)
- Running the migration twice should produce the same result as running it once
- If the migration crashes halfway, re-running it should pick up where it left off (not start over)
- Use the last processed ID (not an offset) as the resume point

**Progress tracking:**

- Log progress every N batches — you need to know how far along you are
- Estimate time remaining: `(totalRows - migratedRows) / rowsPerSecond`
- Store progress in the database or a file so it survives a restart

**Monitoring during the run:**

- Watch database CPU and memory — pause if they spike
- Watch replication lag — pause if lag grows beyond a threshold
- Watch application error rates — stop if you see unexpected errors
- Have a kill switch: a flag in the database or environment that stops the migration loop gracefully

## 4. Common Scenarios

**Backfill a new column:**

```sql
-- After adding: ALTER TABLE products ADD COLUMN slug text;
-- Backfill in batches (from application code)
UPDATE products
SET slug = lower(regexp_replace(name, '[^a-zA-Z0-9]', '-', 'g'))
WHERE slug IS NULL
  AND id BETWEEN :start AND :end;
```

Verify no two rows got the same slug before adding the unique constraint.

---

**Convert float prices to integer cents:**

```sql
-- Add new column first
ALTER TABLE products ADD COLUMN price_cents integer;

-- Backfill — convert float to integer cents with rounding
UPDATE products
SET price_cents = ROUND(price_float * 100)
WHERE price_cents IS NULL
  AND id BETWEEN :start AND :end;

-- Verify no precision loss: check rows where ROUND(price_float * 100) / 100 != price_float
SELECT COUNT(*) FROM products
WHERE ABS(price_cents::float / 100 - price_float) > 0.005;
```

---

**Normalize a denormalized column:**

```sql
-- Splitting full_name into first_name + last_name
-- Phase 1: add new columns
ALTER TABLE users ADD COLUMN first_name text, ADD COLUMN last_name text;

-- Phase 2: backfill — note: real names are complex, validate carefully
UPDATE users
SET
  first_name = split_part(full_name, ' ', 1),
  last_name  = NULLIF(substring(full_name FROM position(' ' IN full_name) + 1), '')
WHERE first_name IS NULL
  AND full_name IS NOT NULL
  AND id BETWEEN :start AND :end;

-- Phase 3: manually review rows where full_name has 3+ words
SELECT id, full_name FROM users
WHERE array_length(string_to_array(trim(full_name), ' '), 1) > 2;
```

---

**PII anonymization on user erasure:**

```ts
async function anonymizeUser(userId: number): Promise<void> {
  await db.transaction(async (tx) => {
    // Anonymize the user record
    await tx.update(users)
      .set({
        name:        '[deleted]',
        email:       `deleted_${users.ulid}@deleted.invalid`,
        phone:       null,
        address:     null,
        deletedAt:   new Date(),
      })
      .where(eq(users.id, userId));

    // Anonymize PII in audit logs (preserve structure, remove identity)
    await tx.update(auditLogs)
      .set({ actorName: '[deleted]' })
      .where(eq(auditLogs.actorId, userId));

    // Delete physical files (KYC documents, profile photos)
    const files = await tx.select().from(userFiles).where(eq(userFiles.userId, userId));
    for (const file of files) {
      await storage.delete(file.storageKey);
    }
    await tx.delete(userFiles).where(eq(userFiles.userId, userId));
  });
}
```

Always run anonymization in a single transaction — the user record and all associated PII must be anonymized together or not at all.

## 5. Data Migration Checklist

**Planning:**

- [ ] Row count estimated — is this a small (< 10k), medium (< 1M), or large (> 1M) migration?
- [ ] Tested on a representative data sample before running on production?
- [ ] Time estimate based on test run performance?
- [ ] Rollback plan defined — how to undo if results are wrong?
- [ ] Old column / old data preserved until migration is verified?
- [ ] Schema migration separate from data migration — data migration runs as a script/job, not inside a migration file?

**Implementation:**

- [ ] Migration runs in batches (500–2000 rows per batch)?
- [ ] Sleep between batches to avoid overwhelming the DB?
- [ ] Idempotent — only processes unprocessed rows (`WHERE new_col IS NULL`)?
- [ ] Uses last processed ID as resume point — not an offset?
- [ ] Progress logged every N batches?
- [ ] Kill switch available — can stop the migration gracefully?

**During the run:**

- [ ] DB CPU, memory, and replication lag monitored?
- [ ] Application error rates monitored — stopped if unexpected errors appear?
- [ ] Sample of migrated rows verified correct before declaring done?

**After completion:**

- [ ] NOT NULL constraint added only after all rows have a value?
- [ ] Full count check — expected rows migrated vs actual?
- [ ] Old data available for rollback window (do not drop old columns immediately)?
- [ ] Migration script saved to the repository for reference?
