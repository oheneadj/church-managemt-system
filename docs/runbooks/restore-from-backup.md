# Runbook: restore from backup

**Targets:** restore within **4 hours** (RTO); lose at most **24 hours** of data (RPO, one daily backup).
**Severity:** P1.

> Backups come from `php artisan db:backup`: a daily `pg_dump -Fc`, encrypted with GPG for the backup **public** key, stored in the separate backup bucket and kept 30 days. The server can write backups but cannot read them. The **private** key is kept offline (1Password), never on a server.
>
> What has been tested: a real backup of the development database was made, its file was unreadable without the key, and the decrypted archive held the same number of rows as the live database. A full restore into a new database was **not** tested, because the development database account cannot create databases. Do the full restore on staging before launch.

## Before you start
- Decide **what** to restore: the whole database, or only some data? A whole-database restore throws away every change since the backup. Think about whether a smaller fix (for example restoring one table into a scratch database and copying rows back) is enough.
- Stop writes while you restore: put the app in maintenance mode (`php artisan down`) and pause the workers.
- Never restore over the live database before you have kept a copy of it as it is now: `pg_dump -Fc` it to a safe place first. The damaged data may still be needed.

## Steps
```bash
# 1. Pick the backup (list the bucket; names carry the UTC time)
aws s3 ls s3://<backup-bucket>/backups/database/ --endpoint-url https://<account>.r2.cloudflarestorage.com

# 2. Download it
aws s3 cp s3://<backup-bucket>/backups/database/backup-YYYY-MM-DD-HH-MM-SS.dump.gpg . --endpoint-url https://<account>.r2.cloudflarestorage.com

# 3. Decrypt, on a machine that has the private key (import it from 1Password first, remove it afterwards)
gpg --output restore.dump --decrypt backup-YYYY-MM-DD-HH-MM-SS.dump.gpg

# 4. Look inside before restoring
pg_restore --list restore.dump | head

# 5. Restore into a NEW database first and check it
createdb chms_restored
pg_restore --no-owner -d chms_restored restore.dump
psql -d chms_restored -c "select count(*) from churches" -c "select count(*) from members"

# 6. Switch the app to it: point DB_DATABASE at chms_restored (or rename the databases), restart
php artisan up
php artisan queue:restart
```
Delete `restore.dump` and the downloaded `.gpg` file when you are done (`shred -u restore.dump`).

## Verify
- Counts of churches, members and users look right for the date of the backup.
- Sign in on a test church, open a member, check the newest record you expect exists.
- `GET /api/health` is ok; send a test sign-in code.
- Tell churches how much was lost (everything since the backup time) so they can re-enter it.
- Record the time the restore took here and in the post-mortem. If it was over 4 hours, that is an action.

## Rehearsal (before launch, then every month, docs/05)
Run steps 1 to 5 on staging with a real backup, record the time, and fix this runbook where it was wrong.
