# Monitoring & Alerting

> What we watch, what we alert on, and what to do when an alert fires. Every alert has an owner and a first action. If you're reading this at 2am because something is on fire, the On-Call Runbook section is what you need.

---

## Observability Stack

| Layer | Tool | What It Watches |
|---|---|---|
| Application logs | Laravel Log (JSON → file) | Exceptions, errors, warnings, slow queries |
| Application performance | Laravel Telescope (dev/staging only) | Queries, jobs, HTTP requests, exceptions |
| Uptime | Coolify built-in + UptimeRobot (free tier) | HTTP health check every 60 seconds |
| Infrastructure | Oracle Cloud metrics | CPU, RAM, disk, network egress |
| Queue health | Custom `queue:monitor` command | Queue depth, failed jobs |
| Integration health | Custom health-check command | Paystack, AT, Meta, R2 reachability |
| Error tracking | Laravel Log + Slack webhook (v2: Sentry) | Unhandled exceptions in production |

**MVP reality:** Sentry, Datadog, and New Relic are v2 additions. MVP monitoring is UptimeRobot + structured logs + queue depth checks. It's enough to know when something is broken and why.

---

## Log Structure

All logs are structured JSON for easy parsing:

```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver'   => 'stack',
        'channels' => ['daily'],
    ],
    'daily' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/laravel.log'),
        'level'  => env('LOG_LEVEL', 'warning'),
        'days'   => 14,
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
    ],
],
```

### Log Level Policy

| Level | When to use | Examples |
|---|---|---|
| `error` | Something broke that shouldn't have | Job failed after all retries, payment processing error, DB connection lost |
| `warning` | Something unexpected but recoverable | OTP attempt limit hit, SMS credit balance low, webhook with unknown event type |
| `info` | Significant business events | Contribution created, church onboarded, export completed |
| `debug` | Low-level detail | Only in development. Never in production. |

**Production log level: `warning`** — info and debug are too noisy at scale and expose business data in logs unnecessarily.

---

## Health Checks

### `/api/health` — Application Health

```php
Route::get('/health', function () {
    $checks = [
        'database' => function() {
            DB::connection()->getPdo();
            return 'ok';
        },
        'redis' => function() {
            return Redis::ping() === 'PONG' ? 'ok' : 'error';
        },
        'storage' => function() {
            Storage::disk('r2')->put('health-check.txt', 'ok');
            return 'ok';
        },
    ];

    $results = [];
    $allOk   = true;

    foreach ($checks as $name => $check) {
        try {
            $results[$name] = $check();
        } catch (\Throwable $e) {
            $results[$name] = 'error';
            $allOk          = false;
            Log::error("Health check failed: {$name}", ['error' => $e->getMessage()]);
        }
    }

    return response()->json([
        'status'    => $allOk ? 'ok' : 'degraded',
        'checks'    => $results,
        'timestamp' => now()->toIso8601String(),
    ], $allOk ? 200 : 503);
});
```

### `/api/health/queues` — Queue Worker Health

```php
Route::get('/health/queues', function () {
    $queues = ['critical', 'notifications', 'payments', 'reports', 'exports', 'scheduled'];
    $sizes  = [];

    foreach ($queues as $queue) {
        $sizes[$queue] = Queue::size($queue);
    }

    $failedCount = DB::table('failed_jobs')->count();

    return response()->json([
        'queue_depths' => $sizes,
        'failed_jobs'  => $failedCount,
        'timestamp'    => now()->toIso8601String(),
    ]);
})->middleware('auth:sanctum'); // Internal check — authenticated only
```

---

## UptimeRobot Configuration

Free tier monitors (50 monitors, 5-minute checks):

| Monitor | URL | Alert on |
|---|---|---|
| API health | `https://api.yourapp.com/api/health` | Non-200 response |
| Frontend | `https://yourapp.com` | Non-200 response |
| Demo church | `https://demo.yourapp.com` | Non-200 response |

Alert channels: Email + WhatsApp (via AT API) to Lead Engineer.

**Downtime definition:** 2 consecutive failed checks = alert fired (10-minute grace period). Single failure = no alert (transient network blip).

---

## Scheduled Monitoring Commands

These run on the Laravel scheduler and alert on anomalies:

### Queue Depth Monitor

```php
// app/Console/Commands/MonitorQueueDepth.php
class MonitorQueueDepth extends Command
{
    protected $signature = 'monitor:queues';

    // Thresholds: alert if any queue exceeds this depth
    private array $thresholds = [
        'critical'      => 3,    // OTPs: a backlog here means people cannot sign in
        'payments'      => 10,   // Payments queue should clear fast
        'notifications' => 50,
        'reports'       => 20,
        'exports'       => 10,
        'scheduled'     => 5,
    ];

    public function handle(): void
    {
        foreach ($this->thresholds as $queue => $threshold) {
            $depth = Queue::size($queue);
            if ($depth > $threshold) {
                Log::warning("Queue depth exceeded threshold", [
                    'queue'     => $queue,
                    'depth'     => $depth,
                    'threshold' => $threshold,
                ]);
                $this->sendAlert("Queue {$queue} has {$depth} pending jobs (threshold: {$threshold})");
            }
        }

        // Alert on any failed jobs accumulating
        $failedCount = DB::table('failed_jobs')->count();
        if ($failedCount > 5) {
            $this->sendAlert("Failed jobs count: {$failedCount}. Review with: php artisan queue:failed");
        }
    }
}
```

**Schedule:** Every 15 minutes.

### Integration Health Monitor

```php
// app/Console/Commands/MonitorIntegrations.php
class MonitorIntegrations extends Command
{
    protected $signature = 'monitor:integrations';

    public function handle(): void
    {
        $this->checkPaystack();
        $this->checkAfricasTalking();
        $this->checkR2();
    }

    private function checkPaystack(): void
    {
        try {
            $response = Http::withToken(config('services.paystack.secret'))
                ->timeout(5)
                ->get('https://api.paystack.co/bank');

            if ($response->failed()) {
                $this->sendAlert('Paystack API unreachable: ' . $response->status());
            }
        } catch (\Throwable $e) {
            $this->sendAlert('Paystack API check failed: ' . $e->getMessage());
        }
    }

    private function checkR2(): void
    {
        try {
            Storage::disk('r2')->put('monitor/heartbeat.txt', now()->toIso8601String());
        } catch (\Throwable $e) {
            $this->sendAlert('Cloudflare R2 write failed: ' . $e->getMessage());
        }
    }
}
```

**Schedule:** Every 30 minutes.

### SMS Credit Low-Balance Alert

```php
// In a scheduled command:
$lowBalanceChurches = Church::whereActive()
    ->where('sms_credits', '<', 20) // Less than 20 credits remaining
    ->get();

foreach ($lowBalanceChurches as $church) {
    // Notify church admin via email (not SMS — they're out of SMS credits)
    Mail::to($church->adminUser()->email)
        ->queue(new SmsCreditsLowMail($church));
}
```

**Schedule:** Daily at 08:00 UTC (9am Ghana time).

---

## Alert Thresholds

### Infrastructure Alerts

| Metric | Warning | Critical | Action |
|---|---|---|---|
| CPU usage | > 70% sustained 10 min | > 90% sustained 5 min | Check for runaway process; scale if persistent |
| RAM usage | > 80% | > 90% | Identify memory leak; restart workers |
| Disk usage | > 70% | > 85% | Prune logs, prune webhook_logs, prune old exports |
| Network egress | > 8TB/month | > 9.5TB/month | Ensure R2 is serving media (not the app server) |

### Application Alerts

| Metric | Threshold | Action |
|---|---|---|
| 5xx error rate | > 1% of requests in 5 min | Check recent deploy; check DB connection |
| 4xx error rate (non-auth) | > 10% of requests in 5 min | Check for attack or broken frontend |
| API response time p99 | > 2 seconds | Check slow query log; check queue saturation |
| Failed job count | > 5 accumulated | Review with `php artisan queue:failed` |
| Payments queue depth | > 10 | Workers may be stuck; check worker logs |
| Health check failure | Any check != ok | See on-call runbook |

### Business Alerts

| Event | Trigger | Action |
|---|---|---|
| Zero `charge.success` webhooks on Sunday | 8am–1pm, no webhook in 60 min | Check Paystack status page; check webhook handler |
| Church SMS credits exhausted | Any church reaches 0 | Email admin; add 10 emergency credits as goodwill |
| OTP failure rate high | > 30% of OTP verifications fail in 1 hour | Check AT SMS delivery; check Redis OTP storage |
| New church signup | Any | No alert — log at info level; review weekly |

---

## Error Tracking

### Production Error Pipeline

```
PHP exception
   ↓
Laravel Exception Handler
   ↓
Log::error() with context
   ↓
JSON log file (storage/logs/laravel-YYYY-MM-DD.log)
   ↓
[MVP] Manual log review
[v2] Sentry — auto-grouped, with stack trace + context
```

### Configuring Log Alerts (MVP — Slack via Webhook)

```php
// config/logging.php — add a Slack channel for critical errors
'slack' => [
    'driver'   => 'slack',
    'url'      => env('LOG_SLACK_WEBHOOK_URL'),
    'username' => 'CHMS Logger',
    'emoji'    => ':warning:',
    'level'    => 'error',
],

// Stack both daily file and Slack for errors
'stack' => [
    'driver'   => 'stack',
    'channels' => ['daily', 'slack'],
],
```

Every `Log::error()` sends to a private Slack channel. Warnings stay in the log file only.

---

## On-Call Runbook

### Alert: API health check failing (503)

**First action:** Check `https://api.yourapp.com/api/health` manually. Read the `checks` object in the response.

**Database check failing:**
```bash
# SSH into instance
ssh ubuntu@<instance-ip>
docker ps  # Is the PostgreSQL container running?
docker logs <postgres-container>  # Any crash loops? (name shown by docker ps)
# If PostgreSQL is down:
docker restart <postgres-container>
# Wait 30 seconds, then:
curl https://api.yourapp.com/api/health
```

**Redis check failing:**
```bash
docker ps  # Is Redis container running?
docker restart coolify-redis
php artisan queue:restart  # Workers need to reconnect
```

**Storage (R2) check failing:**
- Check Cloudflare's status page: cloudflarestatus.com
- Check R2 credentials haven't expired (R2 API tokens don't expire by default — check if they were rotated)

### Alert: Queue depth > threshold on `payments`

The payments queue is the most critical. A building payment queue means:
- Workers may have crashed
- A job may be throwing exceptions on every retry

```bash
# Check worker status in Coolify process manager
# Or via SSH:
php artisan queue:monitor redis --queues=payments

# Check for failed jobs
php artisan queue:failed

# If a job is repeatedly failing:
php artisan queue:failed  # Note the job ID
php artisan queue:forget <id>  # Remove it if it's corrupted
# Or: retry after fixing the root cause:
php artisan queue:retry <id>

# Check recent Paystack webhook for patterns:
WebhookLog::where('source', 'paystack')
    ->latest()
    ->take(10)
    ->get(['event', 'created_at', 'processed_at']);
```

### Alert: Zero Paystack webhooks on Sunday

This means digital giving is silently failing. **High priority.**

1. Check Paystack status: `status.paystack.com`
2. Check the webhook endpoint in Paystack dashboard — is it showing failed deliveries?
3. Check the app's webhook handler directly:
   ```bash
   # Look for recent webhook log entries
   tail -n 100 storage/logs/laravel-$(date +%Y-%m-%d).log | grep paystack
   ```
4. If Paystack is up and the endpoint isn't receiving: check Cloudflare isn't blocking the requests (check WAF rules)
5. Emergency fallback: direct finance secretaries to record contributions as cash manually. No money is lost.

### Alert: API response time p99 > 2 seconds

1. Check for a recent deploy that may have introduced a slow query
2. Check Laravel Telescope on staging for query analysis
3. On production, enable the query log temporarily:
   ```php
   DB::enableQueryLog();
   // Reproduce the slow endpoint
   dd(DB::getQueryLog());
   ```
4. Check for queue saturation — if reports/exports queue is full, it slows the whole app
5. Check Oracle instance CPU/RAM — if resource-constrained, restart heavy processes

### Alert: Disk usage > 85%

```bash
# Find what's taking up space
du -sh /var/lib/docker/volumes/*  # Docker volumes (DB data)
du -sh /var/www/html/storage/logs  # Application logs
du -sh /var/www/html/storage/app  # Any local files (should be minimal — use R2)

# Prune old logs (Laravel keeps 14 days by default)
find /var/www/html/storage/logs -name "*.log" -mtime +14 -delete

# Prune webhook_logs older than 90 days
php artisan webhook-logs:prune

# Prune old exports (should be in R2, but check local storage)
Storage::disk('local')->delete(Storage::disk('local')->files('exports'));
```

### Alert: High OTP failure rate

OTP failures mean members can't log in.

1. Check Africa's Talking delivery reports in their dashboard
2. Check Redis is running: `redis-cli ping` → should return `PONG`
3. Check if specific phone numbers are affected (single network issue) or all numbers
4. If AT is down: OTP delivery fails silently. Staff with existing valid tokens can still work. New logins blocked.
5. **Escalation:** Contact Africa's Talking support if AT-side issue.

---

## Log Retention

| Log type | Location | Retention | Enforcement |
|---|---|---|---|
| Application logs | `storage/logs/` | 14 days | Laravel `daily` driver rotation |
| Failed jobs | `failed_jobs` table | Manual review | Pruned after retry/resolution |
| Webhook logs | `webhook_logs` table | 90 days | `php artisan webhook-logs:prune` (weekly cron) |
| Message logs | `message_logs` table | 12 months | `php artisan message-logs:prune` (monthly cron) |
| Auth logs | `personal_access_tokens` | Until revoked | `php artisan sanctum:prune-expired --hours=8` (daily cron) |

---

## Incident Severity Levels

| Severity | Definition | Response time | Who |
|---|---|---|---|
| P0 — Critical | Platform down for all churches; payments failing; data breach | Immediate | Lead Engineer |
| P1 — High | One or more churches can't use core feature (giving, attendance); data loss risk | < 1 hour | Lead Engineer |
| P2 — Medium | Degraded performance; non-critical feature broken; alerts firing but workaround exists | < 4 hours | Lead Engineer |
| P3 — Low | Minor bug; cosmetic issue; non-urgent alert | Next business day | Lead Engineer |

All incidents → post-mortem. Template in `docs/reference/post-mortems/`.

---

## v2 Observability Upgrades

When the first 20 churches are live and paying, add:

- **Sentry** — error tracking with stack traces, grouped by fingerprint, release tracking. Replace manual Slack log monitoring.
- **Uptime Kuma** — self-hosted uptime monitoring (replaces UptimeRobot free tier, more monitors, shorter intervals)
- **Grafana + Prometheus** — infrastructure and business metrics dashboards (queue depth trends, giving volume, active users)
- **PagerDuty or similar** — on-call rotation, escalation policies, alert deduplication

Until then, structured logs + UptimeRobot + Slack webhook is sufficient.
