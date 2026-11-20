# Runbook: church purge failed

**Severity:** P2. A church's personal data staying past its date is a privacy problem (docs/05, D-042).

## Symptom
- The log has `Church purge failed` at critical level, with a `church_id`.
- A church cancelled more than 90 days ago still has `purged_at` empty.

## Contain
Nothing needs stopping. The purge is safe to run again and does nothing twice.

## Diagnose
```bash
php artisan tinker --execute='echo App\Models\Church::query()->whereNotNull("cancelled_at")->whereNull("purged_at")->where("cancelled_at","<",now()->subDays(90))->pluck("slug","id")->toJson();'
php artisan queue:failed                    # look for App\Jobs\PurgeChurchJob and its error
```
- A database error rolls back the whole database part, so nothing is half purged. The error names the table.
- A storage error (R2 down, bad key) only affects file deletion, which comes after the database part. The church is then marked purged but some files remain; the purge job also runs when `purged_at` is set, so running it again deletes the files.

## Resolve
```bash
php artisan queue:retry <id>                 # the failed purge job, after the cause is fixed
php artisan churches:purge-cancelled         # or queue every due church again
```
To take a cancellation back **before** it is purged: `php artisan church:reinstate <slug>`. After a purge it cannot be undone.

## Verify
- `purged_at` is set, the church is soft-deleted and its slug now ends in `-deleted-<id>`.
- The church's folder in the storage bucket is gone: `churches/<id>/`.
- No `Church purge failed` line appears again.
