# Deployment & Operations

> How the platform is built, deployed, and kept running. Every runbook step is written so a team member who didn't set up the system can execute it at 2am.

---

## Infrastructure Overview

```
Internet
   │
   ▼
Cloudflare (DNS + DDoS + proxy)
   │
   ▼
Oracle ARM Instance (4 OCPUs, 24GB RAM)
   │
   ├── Coolify (PaaS layer — Docker, Traefik proxy, SSL)
   │     ├── web        (one image, CONTAINER_ROLE=web, nginx + php-fpm on 8080)
   │     ├── worker     (same image, CONTAINER_ROLE=worker, Supervisor runs the 6 named queues)
   │     ├── scheduler  (same image, CONTAINER_ROLE=scheduler, php artisan schedule:work)
   │     ├── PostgreSQL (Coolify database, private)
   │     └── Redis      (Coolify database, private)
   │
   └── Cloudflare R2 (object storage — external, CDN-backed)
```

**One server. Everything runs on it.** That's intentional at this scale — it's cheaper, simpler to reason about, and the Oracle free tier instance is substantially more powerful than most $20/month VPS options.

---

## Oracle ARM Instance Setup

### Initial Provision (one-time)

1. Create Oracle Always Free account at cloud.oracle.com
2. Provision instance: `VM.Standard.A1.Flex` — 4 OCPUs, 24GB RAM, Ubuntu 22.04 LTS
3. Generate SSH key pair, download private key. Store in 1Password.
4. Open ports in Oracle's security list: 22 (SSH), 80 (HTTP), 443 (HTTPS)
5. Also open ports in the instance's `iptables` (Oracle has two firewall layers):
   ```bash
   sudo iptables -I INPUT -p tcp --dport 80 -j ACCEPT
   sudo iptables -I INPUT -p tcp --dport 443 -j ACCEPT
   sudo apt-get install iptables-persistent
   sudo netfilter-persistent save
   ```
6. Set hostname: `sudo hostnamectl set-hostname chms-prod`
7. Update packages: `sudo apt update && sudo apt upgrade -y`

### Install Coolify

```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```

Coolify installs Docker, its own containers, and a web UI at port 8000. Immediately:
- Change the default port to something non-standard (e.g. 3721) in Coolify settings
- Enable Coolify's built-in HTTP basic auth on the panel
- Add your IP to an allowlist in the Oracle security list for the Coolify port

### Secure the Instance

```bash
# Disable password SSH auth
sudo nano /etc/ssh/sshd_config
# Set: PasswordAuthentication no
# Set: PermitRootLogin no
sudo systemctl restart sshd

# Install fail2ban
sudo apt install fail2ban -y
sudo systemctl enable fail2ban

# Set timezone
sudo timedatectl set-timezone UTC
```

---

## Coolify Configuration

### One image, three applications

`deploy/Dockerfile` builds one image. The `CONTAINER_ROLE` environment variable picks what a container does (`deploy/bin/start`). Create **three Coolify Applications** from the same repository, all with the **Dockerfile** build pack, Base Directory `/` and Dockerfile Location `/deploy/Dockerfile`:

| Application | `CONTAINER_ROLE` | Domain | Ports Exposes | What runs |
|---|---|---|---|---|
| `chms-web` | `web` | yes (see Wildcard routing) | `8080` | `env:check`, `migrate --force`, `db:seed --force`, `optimize`, then nginx + php-fpm |
| `chms-worker` | `worker` | none | none | `env:check`, then Supervisor running the six queue workers |
| `chms-scheduler` | `scheduler` | none | none | `env:check`, then `php artisan schedule:work` |

Why three Applications and not Docker Compose: Coolify does not support rolling updates for Compose applications, and rolling updates need a health check, default container naming and no host port mapping. The `web` app meets all three.

Only `web` migrates and seeds. Both are idempotent, and the migrate step retries for about 30 seconds so a database that is still starting does not fail the deploy.

The image's own `HEALTHCHECK` (`deploy/bin/healthcheck`) is what Coolify uses (it ignores the dashboard check when the image defines one): `web` calls `/api/health`, `worker` requires every Supervisor program to be RUNNING, `scheduler` requires the `schedule:work` process.

Databases are Coolify resources, private to the server:

- **PostgreSQL**: Coolify defaults to 18, which matches local development and CI.
- **Redis**: Coolify defaults to `redis:7.2`. Change the image to a Redis 8 tag so production matches development and CI. Coolify does not back Redis up; only cache, queues and OTP codes live there, all rebuildable.
- Use the internal URL's host, which is the database container name shown in Coolify, for `DB_HOST` and `REDIS_HOST`.

### Environment Variables

Set in each Application's environment manager. All secrets live here, never in the repo. `.env.example` lists every key; `php artisan env:check` (run on every container start) refuses to boot when a required one is missing or unsafe.

Shared by all three applications:

```env
CONTAINER_ROLE=web                 # web | worker | scheduler (different per application)
APP_ENV=production
APP_KEY=base64:...                 # Generate: php artisan key:generate --show (same value in all three)
APP_DEBUG=false
APP_URL=https://yourapp.com        # must be https

DB_CONNECTION=pgsql
DB_HOST=<postgres container name>
DB_PORT=5432
DB_DATABASE=chms_production
DB_USERNAME=chms_user
DB_PASSWORD=...

REDIS_HOST=<redis container name>
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# Logs go to stderr as JSON so Coolify's log viewer shows them
LOG_CHANNEL=stderr
LOG_FORMAT=json
LOG_LEVEL=warning

# Providers: a real SMS provider is required in production (env:check rejects `log`)
SMS_PRIMARY_PROVIDER=...

FILESYSTEM_DISK=r2
CLOUDFLARE_R2_ACCESS_KEY_ID=...
CLOUDFLARE_R2_SECRET_ACCESS_KEY=...
CLOUDFLARE_R2_BUCKET=chms-production
CLOUDFLARE_R2_URL=https://<account id>.r2.cloudflarestorage.com
CLOUDFLARE_R2_BACKUP_ACCESS_KEY_ID=...        # separate bucket and key pair
CLOUDFLARE_R2_BACKUP_SECRET_ACCESS_KEY=...
CLOUDFLARE_R2_BACKUP_BUCKET=chms-backups
```

Only on `web` (it serves browsers and receives webhooks):

```env
TENANCY_BASE_DOMAIN=yourapp.com
SESSION_DRIVER=redis
SESSION_DOMAIN=                    # leave empty: cookies stay host-only per church (D-027)
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16   # the Docker network Traefik sits on; never *
PAYSTACK_SECRET_KEY=sk_live_...
PAYSTACK_PUBLIC_KEY=pk_live_...
PAYSTACK_WEBHOOK_SECRET=...
META_VERIFY_TOKEN=...
META_ACCESS_TOKEN=...
META_PHONE_NUMBER_ID=...
```

The worker also needs the provider keys it calls (SMS, WhatsApp, Paystack, mail), because the jobs run there. `worker` and `scheduler` have no domain and need no `TRUSTED_PROXIES`.

### Wildcard routing and SSL

Churches live on `<slug>.yourapp.com`, so the `web` app must answer every subdomain, with a wildcard certificate.

1. In Cloudflare, add `A` records for `yourapp.com` and `*.yourapp.com` pointing at the server.
2. A wildcard certificate needs the Let's Encrypt **DNS challenge**. Configure Traefik (Coolify's proxy) with the Cloudflare DNS provider and a scoped API token, following Coolify's wildcard certificate documentation.
3. Coolify documents editing container labels with a `HostRegexp` rule. Use that for the `web` app so one router matches `yourapp.com` and every subdomain. A wildcard in the Domains field is **not documented**; verify it on staging before relying on it.
4. Unknown subdomains are answered by the app with a 404 (`ResolveTenant`).

### Scheduled tasks

There is no cron entry. The `scheduler` application runs `php artisan schedule:work`, which triggers the scheduler every minute. The tasks themselves are listed in `09-engineering-principles.md`.

### Queue workers

`deploy/supervisor/supervisord.conf` runs the six queues in the `worker` app:

| Queue | Processes | `--sleep` | `--tries` | `--backoff` |
|---|---|---|---|---|
| `critical` | 1 | 1 | 3 | 5 |
| `payments` | 2 | 1 | 3 | 30 |
| `notifications` | 2 | 3 | 3 | 60 |
| `reports` | 1 | 5 | 2 | 120 |
| `exports` | 1 | 5 | 2 | 120 |
| `scheduled` | 1 | 10 | 1 | none |

Every worker uses `--max-time=3600` so it recycles hourly, and `stopwaitsecs=120` so a stop signal lets a running job finish. Worker output goes to stdout, which Coolify shows as the container log. The file is the source of truth; change the table when you change the file.

### Verify on the first staging deploy

These were read from documentation but not yet proven, because no Docker build has run yet:

- The image builds on the ARM server (CI builds amd64 only).
- `*.yourapp.com` routes to `chms-web` using the label approach above.
- Coolify accepts `chms-worker` and `chms-scheduler` with no domain and no port.
- Coolify's stop timeout is at least the Supervisor `stopwaitsecs` (120s), so a stopping worker is not killed mid-job.
- The base image's entrypoint runs cleanly with our `CMD`.
- The 120s health `start-period` covers the first migration.
- Staging sign-in needs a real SMS adapter, which is not written yet.

---

## CI/CD Pipeline

### Repositories

```
github.com/yourorg/chms   (Laravel API + React 19 SPA in resources/js — one repo, D-022)
```

Local development is native (no Docker, D-023). Staging and production build the Docker image from `deploy/Dockerfile`; that file is the source of truth for the PHP version and extensions (`pdo_pgsql`, `redis`, `bcmath`, `intl`, `gd`, `exif`).

### GitHub Actions Workflow

The pipeline is `.github/workflows/ci.yml` (the file is the source of truth; this section only explains it).

| Job | Runs on | What it does |
|---|---|---|
| `backend` | every push and PR to `main` / `staging` | PHP 8.4, PostgreSQL 18 and Redis 8 services (same majors as local, D-023). Migrations go up, down and up again; Pint; PHPStan level 8; `composer audit`; Pest. |
| `frontend` | same | Node 24: `npm ci`, TypeScript, ESLint + Prettier, Vitest, production build, `npm audit --audit-level=high`. |
| `image` | same | hadolint on `deploy/Dockerfile`, builds the image (amd64), then checks it has `pdo_pgsql`, `redis`, `bcmath`, `intl`, `gd`, `exif`, Supervisor and the built assets, and no Telescope. |
| `deploy-staging` | push to `staging`, after all three jobs pass | `POST` to the Coolify webhook, then a smoke test of `/api/health` (retries for about 5 minutes). |
| `deploy-production` | push to `main`, after all three jobs pass | Same, behind the `production` environment, whose **required reviewers** are the manual approval step. |

One-time GitHub setup:

- Secrets: `COOLIFY_TOKEN`, `COOLIFY_STAGING_WEBHOOK`, `COOLIFY_PRODUCTION_WEBHOOK`. Each webhook secret is the full deploy URL copied from the application's Webhooks page in Coolify; the call is `POST` with `Authorization: Bearer <token>` (Coolify API, `POST /deploy?uuid=...`).
- Variables: `STAGING_URL`, `PRODUCTION_URL` (for example `https://staging.yourapp.com`).
- Environments: create `staging` and `production`; add yourself as a required reviewer on `production`.
- Branch protection on `main` and `staging`: require the `backend`, `frontend` and `image` checks.
- `.github/dependabot.yml` opens weekly update PRs for Composer, npm, the actions and the Docker base images.

Differences from the first draft of this document: PHP 8.4 (the draft said 8.3), PHPStan level 8 (the draft said 6), Redis 8, current action versions, a migration round-trip step, `composer audit` and `npm audit`, and no `.env.testing` (the workflow copies `.env.example`; `phpunit.xml` sets the test database).

### What a deploy does

CI triggers the Coolify webhook for the `chms-web` application; trigger the other two the same way (or enable auto-deploy on them). Each new container runs `deploy/bin/start`, which is the whole deploy script: `env:check`, then (web only) migrate, seed and `optimize`, then the role's process. Caches are built at start-up, so there is no separate cache step and no `queue:restart`: replacing the worker container replaces its workers.

### Zero-Downtime Deploys

With rolling updates enabled on `chms-web` (needs the health check, default container naming and no host port mapping), Coolify starts the new container, waits until its health check passes (`/api/health` returns 200), switches Traefik to it, then stops the old one. Migrations run before the new container goes healthy, so they must stay backward-compatible with the previous release (already a project rule).

**The `/api/health` endpoint** (`HealthController`, `HealthCheck` service): public, unversioned, no tenant. It runs `database`, `cache`, `queue`, `storage` and `disk` checks and returns `200 {status:"ok", checks:{...}}` or `503 {status:"degraded", ...}`. Each check reports only `ok` or `error`; the reason goes to the log. Laravel's built-in `GET /up` is the liveness probe (is the process running?), `/api/health` is readiness (can it serve?). The `disk` check fails when less than `HEALTH_MIN_FREE_DISK_PERCENT` (default 10) of the disk holding `storage/` is free.

---

## Backup Strategy

### What Is Backed Up

| Data | Method | Frequency | Retention | Storage |
|---|---|---|---|---|
| PostgreSQL database | `pg_dump -Fc` + GPG encrypt | Daily 02:00 UTC | 30 days | R2 backup bucket |
| Cloudflare R2 files | R2 cross-bucket replication | Real-time | 30 days (versioning) | R2 backup bucket |
| `.env` file | Manual — in 1Password | On change | Indefinite | 1Password |
| Coolify config | Coolify export | Weekly | 4 copies | R2 backup bucket |

### Database Backup (built)

`php artisan db:backup` (`app/Console/Commands/BackupDatabase.php`) runs every day at 02:00 UTC from the scheduler and never twice at once:

1. `pg_dump -Fc` writes a compressed dump to a private work folder. The database password goes in `PGPASSWORD` in the process environment, never on the command line.
2. `gpg --encrypt` encrypts it for `BACKUP_GPG_RECIPIENT`, the **public** key. The server can write backups but cannot read them; the private key stays offline (1Password).
3. The encrypted file is streamed to the `r2-backup` disk (the separate bucket with its own credentials) under `backups/database/backup-YYYY-MM-DD-HH-MM-SS.dump.gpg`.
4. Backups older than 30 days (`config/backup.php`) are removed.
5. The plain dump and the encrypted copy are deleted from the server afterwards, even when a step fails. A failure is reported to Sentry and the command exits with an error. With no `BACKUP_GPG_RECIPIENT` it refuses to make an unencrypted backup, and `env:check` fails outside development.

Tested with faked `pg_dump`, `gpg` and bucket, and once for real against the development database (encrypted, unreadable with another key, same row counts after decrypting). The full restore into a new database still has to be rehearsed on staging: [restore runbook](runbooks/restore-from-backup.md).

### Restore Procedure

**RTO: 4 hours | RPO: 24 hours**

```bash
# 1. Download backup from R2
aws s3 cp s3://chms-backups/backups/database/backup-YYYY-MM-DD.dump.gpg . \
  --endpoint-url=https://<account>.r2.cloudflarestorage.com

# 2. Decrypt
gpg --output backup.dump --decrypt backup-YYYY-MM-DD.dump.gpg

# 3. (no decompress step — pg_dump -Fc output is already compressed)

# 4. Restore (puts platform in maintenance mode first)
php artisan down --message="Restoring from backup — back in 30 minutes"
pg_restore --clean --if-exists --no-owner -h 127.0.0.1 -U chms_user -d chms_production backup.dump
php artisan migrate  # Run any migrations missed since backup
php artisan up

# 5. Verify
php artisan tinker
>>> Church::count()   # Should match last known count
>>> User::count()
```

---

## Runbooks

### Runbook: Deploy to Production

1. Merge feature branch → `staging`. Wait for CI to pass.
2. Test the feature on `staging.yourapp.com` with realistic data.
3. Get sign-off from Product Owner (for full-stack features).
4. Merge `staging` → `main`. CI runs automatically.
5. Monitor Coolify deployment logs for errors.
6. Verify health check: `curl https://api.yourapp.com/api/health`
7. Check queue workers restarted: look for queue restart log in worker logs.
8. Spot-check the deployed feature in production.

**If deploy fails:**
- Coolify rolls back automatically (old container stays live until new one is healthy)
- Check Coolify build logs for the error
- Fix, push, let CI re-run

### Runbook: Add a New Church

Normal churches sign up via the self-service onboarding flow. This runbook is for manually adding a church (e.g. during testing or for a design partner):

```bash
php artisan tinker
>>> Church::create([
...   'name'  => 'Grace Assembly International',
...   'slug'  => 'grace-assembly',      // becomes grace-assembly.yourapp.com
...   'plan'  => 'starter',
...   'status' => 'active',
... ]);
```

Then create the admin user and invite them via email.

### Runbook: Reset OTP Lockout

If a staff member is locked out due to too many OTP attempts:

```bash
php artisan otp:reset-lockout grace-assembly +233244123456
```

The command clears the lockout counter and any pending code for that church and number (the Redis keys are namespaced per church and hashed, so deleting a raw key by hand no longer works).

Confirm the phone number with the church admin before doing this.

### Runbook: Restart Queue Workers

Every deploy replaces the `chms-worker` container, so workers always run the new code. To restart them without a deploy, restart the `chms-worker` application in Coolify. Supervisor sends a stop signal and waits up to 120 seconds for running jobs to finish, so no jobs are dropped. Queued jobs stay in Redis and are picked up by the new container.

### Runbook: Emergency Maintenance Mode

```bash
php artisan down --message="We're doing emergency maintenance. Back shortly." --retry=60
# ... do the work ...
php artisan up
```

Churches see a friendly maintenance page. In-flight requests return a 503 with `Retry-After: 60`.

### Runbook: Rotate Secrets

When a team member leaves or secrets are believed compromised:

```bash
# 1. Generate new APP_KEY
php artisan key:generate --show
# Store in 1Password before changing anything

# WARNING: Rotating APP_KEY invalidates:
# - All active Sanctum tokens (users must re-login)
# - All field-level encrypted data (must re-encrypt before rotating)
# Only rotate APP_KEY if believed compromised. Rotate other keys freely.

# 2. Update in Coolify env vars (not SSH into server)
# 3. Trigger a new deployment to reload env
# 4. Verify: php artisan tinker → config('app.key')
```

For API key rotation (Paystack, AT, Meta, R2):
1. Generate new key/secret in the third-party dashboard
2. Update in Coolify environment vars
3. Deploy (env reload triggers automatically on Coolify env change)
4. Revoke old key in third-party dashboard

### Runbook: Database Size Audit

```sql
SELECT
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = 'chms_production'
ORDER BY size_mb DESC;
```

Expected largest tables: `contributions`, `webhook_logs`, `message_logs`, `attendance_records`.

`webhook_logs` has a 90-day retention policy — run `php artisan webhook-logs:prune` if it grows large.

### Runbook: Migrate to New Server

If Oracle reclaims the instance or we move to a paid provider:

1. **New instance:** Provision on Hetzner (CAX21 ARM, €6/month) — Ubuntu 22.04
2. **Install Coolify** (same as initial setup above)
3. **Restore secrets:** Pull `.env` from 1Password, enter all values in new Coolify
4. **Restore database:** Download latest backup from R2, follow restore procedure above
5. **Reconfigure Coolify:** Services, Nginx, domains, queue workers, cron
6. **Update Cloudflare DNS:** Point `A` record to new instance IP
7. **SSL:** Re-run Let's Encrypt cert via Coolify
8. **Verify:** Health check, test login, test payment, test SMS

Target: < 4 hours from decision to live. Practice this before the first 50 churches are live.

---

## Staging Environment

Staging runs on the same Oracle instance as production, in a separate Coolify application:

- URL: `staging-api.yourapp.com` / `staging.yourapp.com`
- Database: `chms_staging` (separate DB, same PostgreSQL instance)
- Secrets: Different Paystack test keys, different AT credentials, different R2 bucket
- Deployed automatically on push to `staging` branch

**Staging data:** Seeded with realistic-but-fake data using `DatabaseSeeder`. Never copy production data to staging — it contains real member PII.

```bash
# Reset staging to clean seeded state
php artisan migrate:fresh --seed --env=staging
```

---

## Scaling Checklist

When the platform reaches 50+ active churches, review:

- [ ] PostgreSQL query performance — query `pg_stat_activity` and `pg_stat_statements` on a Sunday to identify slow queries
- [ ] Redis memory usage — `redis-cli info memory` — add more if > 80% of 4GB allocation
- [ ] Queue depth — monitor `queue:monitor` output; add workers if depth builds up
- [ ] Oracle egress — check monthly egress; R2 handles media (zero egress), but API responses add up
- [ ] Consider moving to Hetzner paid (CAX31, €12/month) for more headroom before 100 churches

The architecture doesn't change — just the server size.
