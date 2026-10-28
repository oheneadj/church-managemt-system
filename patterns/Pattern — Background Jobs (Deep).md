# Pattern — Background Jobs (Deep)

> Read this file when building complex job workflows beyond a simple queued task — chaining, batching, unique jobs, or long-running progress tracking.

## 1. When to Read This

Read this file when:

- Building a multi-step workflow where jobs must run in sequence (step 2 only starts when step 1 succeeds)
- Running parallel operations and waiting for all to complete before continuing
- Preventing a job from running twice simultaneously for the same resource
- Tracking progress of a long-running job the user is waiting on
- Handling jobs that permanently fail after all retries are exhausted

For basic single queued jobs, the rules in `CLAUDE.md` Section 18 are sufficient. Read this file for complex workflows.

## 2. The Job Contract

Every queued job must declare all four of these. A job missing any of them is incomplete.

```php
// PHP / Laravel example
class ProcessPaymentJob implements ShouldQueue
{
    public int   $tries   = 3;             // max attempts before moving to failed
    public int   $timeout = 60;            // seconds per attempt before killing the job
    public array $backoff = [30, 120, 300]; // seconds to wait between retries (exponential)

    public function handle(): void { /* ... */ }

    public function failed(Throwable $exception): void
    {
        // Explicit permanent failure handling — never silent
        Log::error('Payment job permanently failed', [
            'paymentId' => $this->payment->ulid,
            'error'     => $exception->getMessage(),
        ]);
        $this->payment->update(['status' => PaymentStatus::Failed]);
        // Notify ops team via alert channel
    }
}
```

```ts
// TypeScript / BullMQ example
const processPaymentWorker = new Worker('payments', async (job) => {
  await processPayment(job.data);
}, {
  attempts:  3,
  backoff: { type: 'exponential', delay: 30_000 },
});

processPaymentWorker.on('failed', async (job, error) => {
  if (job?.attemptsMade >= job?.opts.attempts) {
    // Permanent failure — all retries exhausted
    await markPaymentAsFailed(job.data.paymentId, error.message);
    await alertOps(job.data.paymentId, error);
  }
});
```

**Rules:**

- `tries` / `attempts`: 3–5 for most jobs; 1 for jobs that must not be retried (irreversible actions)
- `timeout`: set to 2× the expected maximum duration; never 0 (no timeout)
- `backoff`: exponential — give the external service time to recover between retries
- `failed()`: every job must have explicit permanent failure handling — never let it disappear silently

## 3. Job Chaining & Batching

**Job chaining — sequential execution:** Step 2 only starts if step 1 succeeds. If step 1 fails, the chain stops.

```php
// Laravel — chain runs in sequence
Bus::chain([
    new ValidateOrderJob($order),
    new ReserveInventoryJob($order),
    new ChargePaymentJob($order),
    new SendConfirmationEmailJob($order),
])->catch(function (Throwable $e) use ($order) {
    // Any step failed — handle compensation
    $order->update(['status' => OrderStatus::Failed]);
})->dispatch();
```

**When to use chaining:**

- Steps are sequential and each depends on the previous
- A failure in any step should stop the process
- You need to guarantee order of execution

**Job batching — parallel execution with callback:** All jobs run in parallel. A callback fires when all complete (or any fail).

```php
// Laravel — batch runs in parallel
$batch = Bus::batch([
    new ProcessImageJob($image, 'thumbnail'),
    new ProcessImageJob($image, 'medium'),
    new ProcessImageJob($image, 'large'),
])
->then(function (Batch $batch) use ($image) {
    // All variants processed successfully
    $image->update(['status' => 'processed']);
})
->catch(function (Batch $batch, Throwable $e) use ($image) {
    // At least one variant failed
    $image->update(['status' => 'failed']);
})
->finally(function (Batch $batch) {
    // Runs regardless of success or failure
})
->dispatch();
```

**When to use batching:**

- Multiple independent tasks that can run in parallel
- You need a callback when all complete
- Processing multiple items from a collection

**Batch progress tracking:**

- `$batch->totalJobs` — total number of jobs
- `$batch->pendingJobs` — jobs not yet started
- `$batch->failedJobs` — jobs that have failed
- `$batch->processedJobs()` — jobs completed (succeeded + failed)
- Use these to show progress to the user — see Section 5

## 4. Unique Jobs

A unique job prevents the same job from being dispatched more than once simultaneously for the same resource. Without this, rapid user actions or queue redelivery can result in duplicate processing.

**Use cases:**

- Processing a payment — two simultaneous `ProcessPaymentJob` for the same payment would double-charge
- Sending a notification — two `SendOrderConfirmationJob` for the same order would double-send
- Generating a report — two simultaneous report generators for the same date range are wasteful
- Updating a search index — queueing 100 index updates for the same record, only the last matters

**Laravel — `ShouldBeUnique`:**

```php
class SendOrderConfirmationJob implements ShouldQueue, ShouldBeUnique
{
    // Unique key — what makes this job unique
    public function uniqueId(): string
    {
        return $this->order->ulid;
    }

    // How long to hold the unique lock (seconds)
    // After this time, a new job with the same key can be dispatched
    public int $uniqueFor = 3600;
}
```

**BullMQ — job deduplication:**

```ts
await queue.add('send-confirmation', { orderId }, {
  jobId: `order-confirmation-${orderId}`, // unique job ID = deduplication key
  // If a job with this ID already exists in the queue, the new one is ignored
});
```

**Rules:**

- The unique key must identify the resource, not the job type alone — `order-{ulid}`, not `send-email`
- Set `uniqueFor` / lock TTL to the expected maximum job duration + buffer — prevents a stuck job from blocking all future instances
- `ShouldBeUnique` holds a lock until the job completes; `ShouldBeUniqueUntilProcessing` releases the lock when the job starts — use the latter when you want to allow re-queueing while a job is running
- Test that dispatching the same job twice results in exactly one execution

## 5. Job Progress Tracking

For long-running jobs (report generation, bulk imports, file processing), the user should see real-time progress — not stare at a spinner for 5 minutes not knowing if anything is happening.

**Pattern — progress stored in cache/DB, polled or pushed to frontend:**

```php
// In the job
class GenerateReportJob implements ShouldQueue
{
    public function handle(): void
    {
        $total = $this->report->totalRows();
        $processed = 0;

        foreach ($this->report->rows() as $row) {
            $this->processRow($row);
            $processed++;

            // Update progress every 100 rows
            if ($processed % 100 === 0) {
                Cache::put(
                    "report:{$this->report->ulid}:progress",
                    ['processed' => $processed, 'total' => $total, 'status' => 'running'],
                    now()->addHours(1)
                );
            }
        }

        Cache::put("report:{$this->report->ulid}:progress",
            ['processed' => $total, 'total' => $total, 'status' => 'complete'],
            now()->addHours(1)
        );
    }
}
```

```ts
// Frontend — poll for progress
const pollProgress = async (reportId: string) => {
  const interval = setInterval(async () => {
    const progress = await reportsApi.getProgress(reportId);
    updateProgressBar(progress.processed / progress.total * 100);

    if (progress.status === 'complete' || progress.status === 'failed') {
      clearInterval(interval);
    }
  }, 2000); // poll every 2 seconds
};
```

**Alternatives to polling:**

- WebSocket / SSE broadcast from the job (requires a broadcasting server)
- Polling is simpler and adequate for most use cases — prefer it unless sub-second updates are required

**Progress UX rules:**

- Show percentage complete if total is known; show record count if total is unknown
- Show estimated time remaining once there is enough data to compute it
- Show a "Processing X of Y" indicator — not just a spinner
- If the job fails, show the error and offer a retry action
- Store final result location (file URL, download link) in the progress record when complete

## 6. Dead Letter Queues & Permanent Failure

A dead letter queue (DLQ) is where jobs go after they have exhausted all retry attempts. Without a DLQ, permanently failed jobs disappear — you have no record of what failed, no way to investigate, and no way to retry when the underlying issue is fixed.

**What goes in the DLQ:**

- Jobs that have exceeded `$tries` / `attempts`
- Jobs that throw a non-retryable exception
- Jobs that time out on every attempt

**DLQ setup:**

```php
// Laravel — failed jobs table
// php artisan queue:failed-table && php artisan migrate
// Then: php artisan queue:work --tries=3

// Review failed jobs
php artisan queue:failed

// Retry a specific failed job
php artisan queue:retry {id}

// Retry all failed jobs
php artisan queue:retry all

// Delete all failed jobs
php artisan queue:flush
```

**What to do when a job lands in the DLQ:**

1. Alert — the ops team must know immediately for critical jobs (payments, KYC, orders)
2. Record — update the related DB record to reflect the failure (`status: 'failed'`, `error_message`)
3. Notify — for user-facing failures, notify the user that their action failed and what to do next
4. Investigate — read the error, fix the root cause
5. Retry — once the root cause is fixed, retry from the DLQ

**Non-retryable exceptions:** Some failures should not be retried — the exception carries information that makes retrying futile or dangerous.

```php
use Illuminate\Queue\MaxAttemptsExceededException;

public function handle(): void
{
    try {
        $this->chargeCard();
    } catch (CardDeclinedException $e) {
        // Card was declined — retrying will not help, skip to failed() immediately
        $this->fail($e);
    }
}
```

**DLQ monitoring — required:**

- Alert when any job lands in the DLQ — not only when humans check it manually
- Alert when the DLQ size exceeds a threshold (e.g. > 10 jobs in the payments queue DLQ)
- Review the DLQ in every post-mortem — failed jobs are often symptoms of deeper issues

## 7. Background Jobs Checklist

**Job contract:**

- [ ] Every job declares `tries`, `timeout`, `backoff`, and `failed()` handler?
- [ ] `failed()` handler alerts, logs, and updates DB record — never silent?
- [ ] Non-retryable exceptions call `$this->fail()` immediately?
- [ ] Jobs routed to the correct named queue — not dumped in `default`?

**Chaining:**

- [ ] Sequential dependencies use job chains — not sleep() or manual polling?
- [ ] Chain failure handler handles compensation (rollback, notification)?
- [ ] Each job in a chain is individually idempotent — safe to retry on its own?

**Batching:**

- [ ] Parallel independent tasks use job batches?
- [ ] `then()`, `catch()`, and `finally()` callbacks defined on every batch?
- [ ] Batch progress accessible for long-running batches?

**Unique jobs:**

- [ ] Jobs that must not run twice concurrently implement `ShouldBeUnique` or equivalent?
- [ ] Unique key is specific to the resource — not just the job type?
- [ ] `uniqueFor` / lock TTL set to expected max duration + buffer?
- [ ] Duplicate dispatch tested — second dispatch is ignored?

**Progress tracking:**

- [ ] Long-running jobs update progress in cache or DB?
- [ ] Frontend shows meaningful progress (% complete, record count)?
- [ ] Failed job updates progress record with error message?

**Dead letter queue:**

- [ ] DLQ configured — failed jobs table or equivalent?
- [ ] Alert fires when any job lands in the DLQ?
- [ ] DLQ size monitored — alert on threshold breach?
- [ ] Process defined for investigating and retrying DLQ jobs?
