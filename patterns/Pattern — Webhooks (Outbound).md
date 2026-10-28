# Pattern — Webhooks (Outbound)

> Read this file before building a system that sends webhooks to external consumers — notifying partner systems, developer integrations, or your own mobile app backend.

## 1. When to Read This

Read this file before:

- Building an integration platform where third-party developers register endpoints to receive events
- Notifying a partner system when something happens in your app (payment confirmed, order status changed)
- Building any feature where your system pushes data to an external URL on an event

This file covers **outbound webhooks** — your system sending to others. For receiving webhooks from payment providers and other external services, see `CLAUDE.md` Section 10 (idempotency, polling fallback).

## 2. Webhook Registration & Endpoint Management

**Webhook endpoints table schema:**

```sql
CREATE TABLE webhook_endpoints (
  id           bigserial PRIMARY KEY,
  ulid         text NOT NULL UNIQUE,
  tenant_id    bigint NOT NULL REFERENCES tenants(id),
  url          text NOT NULL,
  secret       text NOT NULL,        -- HMAC signing secret, stored encrypted
  events       text[] NOT NULL,      -- which event types to receive
  is_active    boolean NOT NULL DEFAULT true,
  created_at   timestamptz NOT NULL DEFAULT now(),
  last_used_at timestamptz
);
```

**Registration rules:**

- Validate the URL format — must be `https://` — never allow `http://` for production webhooks
- Generate a unique secret per endpoint — the consumer uses it to verify signatures
- Store the secret encrypted at the column level — it is sensitive, treat like a password
- Let consumers select which event types they want — do not send all events to all endpoints
- Allow consumers to register multiple endpoints for different event types

**Endpoint validation — send a test event on registration:**

- On registration, send a test ping event to the URL
- If the endpoint does not respond with a `2xx` within 5 seconds, warn the user
- Do not block registration on a failed ping — the endpoint may not be ready yet

**Endpoint health:**

- Track consecutive failures per endpoint
- After N consecutive failures (e.g. 10), automatically disable the endpoint
- Notify the consumer before disabling: *"Your webhook endpoint has been failing. It will be disabled in 24 hours if not fixed."*
- Allow consumers to re-enable a disabled endpoint after fixing it
- Store `last_used_at` — endpoints inactive for 90+ days can be automatically reviewed

## 3. Delivery Guarantees & Retry Strategy

**Webhooks are at-least-once — design for it on both sides.** Your system will retry failed deliveries. Consumers must be idempotent — processing the same webhook twice should produce the same result as processing it once.

**Delivery flow:**

```
Event occurs
  → Queued delivery job dispatched for each registered endpoint
    → Job sends HTTP POST to endpoint URL
      → 2xx response → mark as delivered, log success
      → Non-2xx or timeout → mark as failed, schedule retry
        → Retry with exponential backoff
          → After max retries → mark as permanently failed, alert consumer
```

**Retry strategy:**

- Retry on any non-`2xx` response code and on network timeout
- Do NOT retry on `4xx` responses that indicate a permanent error (e.g. `410 Gone` = endpoint deleted)
- Exponential backoff: 30s, 5m, 30m, 2h, 12h, 24h
- Maximum retries: 6–10 attempts over \~24 hours
- After max retries: mark delivery as permanently failed, notify the consumer

**Timeout:**

- Webhook delivery must time out after 10–30 seconds
- A slow consumer endpoint should not hold your delivery job open indefinitely
- Consumers should acknowledge immediately (return `200`) and process asynchronously

**Delivery order:**

- Webhooks are not guaranteed to arrive in the order events occurred
- Include an `event.createdAt` timestamp in every payload
- Include an event sequence number if ordering is critical for your consumers
- Document this limitation clearly in your webhook documentation

**Do not retry `410 Gone`:**

```ts
if (response.status === 410) {
  // Consumer has explicitly said this endpoint is gone — stop retrying
  await disableEndpoint(endpoint.ulid);
  return;
}
```

## 4. Signing Webhook Payloads

Consumers need to verify that webhook deliveries came from your system — not from an attacker who learned their endpoint URL. Signing with HMAC solves this.

**How it works:**

1. You generate a unique secret per consumer endpoint on registration
2. On every delivery, you sign the payload with that secret using HMAC-SHA256
3. You include the signature in a request header
4. The consumer recomputes the signature using their secret and compares — if they match, the payload is authentic

**Signing implementation:**

```ts
import { createHmac } from 'crypto';

function signPayload(payload: string, secret: string): string {
  return createHmac('sha256', secret)
    .update(payload)
    .digest('hex');
}

async function deliverWebhook(endpoint: WebhookEndpoint, event: WebhookEvent) {
  const payload   = JSON.stringify(event);
  const timestamp = Date.now().toString();
  // Include timestamp in signature to prevent replay attacks
  const signature = signPayload(`${timestamp}.${payload}`, endpoint.secret);

  await fetch(endpoint.url, {
    method: 'POST',
    headers: {
      'Content-Type':           'application/json',
      'X-Webhook-Signature':    `sha256=${signature}`,
      'X-Webhook-Timestamp':    timestamp,
      'X-Webhook-ID':           event.ulid,  // for idempotency on consumer side
    },
    body: payload,
  });
}
```

**Consumer verification:**

```ts
function verifyWebhookSignature(req: Request, secret: string): boolean {
  const signature = req.headers['x-webhook-signature']?.replace('sha256=', '');
  const timestamp = req.headers['x-webhook-timestamp'];
  const payload   = req.body; // raw string, not parsed

  // Reject if timestamp is more than 5 minutes old (replay attack prevention)
  if (Math.abs(Date.now() - Number(timestamp)) > 300_000) return false;

  const expected = createHmac('sha256', secret)
    .update(`${timestamp}.${payload}`)
    .digest('hex');

  // Use timing-safe comparison to prevent timing attacks
  return timingSafeEqual(Buffer.from(signature), Buffer.from(expected));
}
```

**Rules:**

- Include a timestamp in the signed string — prevents replay attacks where an attacker re-sends a captured valid webhook
- Use `timingSafeEqual` for comparison — not `===` which leaks timing information
- Reject requests with timestamps older than 5 minutes
- Document the signing algorithm and provide verification examples in your developer docs
- Allow consumers to rotate their secret without downtime: accept the old and new secret during a rotation window

## 5. Webhook Logs & Delivery Receipts

Consumers need visibility into their webhook deliveries — what was sent, when, and whether it succeeded. Without this, debugging integration failures is extremely difficult.

**Webhook delivery log table:**

```sql
CREATE TABLE webhook_deliveries (
  id              bigserial PRIMARY KEY,
  ulid            text NOT NULL UNIQUE,
  endpoint_id     bigint NOT NULL REFERENCES webhook_endpoints(id),
  event_type      text NOT NULL,
  event_ulid      text NOT NULL,     -- the event that triggered this delivery
  payload         jsonb NOT NULL,    -- what was sent
  status          text NOT NULL,     -- pending, delivered, failed, abandoned
  attempts        integer NOT NULL DEFAULT 0,
  response_code   integer,           -- HTTP status code from the consumer
  response_body   text,              -- first 1000 chars of consumer response
  error_message   text,              -- network error message if applicable
  next_attempt_at timestamptz,
  delivered_at    timestamptz,
  created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX ON webhook_deliveries (endpoint_id, created_at DESC);
CREATE INDEX ON webhook_deliveries (status, next_attempt_at) WHERE status = 'pending';
```

**What to log:**

- Every attempt — not just the final outcome
- Request headers sent (excluding the signing secret)
- Response code and first 1000 characters of response body
- Duration of the request
- Error message for network failures

**Consumer-facing delivery dashboard:**

- Show the last 50 deliveries per endpoint
- Show delivery status, event type, timestamp, and response code
- Allow consumers to view the full payload for debugging
- Allow consumers to manually retry a failed delivery
- Do not expose the signing secret in the dashboard — only show that one exists

**Retention:**

- Keep delivery logs for 30 days — long enough for consumers to debug issues
- Delete after 30 days — webhook logs accumulate quickly at scale

## 6. Outbound Webhooks Checklist

**Registration:**

- [ ] HTTPS-only endpoints — `http://` rejected?
- [ ] Unique secret generated per endpoint, stored encrypted?
- [ ] Consumers can select which event types to receive?
- [ ] Test ping sent on registration?
- [ ] Endpoint health tracking — auto-disable after N consecutive failures?

**Delivery:**

- [ ] Webhook delivery via background queue — never synchronous in request/response?
- [ ] Exponential backoff retry strategy (30s → 5m → 30m → 2h → 12h → 24h)?
- [ ] 10–30 second timeout per attempt?
- [ ] `410 Gone` treated as permanent — endpoint disabled, no further retries?
- [ ] Maximum retry count with consumer notification on permanent failure?

**Signing:**

- [ ] Every payload signed with HMAC-SHA256 using the endpoint's secret?
- [ ] Timestamp included in the signed string (replay attack prevention)?
- [ ] `X-Webhook-Signature`, `X-Webhook-Timestamp`, and `X-Webhook-ID` headers on every request?
- [ ] Signing algorithm documented with verification code examples?
- [ ] Secret rotation supported without downtime?

**Logging:**

- [ ] `webhook_deliveries` table logging every attempt?
- [ ] Response code and body captured on every attempt?
- [ ] Consumer-facing delivery dashboard available?
- [ ] Manual retry from dashboard available to consumers?
- [ ] 30-day retention with automated cleanup?

**Payload design:**

- [ ] Event type field in every payload?
- [ ] Event ULID for consumer-side idempotency?
- [ ] `created_at` timestamp for ordering?
- [ ] No integer primary keys in payload — ULIDs only?
