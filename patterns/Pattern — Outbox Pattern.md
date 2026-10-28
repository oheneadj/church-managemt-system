# Pattern — Outbox Pattern

> Read this file before publishing domain events, sending messages to a queue, or triggering webhooks alongside a database write.

## 1. When to Read This

Read this file when:

- Publishing a domain event alongside a database write (e.g. `OrderPlaced` when creating an order)
- Sending a message to a queue as a side effect of a database transaction
- Triggering a webhook after a record is saved
- Sending a notification that must not be lost if the application crashes
- Any situation where two systems must be updated atomically but do not share a transaction

**The problem this pattern solves in one sentence:** You cannot atomically write to a database and publish to a message broker — if either fails, they diverge, and you get a DB write without an event, or an event without a DB write.

## 2. The Problem — Dual Write

Consider the most common pattern for publishing an event after saving a record:

```ts
// BROKEN — naive dual write
async function createOrder(dto: CreateOrderDTO): Promise<Order> {
  const order = await orderRepo.create(dto);    // Step 1: write to DB
  await eventBus.publish('order.placed', order); // Step 2: publish event
  return order;
}
```

This looks correct but has two critical failure modes:

**Failure Mode 1 — DB succeeds, event publish fails:**

- The order is created in the database
- The event publish fails (network error, broker unavailable, app crash)
- No `order.placed` event is ever published
- The email confirmation is never sent, the inventory is never decremented, the fulfillment system never picks up the order
- The order exists in the DB but no downstream system knows about it

**Failure Mode 2 — Event published, DB write fails (within a transaction):**

- Less common but possible if the event is published before the transaction commits
- Downstream systems act on an order that does not exist in the DB
- Idempotent consumers may reject the retry when the DB write eventually succeeds

**Why wrapping in a transaction does not help:**

```ts
// STILL BROKEN — transactions don't span two systems
await db.transaction(async (tx) => {
  const order = await orderRepo.create(dto, tx);
  await eventBus.publish('order.placed', order); // outside the DB transaction
  // if publish fails after commit → event lost
  // if publish succeeds but commit fails → phantom event
});
```

A database transaction provides atomicity for database operations only. The event broker is a separate system — it cannot participate in a database transaction.

## 3. The Outbox Pattern — How It Works

The key insight: instead of publishing events directly, write them to a special table (`outbox`) in the same database transaction as the business write. A separate relay process reads the outbox and publishes the events. The DB transaction guarantees atomicity between the business record and the outbox entry.

```
Step 1 — Inside one DB transaction:
  → Write the order record to the orders table
  → Write the event record to the outbox table
  → Commit transaction
  → Both succeed or both fail — atomically

Step 2 — Outbox relay (separate background process):
  → Poll the outbox table for unpublished events
  → Publish each event to the message broker
  → Mark the outbox entry as published
  → Repeat
```

**Why this works:**

- The DB transaction guarantees the order record and outbox entry are always in sync
- If the app crashes after the transaction but before the relay publishes — the relay picks it up on next run
- If the broker is unavailable — the outbox entry stays unpublished and the relay retries
- The business write never fails because of a broker problem

**The pattern in code:**

```ts
// CORRECT — outbox pattern
async function createOrder(dto: CreateOrderDTO): Promise<Order> {
  return db.transaction(async (tx) => {
    // Business write
    const order = await orderRepo.create(dto, tx);

    // Write event to outbox — same transaction
    await tx.insert(outbox).values({
      ulid:       generateUlid(),
      eventType:  'order.placed',
      payload:    JSON.stringify(order),
      status:     'pending',
      createdAt:  new Date(),
    });

    return order;
    // Both commits atomically — or both roll back
  });
}

// Relay — runs separately, retries safely
async function relayOutboxEvents() {
  const events = await db.select().from(outbox)
    .where(eq(outbox.status, 'pending'))
    .limit(100)
    .orderBy(outbox.createdAt);

  for (const event of events) {
    try {
      await eventBus.publish(event.eventType, JSON.parse(event.payload));
      await db.update(outbox)
        .set({ status: 'published', publishedAt: new Date() })
        .where(eq(outbox.ulid, event.ulid));
    } catch (error) {
      await db.update(outbox)
        .set({ attempts: sql`attempts + 1`, lastError: error.message })
        .where(eq(outbox.ulid, event.ulid));
    }
  }
}
```

## 4. Implementation

**Outbox table schema:**

```sql
CREATE TABLE outbox (
  id           bigserial PRIMARY KEY,
  ulid         text NOT NULL UNIQUE,
  event_type   text NOT NULL,
  payload      jsonb NOT NULL,
  status       text NOT NULL DEFAULT 'pending',  -- pending, published, failed
  attempts     integer NOT NULL DEFAULT 0,
  last_error   text,
  created_at   timestamptz NOT NULL DEFAULT now(),
  published_at timestamptz
);

CREATE INDEX ON outbox (status, created_at) WHERE status = 'pending';
```

**The relay process:**

- Runs on a short interval (1–5 seconds) as a scheduled job or a dedicated worker
- Fetches a batch of pending events (100 at a time — not all at once)
- Publishes each event and marks it as published
- On failure, increments the attempt counter and records the error
- After a maximum number of attempts (e.g. 10), marks the event as `failed` and alerts
- Uses row-level locking (`SELECT ... FOR UPDATE SKIP LOCKED`) to prevent multiple relay instances from processing the same event

```sql
-- Fetch with lock to prevent concurrent relay instances
SELECT * FROM outbox
WHERE status = 'pending' AND attempts < 10
ORDER BY created_at
LIMIT 100
FOR UPDATE SKIP LOCKED;
```

**Cleanup:**

- Published events accumulate in the outbox table — clean them up on a schedule
- Keep published events for 7 days for debugging, then delete
- Never delete pending or failed events automatically — they need human review

**Ordering guarantee:**

- The outbox table preserves creation order — events for the same entity are published in the order they were written
- For strict per-entity ordering, add an `aggregateId` column and process events per aggregate in order
- For loose ordering (most cases), processing by `created_at` is sufficient

## 5. Idempotency on the Consumer Side

The outbox relay delivers events **at least once** — if the relay publishes an event and then crashes before marking it as published, it will publish the event again on retry. Consumers must be idempotent.

**What idempotent means here:** Processing the same event twice produces the same result as processing it once. The second processing is a no-op.

**How to implement idempotent consumers:**

```ts
async function handleOrderPlaced(event: OrderPlacedEvent) {
  // Check if already processed
  const alreadyProcessed = await processedEvents.findOne({
    where: { eventId: event.ulid, handlerName: 'send_order_confirmation' }
  });
  if (alreadyProcessed) return; // idempotent — skip

  // Process
  await emailService.sendOrderConfirmation(event.orderId);

  // Record as processed — in the same transaction as any DB writes
  await processedEvents.create({
    eventId: event.ulid,
    handlerName: 'send_order_confirmation',
    processedAt: new Date(),
  });
}
```

**Processed events table:**

```sql
CREATE TABLE processed_events (
  event_id      text NOT NULL,
  handler_name  text NOT NULL,
  processed_at  timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (event_id, handler_name)
);
```

**Rules:**

- Every event consumer records the event ID and handler name in a `processed_events` table
- Check this table at the start of every handler before doing any work
- Write the processed record in the same DB transaction as any business writes the handler performs
- Clean up old processed event records after a retention period (30 days is usually sufficient)
- Never rely on the event broker's deduplication alone — it is not guaranteed

## 6. When to Use (and When Not To)

**Use the outbox pattern when:**

- A domain event must be published every time a record is written — no exceptions
- A webhook must be sent when a payment is confirmed, an order is placed, or a status changes
- A notification must be sent and the notification must not be lost if the app crashes
- A downstream service must be notified and missing even one notification would cause data inconsistency
- You are building a system where eventual consistency is acceptable but eventual delivery is required

**Do not use the outbox pattern when:**

- The side effect is optional — a failed notification is acceptable and not retried
- The side effect must happen synchronously in the same request (user needs immediate feedback)
- The volume is very low and the complexity is not justified — a simple queued job is sufficient
- You are using a framework that provides built-in at-least-once event delivery with DB integration (Laravel's job batching with a DB queue driver provides similar guarantees for many use cases)

**Simpler alternatives for common use cases:**

| Need | Outbox pattern | Simpler alternative |
| --- | --- | --- |
| Send email after order | Only if email must never be lost | Queued job dispatched in the same transaction |
| Notify webhook on payment | Yes — missing a payment webhook is critical | — |
| Update search index | No — can be rebuilt | Queued job, periodic sync |
| Send push notification | No — losing one is acceptable | Queued job |
| Sync to external CRM | Depends on criticality | Scheduled job with polling |

**Laravel note:** In Laravel, dispatching a job `->onQueue()` inside a `DB::transaction()` via `dispatch()` defers the dispatch until after the transaction commits — this is nearly equivalent to the outbox pattern for most use cases and is the recommended approach unless you need cross-service reliability or the job broker is separate from the DB.

## 7. Outbox Pattern Checklist

**Schema:**

- [ ] `outbox` table created with `ulid`, `event_type`, `payload`, `status`, `attempts`, `created_at`?
- [ ] Index on `(status, created_at)` filtered to `status = 'pending'`?
- [ ] `processed_events` table created for consumer idempotency?

**Publishing (producer side):**

- [ ] Outbox entry written in the same DB transaction as the business record?
- [ ] No direct event broker calls from inside a business transaction?
- [ ] Relay process fetches pending events with `FOR UPDATE SKIP LOCKED`?
- [ ] Relay retries with exponential backoff on failure?
- [ ] Failed events (max attempts exceeded) trigger an alert?
- [ ] Published events cleaned up after 7 days?

**Consuming (consumer side):**

- [ ] Every event handler checks `processed_events` before processing?
- [ ] Processed event record written in the same transaction as handler's DB writes?
- [ ] Consumer handles duplicate delivery gracefully — no double-sends, double-charges, double-creates?

**Testing:**

- [ ] Test that outbox entry is created when the business record is written?
- [ ] Test that relay publishes the event and marks it published?
- [ ] Test that relay retries on broker failure?
- [ ] Test that consumer processing the same event twice is a no-op?
- [ ] Test that if the relay crashes mid-publish, the event is re-published on next run?
