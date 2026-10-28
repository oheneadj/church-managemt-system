# Runbook: disk space critical

**Severity:** P2 above 90%, P1 if the database stops because the disk is full.

## Symptom
- The disk alert fires (warning 70%, critical 85%, docs/17), or `GET /api/health` shows the `disk` check failing (free space under `HEALTH_MIN_FREE_DISK_PERCENT`, default 10%).
- PostgreSQL logs `No space left on device`.

## Contain
- Stop things that write: `php artisan sms:pause` does not help here; instead stop the `reports` and `exports` workers in Coolify.
- Do not delete files in the PostgreSQL data folder to make space. That destroys the database.

## Diagnose
```bash
df -h
du -sh storage/logs storage/app storage/framework 2>/dev/null
docker system df                                    # images and volumes (unverified until staging exists)
psql -c "select relname, pg_size_pretty(pg_total_relation_size(oid)) from pg_class where relkind='r' order by pg_total_relation_size(oid) desc limit 8;"
```
- Large `storage/logs`: logs are kept 14 days; a loop writing errors can fill it fast.
- Large `message_logs`, `audit_logs` or `webhook_logs` tables: the daily prune handles message logs (12 months) and webhook payloads (90 days).
- Old Docker images after many deploys.

## Resolve
```bash
php artisan model:prune                              # runs the retention prune now
find storage/logs -name "*.log" -mtime +14 -delete   # old logs
docker image prune -f                                # unused images (unverified until staging exists)
```
If the cause is a runaway error log, fix the error first, or it fills again.

## Verify
- `df -h` is under 70%.
- `curl -s https://<host>/api/health` shows `"disk"` ok.
- Restart the workers you stopped.
