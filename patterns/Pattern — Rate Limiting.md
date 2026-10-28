# Pattern — Rate Limiting

> Read this file before implementing rate limiting beyond a basic throttle middleware.

## 1. When to Read This

Read this file before:

- Implementing rate limiting on auth endpoints (login, register, password reset)
- Rate limiting endpoints that trigger billed third-party calls (SMS OTP, paid APIs)
- Designing rate limits for a public-facing API
- Needing different rate limits per user tier (free vs paid)
- Deploying to multiple servers and needing rate limits to work across all instances

## 2. Rate Limiting Algorithms

**Fixed Window**

- Counts requests in a fixed time window (e.g. 100 requests per minute, window resets at :00)
- Simplest to implement — just an incrementing counter with an expiry
- **Weakness:** a burst at the window boundary (50 requests in the last second of one window + 50 in the first second of the next) allows 100 requests in 2 seconds — double the intended rate
- Acceptable for: general API rate limiting where the boundary burst is tolerable

**Sliding Window**

- Counts requests in a rolling window of the last N seconds — not a fixed reset point
- No boundary burst problem — the window slides continuously
- Slightly more complex to implement — needs a sorted set or timestamp log per key
- **Recommended for most use cases** — more accurate, eliminates the burst window

**Token Bucket**

- Each user has a bucket of tokens; each request consumes a token; tokens refill at a fixed rate
- Allows bursts up to the bucket size, then enforces the steady-state rate
- Best for: APIs where short bursts are acceptable but sustained high rates are not
- Example: a user can burst 20 requests at once, then is limited to 10 per second

**Leaky Bucket**

- Requests enter a queue (the bucket) and are processed at a fixed rate — excess requests are dropped
- Smooths out bursts — output rate is constant regardless of input rate
- Best for: protecting a downstream service from traffic spikes
- Less common for API rate limiting — token bucket is usually preferred

**Recommendation:**

- General API endpoints → **sliding window** (accurate, no burst boundary)
- APIs allowing short bursts → **token bucket**
- Simple auth endpoint protection → **fixed window** (good enough, easiest)

## 3. What to Rate Limit & Thresholds

**Two separate rate limits — always both:**

**Security rate limit** — prevents brute force and abuse:

| Endpoint | Limit | Window | Key |
| --- | --- | --- | --- |
| Login | 5 attempts | 15 minutes | Per IP + per email |
| Register | 10 attempts | 1 hour | Per IP |
| Password reset request | 3 attempts | 1 hour | Per email |
| OTP verification | 5 attempts | 15 minutes | Per phone/email |
| General API | 100–1000 requests | 1 minute | Per authenticated user |
| Public API (no auth) | 20–60 requests | 1 minute | Per IP |

**Cost rate limit** — prevents runaway billing on third-party API calls:

| Action | Limit | Window | Reason |
| --- | --- | --- | --- |
| SMS OTP send | 1 per phone | 60 seconds | Each SMS costs money |
| SMS OTP send | 5 per phone | 1 hour | Abuse prevention |
| Email send | 10 per user | 1 hour | Reputation protection |
| Paid API lookup | 10 per user | 1 minute | Per-request billing |

**Rate limit key — what to key on:**

- Unauthenticated endpoints: key on IP address
- Authenticated endpoints: key on user ID (not IP — users may share IPs)
- Cost-triggering endpoints: key on the target identifier (phone number, email address) — not the requesting user
- For auth bypass attacks: key on both IP AND the target email/phone — limits the attacker on both axes

**Tier-based limits:**

- Free users: lower limits
- Paid users: higher limits
- API key users: limits set per key, configurable
- Store the tier limit in the user record or API key, not hardcoded in middleware

## 4. Distributed Rate Limiting

**The problem:** In-process rate limiting (a counter in application memory) breaks on multi-server deployments. Server A has its own counter, Server B has its own — a user can send N requests to each server and exceed the intended limit.

**The solution: Redis as the shared counter store.**

All servers read and write the same Redis counter. One atomic increment checks and updates the limit across the entire cluster.

**Atomic sliding window in Redis (Lua script):**

```lua
-- Atomic sliding window rate limiter
local key       = KEYS[1]
local now       = tonumber(ARGV[1])  -- current timestamp in ms
local window    = tonumber(ARGV[2])  -- window size in ms
local limit     = tonumber(ARGV[3])  -- max requests allowed

-- Remove expired entries
redis.call('ZREMRANGEBYSCORE', key, 0, now - window)

-- Count current requests in window
local count = redis.call('ZCARD', key)

if count < limit then
  -- Add this request
  redis.call('ZADD', key, now, now)
  redis.call('PEXPIRE', key, window)
  return 0  -- allowed
else
  return 1  -- rate limited
end
```

**Using a library (recommended over rolling your own):**

- Node: `rate-limiter-flexible` — supports Redis, in-memory, PostgreSQL backends
- Laravel: built-in `RateLimiter` facade with Redis backend via `CACHE_DRIVER=redis`
- Hono: `hono/rate-limiter` or custom middleware using `rate-limiter-flexible`

**Cloudflare Workers / Edge:**

- Use Cloudflare Rate Limiting (WAF) for coarse protection at the CDN level
- Use Durable Objects or KV for fine-grained per-user rate limiting at the edge
- Edge rate limiting catches abuse before it reaches your application

**Single-server deployments:**

- In-process rate limiting is acceptable — simpler, no Redis required
- Use a library that can swap backends — start in-process, switch to Redis when you scale

## 5. Response Headers & Client Guidance

When a request is rate limited, return a `429 Too Many Requests` response with headers that tell the client exactly what happened and when to retry. A `429` with no guidance forces the client to guess.

**Standard rate limit headers:**

```
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1730000060
Retry-After: 30
Content-Type: application/json

{ "message": "Too many requests. Please try again in 30 seconds.", "code": "RATE_LIMITED" }
```

**Header meanings:**

- `X-RateLimit-Limit` — the maximum number of requests allowed in the window
- `X-RateLimit-Remaining` — how many requests are left in the current window
- `X-RateLimit-Reset` — Unix timestamp when the window resets / limit restores
- `Retry-After` — seconds until the client should retry (also accepted as an HTTP date)

**Always include these headers on non-limited responses too:** Clients can use `X-RateLimit-Remaining` to throttle themselves proactively — avoiding the `429` entirely. If they only see these headers on `429` responses, they cannot self-throttle.

**Response body rules:**

- Return `429` status — not `200` with an error body
- Include a human-readable message with the retry time: *"Too many login attempts. Please try again in 15 minutes."*
- Include a machine-readable code: `"code": "RATE_LIMITED"`
- Never expose the rate limit key or internal counter values in the response

**Logging rate limit hits:**

- Log every `429` response with: user ID (or IP), endpoint, limit type, timestamp
- A sudden spike in `429` responses for a specific endpoint is a signal of an attack or a buggy client
- Alert when a specific user or IP is hitting rate limits repeatedly

## 6. Rate Limiting Checklist

**Coverage:**

- [ ] Auth endpoints (login, register, password reset) rate limited?
- [ ] OTP send endpoints rate limited per phone/email — not just per user?
- [ ] Cost-triggering endpoints (SMS, paid APIs) have a separate cost rate limit?
- [ ] Public unauthenticated endpoints rate limited by IP?
- [ ] Authenticated endpoints rate limited by user ID?

**Algorithm:**

- [ ] Sliding window used for accuracy-critical limits?
- [ ] Fixed window acceptable for simpler protection?
- [ ] Token bucket considered where short bursts are acceptable?

**Distribution:**

- [ ] Redis used as counter store for multi-server deployments?
- [ ] In-process counter only used on confirmed single-server deployments?
- [ ] Counter increment is atomic — no race conditions?

**Response:**

- [ ] `429` status returned on rate limit — not `200`?
- [ ] `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` headers on all responses?
- [ ] `Retry-After` header on `429` responses?
- [ ] Human-readable message with retry time in response body?

**Security:**

- [ ] Auth endpoints keyed on both IP and target email/phone?
- [ ] Rate limit key does not expose internal user IDs or structure?
- [ ] `429` responses logged with user/IP and endpoint for monitoring?
- [ ] Alerts configured for sustained rate limit spikes on auth endpoints?
