# Pattern — Batch Operations

> Read this file before building any bulk create, update, or delete endpoint.

## 1. When to Read This

Read this file before:

- Building a bulk create endpoint (import 500 products at once)
- Building a bulk update endpoint (mark 200 orders as shipped)
- Building a bulk delete endpoint (archive 100 records)
- Any endpoint that accepts an array of operations in a single request
- Designing an admin tool that operates on multiple records simultaneously

## 2. Batch API Design

**Endpoint structure:**

```
# Batch create
POST /api/v1/orders/batch

# Batch update
PATCH /api/v1/orders/batch

# Batch delete
DELETE /api/v1/orders/batch

# Mixed operations (create, update, delete in one request)
POST /api/v1/orders/batch  (with 'action' field per item)
```

**Request shape:**

```json
// Batch create
{
  "items": [
    { "productId": "prod_01J8K", "quantity": 2, "notes": "Urgent" },
    { "productId": "prod_01J9L", "quantity": 1, "notes": null }
  ]
}

// Batch update — include the ID of each record to update
{
  "items": [
    { "id": "ord_01J8K", "status": "shipped", "trackingNumber": "TRK001" },
    { "id": "ord_01J9L", "status": "shipped", "trackingNumber": "TRK002" }
  ]
}

// Mixed operations
{
  "items": [
    { "action": "create", "data": { ... } },
    { "action": "update", "id": "ord_01J8K", "data": { ... } },
    { "action": "delete", "id": "ord_01J9L" }
  ]
}
```

**Rules:**

- Accept an array of items in the request body — not one item per request
- Each item follows the same validation rules as the single-item endpoint
- Include a client-provided `id` or reference on each item for response mapping — the client needs to know which response corresponds to which input item
- Return a response for each item — success or failure individually
- Use `207 Multi-Status` when some items succeed and some fail (see Section 3)

## 3. Partial Success

In a batch request, some items may succeed and some may fail. How you handle this determines whether your API is usable.

**The wrong approach — fail the whole batch on first error:**

- If item 1 fails validation, items 2–100 are never processed
- The client must fix item 1 and resubmit everything
- If items 2–100 were already processed in a previous call, they get processed again — duplicates

**The right approach — process all items, report individual results:**

**HTTP `207 Multi-Status`** is the correct status code when some succeed and some fail:

```json
// Response: 207 Multi-Status
{
  "results": [
    {
      "index": 0,
      "status": "success",
      "data": { "id": "ord_01J8K", "status": "pending" }
    },
    {
      "index": 1,
      "status": "error",
      "error": {
        "code": "VALIDATION_ERROR",
        "message": "Product not found",
        "field": "productId"
      }
    }
  ],
  "summary": {
    "total":   2,
    "success": 1,
    "failed":  1
  }
}
```

**Status codes for batch endpoints:**

- `200` — all items succeeded
- `207` — some succeeded, some failed
- `400` — the request itself is malformed (not item-level errors)
- `422` — all items failed validation

**Rules:**

- Always return a result for every input item — the client should be able to match each result to its input by `index` or a client-provided reference ID
- Never silently skip failed items — the client must know which failed and why
- Document clearly whether your batch endpoint is transactional (all-or-nothing) or non-transactional (partial success) — see Section 4

## 4. Transactional vs Non-Transactional

This is the most important design decision for a batch endpoint. Choose deliberately and document it clearly.

**Transactional batch (all-or-nothing):**

- If any item fails, all items are rolled back — nothing is saved
- Either the entire batch succeeds or nothing changes
- Return `422` if any item fails validation (before processing begins)
- Return `409` or appropriate error if any item fails during processing

```php
// Transactional batch — wrap in a transaction
DB::transaction(function () use ($items) {
    foreach ($items as $item) {
        $this->createOrder($item); // throws on failure → rolls back all
    }
});
```

**When to use transactional:**

- Items are interdependent — if one fails, the others make no sense
- Data integrity requires all-or-nothing (e.g. double-entry accounting: debit and credit must both succeed)
- The client cannot handle partial results — they just want everything to work or nothing

**Non-transactional batch (partial success):**

- Each item is processed independently
- Successful items are committed; failed items are reported
- Return `207 Multi-Status` with per-item results
- Clients must handle partial success and potentially retry only the failed items

```ts
const results = await Promise.allSettled(
  items.map(item => processItem(item))
);

const response = results.map((result, index) =>
  result.status === 'fulfilled'
    ? { index, status: 'success', data: result.value }
    : { index, status: 'error', error: result.reason.message }
);
```

**When to use non-transactional:**

- Items are independent — one failure should not block others
- The batch is large — rolling back 500 items because item 47 failed is wasteful
- Import/sync scenarios where partial progress is acceptable and preferable

**Always document which mode your endpoint uses** — this is not something clients should have to guess.

## 5. Rate Limiting & Size Limits

**Batch endpoints are high-risk without limits.** A single request that creates 10,000 records can overwhelm your database, exhaust memory, and trigger billed third-party calls at scale.

**Batch size limits:**

- Define a maximum items per batch — typically 100–500 depending on operation cost
- Return `422` or `400` if the batch exceeds the limit with a clear error: *"Maximum 100 items per batch. You sent 250."*
- Document the limit in the API docs — clients need to know to split large imports into chunks
- Consider lower limits for expensive operations (operations that trigger emails, payments, or external API calls)

**Rate limiting batch endpoints separately:**

- A batch of 100 items is not equivalent to 1 request — it is closer to 100 requests in terms of system load
- Rate limit batch endpoints more strictly than single-item endpoints
- Consider counting items processed rather than requests: 500 items per minute, regardless of how many requests they arrive in
- If batch items trigger billed third-party calls: rate limit per item processed, not per request

**Async processing for large batches:**

- Batches over a certain size (e.g. > 100 items) should be processed asynchronously
- Accept the batch, return a `202 Accepted` with a job ID
- Client polls the job endpoint for progress and results
- This prevents HTTP timeouts on large imports and gives the client progress visibility

```json
// 202 Accepted — async processing
{
  "jobId":    "job_01J8K",
  "status":   "queued",
  "statusUrl": "/api/v1/batch-jobs/job_01J8K"
}
```

**Timeout handling:**

- Set a request timeout for synchronous batch endpoints — do not allow a batch to hold an HTTP connection open indefinitely
- If a batch cannot complete within the timeout, switch to async processing

## 6. Batch Operations Checklist

**Design:**

- [ ] Batch endpoint documented as transactional or non-transactional?
- [ ] Each item in the request includes a client-provided reference ID or index for result mapping?
- [ ] Response includes a result entry for every input item — no silent skips?

**Status codes:**

- [ ] `200` returned when all items succeed?
- [ ] `207 Multi-Status` returned when some succeed and some fail?
- [ ] `422` returned when all items fail validation?
- [ ] `400` returned for malformed request (too many items, invalid structure)?

**Partial success:**

- [ ] Per-item error includes which item failed and why?
- [ ] Successful items not rolled back due to other items failing (non-transactional)?
- [ ] Clients documented on how to retry only failed items?

**Transactional batch:**

- [ ] All items validated before any are processed?
- [ ] Single DB transaction wrapping all items?
- [ ] Clear documentation that it is all-or-nothing?

**Limits:**

- [ ] Maximum items per batch defined and enforced?
- [ ] Clear error message when limit exceeded?
- [ ] Rate limiting counts items processed — not just requests?
- [ ] Large batches handled asynchronously with `202 Accepted` and job polling?

**Security:**

- [ ] Each item in the batch authorized individually — not just the batch request?
- [ ] A batch cannot mix records from different tenants?
- [ ] Batch endpoint rate limited more strictly than single-item endpoints?
