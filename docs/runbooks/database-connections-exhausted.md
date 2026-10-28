# Runbook: database connections exhausted

**Severity:** P1 if every request fails, P2 if only some do.

## Symptom
- `GET /api/health` returns 503 with the `database` check failing.
- Requests or jobs fail with `SQLSTATE[08006]`, `too many clients already` or `remaining connection slots are reserved`.
- Sentry shows a burst of database connection errors.

## Contain
1. Pause the biggest source of load so connections free up: `php artisan sms:pause` (workers stop holding connections while retrying), and stop extra queue workers in Coolify if there are more than planned.
2. If a deploy happened in the last hour, [roll it back](deployment-rollback.md) first. A new query in a loop is the usual cause.

## Diagnose
```bash
# Who is holding the connections?
psql -c "select usename, state, count(*) from pg_stat_activity group by 1,2 order by 3 desc;"
# Anything stuck for minutes?
psql -c "select pid, now()-query_start as running, state, left(query,80) from pg_stat_activity where state <> 'idle' order by running desc limit 10;"
psql -c "show max_connections;"
```
- Many `idle in transaction` rows: a request or job opened a transaction and never finished.
- Many `active` long queries: a slow query. Find it with the request id from Sentry or the JSON log.
- Count of connections is close to `max_connections` with all `idle`: too many workers and web processes for the limit.

## Resolve
- End a stuck connection: `psql -c "select pg_terminate_backend(<pid>);"` (only for the stuck ones).
- Restart the workers so they reconnect cleanly: `php artisan queue:restart`.
- Too many processes: lower the worker counts in Coolify, or raise `max_connections` in PostgreSQL if the machine has the memory. Do not raise it blindly: each connection costs memory.
- A slow query: fix it in code (add the missing index or eager load), and deploy.

## Verify
- `curl -s https://<host>/api/health` shows `"database"` ok.
- `select count(*) from pg_stat_activity;` is well under `max_connections`.
- `php artisan sms:resume` if you paused sending, then check `queue:failed` for jobs that failed meanwhile and retry them with `php artisan queue:retry all`.

Write a post-mortem if this was P1 or P2.
