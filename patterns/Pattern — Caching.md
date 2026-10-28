# Pattern — Caching

> Read this file before building any caching layer, adding Redis to a project, or implementing HTTP cache headers.

## 1. When to Read This

Read this file before:

- Adding Redis, Memcached, or any in-memory cache to a project
- Implementing HTTP cache headers (`Cache-Control`, `ETag`, `Last-Modified`)
- Building any feature that says "we should cache this for performance"
- Profiling reveals a query or computation is too slow and caching is the proposed fix
- Setting up a CDN in front of the application

**The cardinal rule of caching:**

> Profile before you cache. Never add a cache because something *might* be slow. Measure it, confirm it is slow, confirm caching is the right fix, then cache it.

Caching is not free — it adds complexity, staleness risk, invalidation bugs, and memory pressure. Every cache entry is a potential source of bugs. Cache only what genuinely needs it.

## 2. What to Cache (and What Not to Cache)

**Good candidates for caching:**

| Data | Why it caches well |
| --- | --- |
| Configuration and settings | Rarely changes; read on every request |
| Reference data | Countries, currencies, categories — static or near-static |
| Aggregated counts | Dashboard stats, notification counts — expensive to compute |
| Expensive query results | Reports, search results, recommendations |
| Rendered fragments | Parts of a page that are identical for all users |
| Third-party API responses | Exchange rates, geocoding, external data with a TTL |
| Session data | User session stored in Redis for fast lookup |

**Poor candidates — never cache these:**

| Data | Why it should not be cached |
| --- | --- |
| Financial balances | Must always be accurate — stale balance = wrong charge or wrong display |
| Inventory stock counts in checkout | Race condition risk — use `lockForUpdate` instead |
| Auth tokens and sessions in shared cache | Security risk — wrong user gets wrong session |
| Personal data (PII) | Privacy risk — wrong user sees wrong data; also retention compliance |
| Data unique to a single request | No benefit — the cache would never be hit again |
| Very frequently mutated data | Invalidation overhead exceeds the read benefit |

**The staleness question — ask before every cache decision:**

- How bad is it if this data is stale for 5 seconds? 60 seconds? 5 minutes?
- If stale data causes incorrect charges, wrong stock display, or security issues → do not cache
- If stale data causes a slightly outdated count or recommendation → safe to cache with a short TTL

## 3. Cache Invalidation Strategies

Cache invalidation is one of the hardest problems in software. Choose the right strategy for each piece of data.

**Strategy 1 — TTL (Time to Live)** The cache entry expires automatically after a fixed duration. The next read after expiry fetches fresh data.

- Best for: reference data, third-party API responses, anything where slight staleness is acceptable
- Set TTL based on how fast the data changes — not on how fast you want the cache to refresh
- Short TTL (5–60 seconds): high-frequency data, dashboard counts
- Medium TTL (5–30 minutes): settings, configuration, category lists
- Long TTL (1–24 hours): near-static reference data, exchange rates
- Never set TTL to 0 (no expiry) on mutable data — you are creating a permanent stale cache

**Strategy 2 — Event-driven invalidation (preferred for mutable data)** Invalidate the cache entry immediately when the underlying data changes.

```
// On write: clear the relevant cache key
await cache.del(`order:${orderId}`);
await cache.del(`user:${userId}:orders`);
```

- Best for: user-specific data, records that change on write
- More precise than TTL — no window of staleness
- Requires discipline: every write path must invalidate the relevant keys
- Use events/listeners to trigger invalidation — keep it out of the business logic itself

**Strategy 3 — Cache-aside (lazy loading)** Check the cache first; on a miss, fetch from DB and populate the cache.

```
async function getOrder(id: string): Promise<Order> {
  const cached = await cache.get(`order:${id}`);
  if (cached) return JSON.parse(cached);

  const order = await orderRepo.findById(id);
  await cache.set(`order:${id}`, JSON.stringify(order), 'EX', 300);
  return order;
}
```

- Most common pattern — works with both TTL and event-driven invalidation
- Risk: cache miss storm on cold start or after invalidation

**Strategy 4 — Write-through** Write to the cache and the DB simultaneously on every write.

- Best for: data that is read very frequently immediately after a write
- Keeps cache warm — no cold misses after a write
- More complex: every write path must update both the DB and the cache atomically

**The golden rule of invalidation:**

> When in doubt, invalidate more aggressively, not less. A cache miss costs a DB query. A stale cache costs a bug.

## 4. Cache Stampede Prevention

A cache stampede (also called a thundering herd) happens when a popular cache key expires and many simultaneous requests all miss the cache at the same time — all of them hit the database simultaneously, overwhelming it.

**When it happens:**

- A high-traffic cache key expires
- After a deploy that clears the cache
- After a manual cache flush
- A cache server restarts

**Prevention Strategy 1 — Mutex / distributed lock** When a cache miss occurs, acquire a lock. Only one process recomputes the value; others wait for the lock to release and then read the freshly-populated cache.

```ts
async function getCachedValue(key: string): Promise<Value> {
  const cached = await cache.get(key);
  if (cached) return JSON.parse(cached);

  // Try to acquire lock — only one process recomputes
  const lockAcquired = await cache.set(`lock:${key}`, '1', 'NX', 'EX', 10);

  if (lockAcquired) {
    const value = await expensiveComputation();
    await cache.set(key, JSON.stringify(value), 'EX', 300);
    await cache.del(`lock:${key}`);
    return value;
  }

  // Another process is recomputing — wait briefly and retry
  await sleep(50);
  return getCachedValue(key);
}
```

**Prevention Strategy 2 — Probabilistic early expiry** Recompute the cache entry slightly before it expires, based on probability that increases as the TTL runs down. Prevents the expiry from ever being reached by multiple simultaneous requests.

**Prevention Strategy 3 — Stale-while-revalidate** Return the stale value immediately while recomputing in the background. The next request gets the fresh value.

- Best UX: no latency spike on recompute
- Risk: one request gets a stale value while recompute is in progress
- Acceptable for: counts, recommendations, non-critical displays
- Not acceptable for: prices, stock levels, anything that affects a transaction

**Prevention Strategy 4 — Cache warming** Pre-populate the cache before it expires, on a schedule, or after a deploy.

- Use a background job to warm critical cache keys after every deploy
- Prevents the first real user request from triggering a cold miss

## 5. Cache Layers

A production application typically has multiple cache layers. Understand what each one does and where it fits.

**Layer 1 — In-process / application memory**

- Fastest possible cache — no network round trip
- Lives in the application process's RAM
- Lost on process restart or deploy
- Not shared between processes — unsuitable for multi-server deployments
- Best for: computed values used repeatedly within a single request, framework-level caching
- Example: memoized function results, compiled templates

**Layer 2 — Distributed cache (Redis / Memcached / Upstash)**

- Shared across all processes and servers
- Survives application restarts (Redis with persistence)
- Sub-millisecond read times over LAN
- Best for: session storage, expensive query results, rate limiting counters, distributed locks
- Redis is the default choice — richer data structures, persistence options, pub/sub, Lua scripting
- Upstash for serverless/edge (HTTP-based Redis, no persistent connections needed)

**Layer 3 — HTTP cache (CDN / reverse proxy)**

- Caches at the network edge — before the request reaches your application
- Controlled via `Cache-Control`, `ETag`, `Last-Modified`, `Vary` headers
- Best for: public, non-personalized content — static assets, public API responses, rendered pages
- Never cache authenticated or personalized responses at the CDN level without `Vary: Authorization` or `Vary: Cookie`
- `Cache-Control: private` for user-specific responses — prevents CDN from caching them

**HTTP cache headers — use precisely:**

| Header | Value | When to use |
| --- | --- | --- |
| `Cache-Control: no-store` | Never cache | Auth endpoints, payment, personal data |
| `Cache-Control: private, max-age=0` | Browser only, no CDN, revalidate | Authenticated pages |
| `Cache-Control: public, max-age=3600` | CDN + browser, 1 hour | Public content that changes rarely |
| `Cache-Control: stale-while-revalidate=60` | Serve stale, revalidate in background | Public content, UX-friendly freshness |
| `ETag: "abc123"` | Conditional GET by content hash | Any cacheable resource |
| `Vary: Accept-Encoding` | Different cache per encoding | Compressed responses |

## 6. Cache Key Design

A good cache key is unique, descriptive, and structured. A bad cache key causes collisions, stale data, or impossible-to-invalidate entries.

**Key structure — use a consistent pattern:**

```
{app}:{version}:{entity}:{identifier}:{variant}
```

Examples:

```
myapp:v1:order:01J8K2M4N5P6Q7R8S9T0U1V2W3          ← single order
myapp:v1:user:01J8K:orders:page:2:status:pending    ← user's paginated orders
myapp:v1:settings:global                             ← global settings
myapp:v1:tenant:acme:dashboard:stats                 ← tenant-scoped stats
```

**Namespacing rules:**

- Always prefix with the app name — prevents collisions in a shared Redis instance
- Include a version segment — lets you invalidate all v1 keys when you change the cache structure
- Include the tenant ID for multi-tenant apps — tenant A must never read tenant B's cache
- Include all parameters that affect the result — if `status=pending` changes the result, it must be in the key

**Tenant isolation in cache keys:**

```ts
// Always scope cache keys by tenant
const key = `${APP}:v1:tenant:${tenantId}:orders:${orderId}`;

// Never use a key without tenant scope for tenant-specific data
const BAD_KEY = `orders:${orderId}`;  // different tenants would share this cache entry
```

**Key versioning for schema changes:** When you change the structure of what you are caching (add a field, change a type), the old cached values are invalid. Bump the version in the key prefix to invalidate all old entries without a manual flush:

```
// Before change: myapp:v1:product:${id}
// After change:  myapp:v2:product:${id}  ← old v1 keys never hit, expire naturally
```

**Key length:**

- Keep keys short — long keys waste memory and slow comparisons
- Use the ULID/UUID as the identifier — never use PII (email, name, phone) in a cache key
- Avoid spaces and special characters — use `:` as the separator

## 7. Cache Poisoning & Security Risks

Caches introduce security risks that do not exist in a purely database-driven system. These are not theoretical — cache poisoning and cross-user data leaks are real production incidents.

**Cache poisoning:** An attacker inserts malicious data into the cache, which is then served to other users.

- Never cache data derived from unvalidated user input without sanitizing it first
- Never cache a response that includes request headers or user-supplied values without normalizing them
- HTTP cache poisoning via unkeyed headers: if your CDN caches responses but ignores certain headers that affect the response body (e.g. `X-Forwarded-Host`), an attacker can poison the cache for other users — always include all response-affecting headers in the cache key via `Vary`

**Cross-user data leaks:** User A's cached data is served to User B.

- This is the most common cache security bug — missing tenant or user scoping in cache keys
- Always include `tenantId` and/or `userId` in cache keys for user-specific data
- For HTTP caching: `Cache-Control: private` on any response that is user-specific; never serve a user-specific response from a shared CDN cache
- Never cache a response that contains a session token, auth token, or personal data in a shared cache layer

**Sensitive data in cache:**

- Never store raw passwords, plaintext tokens, or unencrypted PII in Redis or any external cache
- Treat cache storage as less secure than the database — it typically has fewer access controls and no column-level encryption
- If you must cache sensitive data (e.g. a decrypted field for performance), encrypt it in the cache too and set a short TTL

**Cache key collisions:**

- A poorly designed key can accidentally serve User A's data to User B
- Test: assert that two different users with similar IDs cannot read each other's cache entries
- Test: assert that a tenant-scoped cache key for tenant A is not readable with tenant B's context

## 8. Caching Checklist

**Before adding any cache:**

- [ ] Profiling confirmed this is actually slow — not assumed?
- [ ] Caching is the right fix — not a missing index, N+1 query, or architectural issue?
- [ ] Staleness assessed — how bad is stale data for this specific piece of data?
- [ ] Financial balances, stock counts, and auth data excluded from caching?

**Cache implementation:**

- [ ] Invalidation strategy chosen — TTL, event-driven, or write-through?
- [ ] Every write path that mutates cached data triggers invalidation?
- [ ] TTL set based on how fast the data changes — not arbitrarily?
- [ ] No permanent caches (`TTL = 0`) on mutable data?
- [ ] Stampede prevention for high-traffic keys (lock, stale-while-revalidate, or cache warming)?

**Cache key design:**

- [ ] Keys follow the `{app}:{version}:{entity}:{id}` pattern?
- [ ] Tenant ID included in all keys for tenant-specific data?
- [ ] User ID included in all keys for user-specific data?
- [ ] All parameters affecting the result included in the key?
- [ ] No PII (email, name, phone) in cache keys?
- [ ] Version segment in key prefix so schema changes can invalidate cleanly?

**Security:**

- [ ] No user-specific data in shared CDN/HTTP cache — `Cache-Control: private` set?
- [ ] No sensitive data (tokens, PII) in external cache without encryption?
- [ ] Tenant isolation verified — tenant A cannot read tenant B's cache entries?
- [ ] `Vary` header includes all request headers that affect the cached response?

**Testing:**

- [ ] Cache miss path tested — returns correct data when cache is empty?
- [ ] Cache hit path tested — returns data without hitting the DB?
- [ ] Invalidation tested — stale data is not served after a write?
- [ ] Tenant isolation tested — wrong tenant cannot read another's cache entry?
