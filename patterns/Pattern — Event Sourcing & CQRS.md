# Pattern — Event Sourcing & CQRS

> Read this file before deciding to implement event sourcing. This pattern adds significant complexity — understand it fully before committing.

## 1. When to Read This

**Event sourcing is a powerful but expensive pattern.** It fundamentally changes how you store and query data. Apply it only when the benefits clearly outweigh the complexity cost.

**Event sourcing genuinely helps when:**

- A complete audit trail of every state change is a core requirement (finance, healthcare, legal, compliance)
- You need to replay history — "what was the state of this order 3 days ago at 14:30?"
- You need to project the same events into multiple different read models
- The domain is complex enough that temporal queries and event replay provide real business value
- You need to debug production issues by replaying events against a fixed snapshot

**Event sourcing is overkill when:**

- You just need an audit log — use an audit\_logs table (see `docs/patterns/audit-trails.md`)
- Your read and write models are the same — standard CRUD is simpler and cleaner
- The team is not experienced with the pattern — the learning curve is steep
- The domain is simple — event sourcing adds complexity that must earn its keep
- You are building an MVP — optimize for speed of iteration, not correctness of temporal queries

**The honest warning:** Most applications that implement event sourcing discover they did not need it. Start with a good audit trail (Section 36 of `CLAUDE.md`) and only migrate to event sourcing if you find yourself needing replay, temporal queries, or multiple projections from the same event stream.

## 2. Event Sourcing

**The core idea:** instead of storing the current state of an entity, store the sequence of events that led to that state. The current state is derived by replaying all events from the beginning (or from a snapshot).

**Traditional approach (current state):**

```sql
-- Only the current state is stored
SELECT * FROM orders WHERE id = 42;
-- Result: { id: 42, status: 'shipped', amount: 15000, ... }
-- What happened before 'shipped'? Gone.
```

**Event sourcing approach (event store):**

```sql
-- All events are stored
SELECT * FROM order_events WHERE aggregate_id = 'ord_01J8K' ORDER BY sequence;
-- Result:
-- { type: 'OrderPlaced',    sequence: 1, data: { amount: 15000 } }
-- { type: 'PaymentConfirmed', sequence: 2, data: { transactionId: 'txn_...' } }
-- { type: 'OrderShipped',   sequence: 3, data: { trackingNumber: 'TRK001' } }
-- Current state = apply all events in order
```

**The event store table:**

```sql
CREATE TABLE event_store (
  id             bigserial PRIMARY KEY,
  aggregate_type text NOT NULL,          -- 'Order', 'Customer'
  aggregate_id   text NOT NULL,          -- ULID of the aggregate
  sequence       integer NOT NULL,       -- monotonically increasing per aggregate
  event_type     text NOT NULL,          -- 'OrderPlaced', 'PaymentConfirmed'
  event_data     jsonb NOT NULL,         -- the event payload
  metadata       jsonb,                  -- correlation ID, causation ID, user ID
  occurred_at    timestamptz NOT NULL DEFAULT now(),
  UNIQUE (aggregate_id, sequence)        -- prevent gaps or duplicates
);

CREATE INDEX ON event_store (aggregate_id, sequence);
CREATE INDEX ON event_store (event_type, occurred_at);
```

**Replaying events to get current state:**

```ts
async function rehydrateOrder(orderId: string): Promise<Order> {
  const events = await eventStore.getEvents('Order', orderId);
  return events.reduce((order, event) => applyEvent(order, event), new Order());
}

function applyEvent(order: Order, event: StoredEvent): Order {
  switch (event.eventType) {
    case 'OrderPlaced':      return { ...order, status: 'placed', amount: event.data.amount };
    case 'PaymentConfirmed': return { ...order, status: 'paid', paymentId: event.data.paymentId };
    case 'OrderShipped':     return { ...order, status: 'shipped', tracking: event.data.trackingNumber };
    default: return order;
  }
}
```

**Snapshots — for performance on long event streams:**

- Replaying 10,000 events to get current state is slow
- Periodically snapshot the aggregate state: store a point-in-time snapshot
- On rehydration: load the latest snapshot, then replay only events after that snapshot
- Snapshot every N events (e.g. every 100) or on a schedule

## 3. CQRS

**CQRS (Command Query Responsibility Segregation)** separates the write side (commands that change state) from the read side (queries that return data). They can use different models, different databases, and different code paths.

**Why separate reads from writes:**

- Read and write models often have very different shapes — a write needs validation and business rules; a read needs denormalized data optimized for display
- Reads are typically far more frequent than writes — they can be scaled independently
- Read models can be optimized for specific query patterns without affecting the write path

**CQRS with event sourcing:** Event sourcing pairs naturally with CQRS. Events are written to the event store (write side). Read model projectors subscribe to the event stream and build denormalized read models optimized for queries.

```
Write side:                        Read side:
Command → Aggregate → Events   →   Projector → Read Model (flat table)
(enforces business rules)          (denormalized for fast reads)

PlaceOrderCommand                  orders_view table:
  → Order.place()                  { order_id, customer_name, total_formatted,
  → OrderPlaced event                item_count, status_label, placed_at }
  → stored in event_store          (rebuilt by replaying OrderPlaced events)
```

**Read model projections:**

```ts
// Projector — listens to events and updates read models
class OrderProjector {
  async onOrderPlaced(event: OrderPlaced): Promise<void> {
    await db.insert(ordersView).values({
      orderId:         event.aggregateId,
      customerId:      event.data.customerId,
      totalCents:      event.data.totalCents,
      totalFormatted:  formatMoney(event.data.totalCents, event.data.currency),
      status:          'placed',
      statusLabel:     'Order Placed',
      placedAt:        event.occurredAt,
    });
  }

  async onOrderShipped(event: OrderShipped): Promise<void> {
    await db.update(ordersView)
      .set({ status: 'shipped', statusLabel: 'Shipped', trackingNumber: event.data.trackingNumber })
      .where(eq(ordersView.orderId, event.aggregateId));
  }
}
```

**Eventual consistency:**

- The read model is eventually consistent with the write side — there is a short lag between an event being written and the read model being updated
- This is usually acceptable (milliseconds to seconds)
- For queries that must reflect the very latest state (immediately after a write), query the event store directly and rehydrate the aggregate

**CQRS without event sourcing:** CQRS does not require event sourcing. You can use a traditional write model (current state in a normalized table) and maintain separate read models (denormalized tables updated by triggers or application code). Start here if you want CQRS benefits without the full event sourcing commitment.

## 4. Practical Implementation

**Event versioning — events change over time:** As the domain evolves, event schemas change. Old events in the store must still be replayable.

```ts
// Always version your events
const event = {
  type:    'OrderPlaced',
  version: 2,              // v1 had different fields
  data:    { ... },
};

// Upcaster — transforms old event versions to current
function upcast(event: StoredEvent): StoredEvent {
  if (event.type === 'OrderPlaced' && event.version === 1) {
    return {
      ...event,
      version: 2,
      data: {
        ...event.data,
        currency: 'GHS',  // v1 didn't have currency — add default
      },
    };
  }
  return event;
}
```

**Optimistic concurrency — prevent conflicting writes:** When two processes try to write an event to the same aggregate simultaneously, only one should win.

```ts
async function appendEvent(aggregateId: string, event: DomainEvent, expectedSequence: number) {
  try {
    await db.insert(eventStore).values({
      aggregateId,
      sequence: expectedSequence + 1,  // will fail if another event was written first
      eventType: event.type,
      eventData: event.data,
    });
  } catch (error) {
    if (isUniqueViolation(error)) {
      throw new ConcurrencyConflictException(
        'Another event was written. Reload and retry.'
      );
    }
    throw error;
  }
}
```

**Rebuilding read models:** One of the superpowers of event sourcing — you can rebuild any read model from scratch by replaying the event store:

```ts
async function rebuildOrdersView() {
  await db.truncate(ordersView);  // clear existing read model

  const events = await eventStore.getAllEvents('Order');
  const projector = new OrderProjector();

  for (const event of events) {
    await projector.apply(event);
  }
}
```

This means you can:

- Fix a bug in a projector and rebuild the read model correctly from history
- Add a new read model optimized for a new query pattern — just replay all events
- Migrate to a new database schema for read models without losing history

## 5. Event Sourcing & CQRS Checklist

**Decision:**

- [ ] Event sourcing genuinely needed — not just wanting an audit log?
- [ ] Team understands the pattern and its tradeoffs?
- [ ] Simpler alternatives ruled out (audit trail, CQRS without event sourcing)?

**Event store:**

- [ ] `event_store` table with `aggregate_type`, `aggregate_id`, `sequence`, `event_type`, `event_data`?
- [ ] Unique constraint on `(aggregate_id, sequence)` to prevent gaps and duplicates?
- [ ] Events are immutable — never updated or deleted?
- [ ] All events versioned — `version` field on every event type?
- [ ] Upcasters written for each event version change?

**Writing events:**

- [ ] Optimistic concurrency enforced — write fails if expected sequence is wrong?
- [ ] Events written atomically within a transaction?
- [ ] Event data is a snapshot — does not reference mutable external data?

**Replaying and projections:**

- [ ] `applyEvent()` function for every event type?
- [ ] Snapshots implemented for aggregates with long event streams?
- [ ] Read model projectors idempotent — replaying the same event twice is a no-op?
- [ ] Read models can be fully rebuilt by replaying the event store?

**CQRS:**

- [ ] Read models are denormalized for their specific query patterns?
- [ ] Write path (commands) and read path (queries) use separate code?
- [ ] Eventual consistency between write and read sides documented and acceptable?
- [ ] Queries that require immediate consistency read from the event store directly?
