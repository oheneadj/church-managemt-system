# Pattern — Pagination (Deep)

> Read this file before choosing a pagination strategy or implementing pagination on a list endpoint.

## 1. When to Read This

Read this file before:

- Building any list endpoint that returns more than 100 records
- Experiencing slow page loads on paginated lists as data grows
- Building an infinite scroll or "load more" feature
- Building an API that external consumers or mobile apps will paginate through
- The current `OFFSET`/`LIMIT` approach is getting slower as the table grows

## 2. Offset Pagination

**How it works:**

```sql
SELECT * FROM orders
WHERE tenant_id = $1
ORDER BY created_at DESC
LIMIT 20 OFFSET 200;  -- skip the first 200 rows, return the next 20
```

**Pros:**

- Simple to implement — one parameter (`page` or `offset`)
- Supports jumping to any page: `?page=50`
- Works with any `ORDER BY` clause
- Framework paginators handle it automatically

**Cons:**

- **Gets slower as OFFSET grows** — the database must scan and discard all rows before the offset, even if it does not read them. `OFFSET 10000` scans 10,000 rows. `OFFSET 1,000,000` scans 1,000,000 rows.
- **Rows can appear twice or be skipped** during pagination if new rows are inserted between requests — the window shifts. User requests page 2; a new row is inserted; the old page 1 row 20 is now page 2 row 1 — they see a duplicate.
- Most search engines cap offset pagination (Meilisearch: 20,000 max offset)

**When offset pagination is acceptable:**

- Small datasets (< 10,000 rows total)
- Admin interfaces where users never paginate past page 5–10
- Situations where the occasional duplicate/skip is not a serious problem
- The dataset is not frequently mutated during active pagination

**When to switch:** if users or processes routinely paginate beyond page 10–20, or the table has > 100,000 rows, consider cursor or keyset pagination.

## 3. Cursor Pagination

**How it works:** Instead of a page number, the client receives an opaque cursor that points to their position in the result set. The next request passes the cursor back to get the next page.

```ts
// Response
{
  "data": [...20 orders...],
  "next_cursor": "eyJpZCI6MTAwLCJjcmVhdGVkX2F0IjoiMjAyNi0xMC0yNlQxMjowMDowMFoifQ==",
  "has_more": true
}

// Next request
GET /api/v1/orders?cursor=eyJpZCI6MTAwLCJjcmVhdGVkX2F0IjoiMjAyNi0xMC0yNlQxMjowMDowMFoifQ==
```

**What the cursor contains:** The cursor is a Base64-encoded JSON object containing the values of the last row's sort columns:

```ts
// Cursor encodes: { id: 100, created_at: '2026-10-26T12:00:00Z' }
const cursor = Buffer.from(JSON.stringify({ id: lastRow.id, createdAt: lastRow.createdAt })).toString('base64');

// Decoding for the query
const { id, createdAt } = JSON.parse(Buffer.from(cursor, 'base64').toString());
```

**The query using the cursor:**

```sql
SELECT * FROM orders
WHERE tenant_id = $1
  AND (created_at, id) < ($cursor_created_at, $cursor_id)  -- keyset condition
ORDER BY created_at DESC, id DESC
LIMIT 20;
```

**Pros:**

- Consistent performance regardless of position — no OFFSET scan
- Stable — new rows inserted during pagination do not cause duplicates or skips
- Scales to millions of rows

**Cons:**

- Cannot jump to a specific page — only forward (and sometimes backward)
- The cursor is tied to the sort order — changing sort requires starting over
- Slightly more complex to implement

**When to use:** infinite scroll, mobile apps, any API consumed programmatically where page jumping is not needed.

## 4. Keyset Pagination

Keyset pagination (also called seek pagination) is the underlying database technique that cursor pagination uses. Understanding it helps you implement it correctly.

**The core idea:** Instead of skipping rows with OFFSET, use a `WHERE` clause that filters out rows you have already seen. The database uses an index to jump directly to the first relevant row — no scanning.

```sql
-- First page — no cursor
SELECT * FROM orders
WHERE tenant_id = $1
ORDER BY created_at DESC, id DESC
LIMIT 20;

-- Subsequent pages — keyset condition
SELECT * FROM orders
WHERE tenant_id = $1
  AND (created_at, id) < ('2026-10-25T10:00:00Z', 5000)  -- the last row of the previous page
ORDER BY created_at DESC, id DESC
LIMIT 20;
```

**Why it is fast:** The `(created_at, id)` condition with a matching composite index allows the database to seek directly to the first row of the next page — `O(log n)` instead of `O(n)`. Performance is constant regardless of how deep into the result set you go.

**Required index:**

```sql
-- Index must match the ORDER BY columns
CREATE INDEX ON orders (tenant_id, created_at DESC, id DESC);
```

**Handling ties in the sort key:** Always include a unique column (like `id`) as the tiebreaker — otherwise rows with the same `created_at` value may be skipped or duplicated across pages.

**Backward pagination:** To page backward, reverse the comparison operator and sort direction:

```sql
-- Previous page: rows AFTER the cursor in reverse order
SELECT * FROM (
  SELECT * FROM orders
  WHERE tenant_id = $1
    AND (created_at, id) > ('2026-10-25T10:00:00Z', 5000)
  ORDER BY created_at ASC, id ASC
  LIMIT 20
) sub
ORDER BY created_at DESC, id DESC;
```

## 5. API Response Shape & Total Count

**Standard paginated response shape:**

```json
{
  "data": [...],
  "meta": {
    "total":        1284,
    "per_page":     20,
    "current_page": 3,
    "last_page":    65,
    "from":         41,
    "to":           60
  },
  "links": {
    "first": "/api/v1/orders?page=1",
    "prev":  "/api/v1/orders?page=2",
    "next":  "/api/v1/orders?page=4",
    "last":  "/api/v1/orders?page=65"
  }
}
```

**For cursor-based pagination:**

```json
{
  "data": [...],
  "meta": {
    "per_page": 20,
    "has_more":  true
  },
  "links": {
    "next":     "/api/v1/orders?cursor=eyJpZ...",
    "prev":     "/api/v1/orders?cursor=eyJpZ..."
  }
}
```

**The total count problem:** Returning the exact total row count requires a `COUNT(*)` query — which can be expensive on large tables (full table scan or index scan). For tables with millions of rows:

- **Option 1: Always count (simple, acceptable for < 1M rows)** — run `SELECT COUNT(*)` with the same WHERE clause
- **Option 2: Estimated count (PostgreSQL)** — use `pg_class.reltuples` for a fast estimate within \~10% accuracy; acceptable for displaying "about 1.2 million results"
- **Option 3: Cap the count** — count up to a maximum (e.g. `SELECT COUNT(*) ... LIMIT 10001`) and display "10,000+" if exceeded
- **Option 4: Omit the count** — cursor-based responses often omit total and only indicate `has_more: true`

**Consistent page size:**

- Default page size should be documented and consistent — `20` or `50` are common defaults
- Allow clients to override with `?per_page=` up to a maximum (e.g. 100)
- Never allow unlimited page size — `?per_page=0` or `?per_page=999999` must be rejected or capped

## 6. Pagination Checklist

**Strategy choice:**

- [ ] Dataset size considered — offset acceptable for < 10,000 rows, cursor/keyset for larger?
- [ ] Use case considered — page jumping needed (offset) vs infinite scroll (cursor)?
- [ ] Cursor pagination chosen for any mobile app or API consumed programmatically?

**Offset pagination (when used):**

- [ ] `ORDER BY` clause present — never paginate without a consistent sort order?
- [ ] Sort order includes a unique tiebreaker column (e.g. `id`) to prevent duplicate rows?
- [ ] Maximum page depth considered — does this break at high offsets?

**Cursor/keyset pagination (when used):**

- [ ] Composite index created matching the `ORDER BY` columns?
- [ ] Cursor includes a unique tiebreaker to prevent ties?
- [ ] Cursor is opaque to clients — Base64 encoded, not raw column values?
- [ ] Invalid or tampered cursors rejected gracefully (not a 500)?

**Response shape:**

- [ ] Consistent pagination response shape across all list endpoints?
- [ ] `has_more` or `next_cursor` present for cursor-based responses?
- [ ] `total` count included where appropriate and performant?
- [ ] `next` and `prev` links included in response?

**Limits:**

- [ ] Default page size documented?
- [ ] Maximum page size enforced — `?per_page=999999` capped or rejected?
- [ ] All list endpoints paginated — no endpoint returns unlimited results?
