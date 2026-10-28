# Runbook: queue worker stopped

**Severity:** P2 if sign-in codes stop arriving (nobody can sign in), P3 for other queues.

## Symptom
- People say the sign-in code never comes. The `critical` queue is growing.
- `php artisan queue:monitor redis:critical,redis:payments,redis:notifications --max=50` reports a queue over its limit.
- No worker process is running for a queue (Coolify's process view).

## Contain
- Tell churches signing in will be slow, if it is the `critical` queue.
- Staff who are already signed in are not affected (their sessions stay valid).

## Diagnose
```bash
php artisan queue:failed            # failed jobs, with the error
tail -n 100 storage/logs/laravel-$(date +%F).log
php artisan tinker --execute='foreach (["critical","payments","notifications","reports","exports","scheduled"] as $q) echo $q.": ".Illuminate\Support\Facades\Queue::size($q).PHP_EOL;'
```
- A worker that was started before a deploy keeps the OLD code in memory. A job built by new code can crash it with a TypeError ("Cannot assign null to property"). This happened once on 28 Oct 2026. The fix is a restart, and the rule is to run `php artisan queue:restart` after every deploy.
- Redis down: `redis-cli ping` should answer `PONG`. If not, restart Redis, then restart the workers.
- One job failing again and again: look at its error in `queue:failed`.

## Resolve
```bash
php artisan queue:restart                          # workers exit and the process manager starts new ones
php artisan queue:work --queue=critical            # only if the manager is not restarting them
php artisan queue:retry all                        # after the cause is fixed
php artisan queue:forget <id>                      # a job that can never succeed
```
Sign-in codes expire after 10 minutes, so retried old codes are useless: ask people to request a new one.

## Verify
- Request a sign-in code on a test church: it arrives within a minute.
- Queue sizes are falling; `queue:failed` stops growing.
- The workers are running the current release (restart them after any deploy).
