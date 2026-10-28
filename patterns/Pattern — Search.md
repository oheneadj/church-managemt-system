# Pattern — Search

> Read this file before building any search feature beyond a simple SQL LIKE query.

## 1. When to Read This

Read this file before:

- Building a product catalogue search, user search, or document search
- Adding a search bar to any list view where users need to find items by text
- Implementing filtering by multiple attributes alongside text search
- The current SQL LIKE search is too slow or producing poor results
- Needing typo tolerance, relevance ranking, or faceted filtering

## 2. Database Full-Text vs Dedicated Search Engine

**Start with database full-text search. Only add a dedicated engine when you hit its limits.**

**Database full-text search (PostgreSQL `tsvector`, MySQL FULLTEXT):**

- Built into the database — no additional infrastructure
- Good for: moderate data volumes (up to a few million rows), basic relevance ranking
- Supports: stemming, stop words, ranking by frequency
- Does not support: typo tolerance, faceted filtering, geo search, synonym handling out of the box
- Use this for: internal admin search, simple user-facing search with small datasets

```sql
-- PostgreSQL full-text search
SELECT * FROM cars
WHERE to_tsvector('english', make || ' ' || model || ' ' || description)
  @@ plainto_tsquery('english', 'toyota corolla')
ORDER BY ts_rank(
  to_tsvector('english', make || ' ' || model),
  plainto_tsquery('english', 'toyota corolla')
) DESC;
```

**When to add a dedicated search engine:**

- Full-text search is too slow on your dataset size
- Users expect typo tolerance (searching "corola" finds "Corolla")
- You need faceted filtering (filter by make, year, price range simultaneously)
- You need highlighted snippets showing where the match occurred
- You need geo search (find cars within 50km)
- Search relevance is a core product feature — not just a utility

**The rule: use SQL until it genuinely cannot meet the requirement — then add the engine.** A search engine adds operational complexity, a sync layer, and another service to monitor. Do not add it speculatively.

## 3. Search Engine Options

| Engine | Hosting | Best for | Notes |
| --- | --- | --- | --- |
| **Meilisearch** | Self-hosted or Meilisearch Cloud | Laravel Scout default, general purpose | Excellent typo tolerance, instant search, easy setup |
| **Typesense** | Self-hosted or Typesense Cloud | Performance-critical, geo search | Faster than Meilisearch, slightly more complex |
| **Elasticsearch / OpenSearch** | Self-hosted | Complex queries, log analytics, large scale | Very powerful, very complex to operate |
| **Algolia** | SaaS only | Fast time-to-market, no ops overhead | Expensive at scale, excellent DX |
| **PostgreSQL full-text** | Your existing DB | Moderate scale, simple needs | Free, already there, good enough for many apps |

**Recommended for most projects:**

- **Laravel projects:** Meilisearch via Laravel Scout — first-class integration, simple driver swap
- **Node/TypeScript projects:** Typesense or Meilisearch — both have excellent TypeScript SDKs
- **When ops complexity is a concern:** Algolia (SaaS) or Meilisearch Cloud
- **When budget is a concern:** Meilisearch or Typesense self-hosted

**Avoid Elasticsearch/OpenSearch for new projects unless:**

- The dataset is enormous (billions of documents)
- The team has prior Elasticsearch experience
- Log analytics or complex aggregations are a core requirement

Elasticsearch is a powerful tool but overkill for product search on most applications — its complexity is not justified by the results.

## 4. Index Design

**What to index:**

- Only the fields users will search against — not the entire model
- Searchable text fields: name, title, description, make, model, SKU, reference number
- Filterable fields: status, category, price range, year, location — these are not searched as text, but filtered
- Sortable fields: price, created\_at, popularity — users sort by these
- Never index passwords, tokens, internal IDs, or sensitive PII in a search engine

**Field weights — not all fields are equal:**

- A match in the title is more relevant than a match in the description
- Configure field weights to reflect this: title × 10, tags × 5, description × 1
- Most search engines support this natively — configure it in the index settings

**Meilisearch index configuration example:**

```ts
await client.index('cars').updateSettings({
  searchableAttributes: [
    'make',          // highest weight (first in list)
    'model',
    'year',
    'description',   // lowest weight (last)
  ],
  filterableAttributes: ['status', 'make', 'year', 'price_usd_cents'],
  sortableAttributes: ['price_usd_cents', 'created_at'],
  displayedAttributes: ['ulid', 'make', 'model', 'year', 'price_usd_cents', 'status'],
  // Never display: id, tenant_id, sensitive fields
});
```

**What to store in the index document:**

- Only what is needed to display search results — not the full model
- Use the ULID as the document ID and include it in displayed attributes
- Never store the integer primary key in the search index
- Include denormalized data for display: `category_name` alongside `category_id` so you do not need a DB join to render results
- Pre-format money fields: store both `price_usd_cents: 15000` and `price_formatted: '$150.00'`

**Multi-tenant search:**

- Always filter by `tenant_id` on every search query — never return cross-tenant results
- Either maintain a separate index per tenant (clean isolation, more overhead) or use a single index with a mandatory `tenantId` filter
- The mandatory filter approach is simpler but requires every query to include it — enforce this at the search service layer, not the route handler

## 5. Keeping Indexes in Sync

The search index is a derived view of your database. It will drift out of sync if you do not actively maintain it. Choose a sync strategy before going to production.

**Strategy 1 — Real-time sync via model observer / event listener (recommended):**

```ts
// After every create/update/delete, update the index
OrderObserver.afterSave(async (order) => {
  await searchIndex.upsert(order.toSearchDocument());
});

OrderObserver.afterDelete(async (order) => {
  await searchIndex.delete(order.ulid);
});
```

- Lowest latency — index is updated within seconds of a DB change
- Requires every write path to trigger the observer — can miss updates from bulk operations or raw SQL
- Queue the index update as a background job — do not block the HTTP response on it

**Strategy 2 — Queue-based sync:**

- Write events to a queue (`SearchIndexQueue`) whenever a relevant record changes
- A worker consumes the queue and updates the search index
- Decoupled — the application does not depend on the search engine being available
- Slightly higher latency (seconds to minutes) but more reliable

**Strategy 3 — Scheduled full re-index:**

- A scheduled job rebuilds the entire index from the database on a schedule (nightly)
- Simple — no observer or queue needed
- High latency — changes take up to 24 hours to appear in search
- Only acceptable for data that changes infrequently (product catalogue, blog posts)
- Use as a safety net alongside real-time sync — catches anything that slipped through

**Bulk operations — always trigger a re-index:**

- `UPDATE orders SET status = 'shipped' WHERE ...` bypasses model observers in most ORMs
- Any bulk operation must explicitly trigger a re-index job for the affected records
- After any data migration that touches indexed fields, trigger a full re-index

**Initial indexing:**

- When first setting up search, run a one-time full indexing job
- Index in batches — not all records at once
- Run during off-peak hours — indexing is CPU and memory intensive

## 6. Search UX Patterns

**Instant search (search-as-you-type):**

- Trigger search after 2–3 characters — not on every keystroke
- Debounce the search input by 200–300ms — prevents a query on every keypress
- Show a loading state while the query is in flight
- Display results in a dropdown overlay — not a full page reload
- Allow keyboard navigation through results (arrow keys, Enter, Escape)

**Typo tolerance:**

- Most dedicated search engines have typo tolerance built in
- Configure the tolerance level — how many typos are allowed per word length
- Test with real misspellings from your domain: "corola" → "Corolla", "toyoto" → "Toyota"

**Faceted filtering:**

- Let users filter by multiple attributes simultaneously: make, year, price range, status
- Show the count of results per facet value: `Toyota (42)`, `Honda (18)`
- Update facet counts as filters are applied — remaining options change
- Never show facet values with 0 results — they are confusing
- Persist filter state in URL params — shareable, bookmarkable, survives a refresh

**Pagination on search results:**

- Use offset pagination for small result sets (< 10,000 results)
- Use cursor-based pagination for large result sets — offset pagination degrades at depth
- Most search engines limit deep pagination (Meilisearch: max offset 20,000) — plan for this
- Show total result count: "42 results for 'Toyota'"

**Empty states:**

- When search returns no results: show a helpful message and suggest alternatives
- "No results for 'corolla 2030'" → suggest removing the year filter
- Never show a blank page — always guide the user toward finding what they need

**Result highlighting:**

- Highlight the matching terms in the result title and description
- Most search engines return highlight snippets — use them
- This helps users understand why a result was returned

## 7. Search Checklist

**Engine choice:**

- [ ] Database full-text search evaluated first — only adding a dedicated engine when genuinely needed?
- [ ] Engine chosen based on actual requirements (typo tolerance, facets, scale)?

**Index design:**

- [ ] Only relevant fields indexed — passwords, tokens, sensitive PII excluded?
- [ ] Searchable, filterable, and sortable attributes configured separately?
- [ ] Field weights configured — title weighted higher than description?
- [ ] Integer primary keys not stored in the search index — ULID used as document ID?
- [ ] Money fields stored as both raw integer and formatted string in the index?
- [ ] Multi-tenant: `tenantId` filter applied to every search query at the service layer?

**Sync:**

- [ ] Sync strategy chosen — real-time, queue-based, or scheduled?
- [ ] Index updates queued as background jobs — not blocking HTTP responses?
- [ ] Bulk operations explicitly trigger re-index for affected records?
- [ ] Nightly full re-index as a safety net?
- [ ] Initial indexing run for existing data before search goes live?

**Security:**

- [ ] Multi-tenant search always filtered by tenantId — cross-tenant results impossible?
- [ ] Search does not expose fields not in `displayedAttributes`?
- [ ] Rate limiting on search endpoints?

**UX:**

- [ ] Search input debounced (200–300ms)?
- [ ] Loading state shown during search?
- [ ] Filter state persisted in URL params?
- [ ] Empty state handled with helpful messaging?
- [ ] Result count displayed?
- [ ] Matching terms highlighted in results?
