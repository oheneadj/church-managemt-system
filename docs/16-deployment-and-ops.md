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
   ├── Coolify (PaaS layer — Docker, Nginx, SSL, process supervisor)
   │     ├── Laravel API (Docker container, port 8000)
   │     ├── PostgreSQL (Docker container, internal only)
   │     ├── Redis (Docker container, internal only)
   │     └── Queue Workers (Supervisor — 6 named queues)
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

### Services to Create in Coolify

| Service | Type | Port | Notes |
|---|---|---|---|
| PostgreSQL | Database | 5432 (internal) | Production DB |
| Redis | Database | 6379 (internal) | Cache + queues |
| Laravel API | Application | 8000 | Main app container |
| Queue Worker | Worker | — | Supervisor process, no HTTP |

### Environment Variables

Set in Coolify's environment manager for the Laravel API service. All secrets live here — never in the repo.

```env
APP_NAME=YourAppName
APP_ENV=production
APP_KEY=base64:...           # Generate: php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://api.yourapp.com

DB_CONNECTION=pgsql
DB_HOST=postgres             # Coolify service name = internal hostname
DB_PORT=5432
DB_DATABASE=chms_production
DB_USERNAME=chms_user
DB_PASSWORD=...

REDIS_HOST=redis             # Coolify service name
REDIS_PORT=6379

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=re_...
MAIL_FROM_ADDRESS=noreply@yourapp.com
MAIL_FROM_NAME="YourAppName"

PAYSTACK_SECRET_KEY=sk_live_...
PAYSTACK_PUBLIC_KEY=pk_live_...
PAYSTACK_WEBHOOK_SECRET=...

AT_API_KEY=...
AT_USERNAME=...
AT_SENDER_ID=YourApp

META_VERIFY_TOKEN=...
META_ACCESS_TOKEN=...
META_PHONE_NUMBER_ID=...

CLOUDFLARE_R2_ACCESS_KEY_ID=...
CLOUDFLARE_R2_SECRET_ACCESS_KEY=...
CLOUDFLARE_R2_BUCKET=chms-production
CLOUDFLARE_R2_URL=https://...r2.cloudflarestorage.com

FRONTEND_URL=https://yourapp.com
```

### Wildcard SSL

Coolify handles Let's Encrypt automatically. Configure:

1. In Coolify → SSL/TLS → Add domain: `*.yourapp.com` and `yourapp.com`
2. Use DNS challenge (required for wildcard) — add the DNS TXT record Coolify shows you in Cloudflare
3. Auto-renewal is handled by Coolify; cert renews 30 days before expiry

### Process Supervisor (Queue Workers)

Add a Supervisor config in Coolify's process manager for queue workers:

```ini
[program:queue-critical]
command=php /var/www/html/artisan queue:work redis --queue=critical --sleep=1 --tries=3 --backoff=5
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-critical.log

[program:queue-notifications]
command=php /var/www/html/artisan queue:work redis --queue=notifications --sleep=3 --tries=3 --backoff=60
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-notifications.log

[program:queue-payments]
command=php /var/www/html/artisan queue:work redis --queue=payments --sleep=1 --tries=3 --backoff=30
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-payments.log

[program:queue-reports]
command=php /var/www/html/artisan queue:work redis --queue=reports --sleep=5 --tries=2 --backoff=120
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-reports.log

[program:queue-exports]
command=php /var/www/html/artisan queue:work redis --queue=exports --sleep=5 --tries=2 --backoff=120
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-exports.log

[program:queue-scheduled]
command=php /var/www/html/artisan queue:work redis --queue=scheduled --sleep=10 --tries=1
numprocs=1
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker-scheduled.log
```

### Scheduled Tasks (Laravel Scheduler)

A single cron entry runs the Laravel scheduler every minute:

```bash
# Add via Coolify's cron manager, or directly in the container's crontab:
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler then dispatches individual tasks on their own cadence (see `09-engineering-principles.md` for the scheduled tasks table).

---

## CI/CD Pipeline

### Repositories

```
github.com/yourorg/chms   (Laravel API + React 19 SPA in resources/js — one repo, D-022)
```

Local development is native (no Docker, D-023). Staging and production build the Docker image from `deploy/Dockerfile`; that file is the source of truth for the PHP version and extensions (`pdo_pgsql`, `redis`, `bcmath`).

### GitHub Actions Workflow (API)

```yaml
# .github/workflows/ci.yml
name: CI

on:
  push:
    branches: [main, staging]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:18
        env:
          POSTGRES_PASSWORD: password
          POSTGRES_DB: chms_test
        ports: ['5432:5432']
        options: --health-cmd="pg_isready" --health-interval=10s --health-timeout=5s --health-retries=5
      redis:
        image: redis:7
        ports: ['6379:6379']

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, ctype, fileinfo, pdo, pdo_pgsql, bcmath, redis
          coverage: none

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist --optimize-autoloader

      - name: Build and check the SPA
        run: |
          npm ci
          npm run typecheck
          npm run build

      - name: Copy .env
        run: cp .env.testing .env

      - name: Generate key
        run: php artisan key:generate

      - name: Run migrations
        run: php artisan migrate --force
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: chms_test
          DB_USERNAME: postgres
          DB_PASSWORD: password

      - name: Check code style (Pint)
        run: ./vendor/bin/pint --test

      - name: Static analysis (PHPStan level 6)
        run: ./vendor/bin/phpstan analyse

      - name: Run tests
        run: php artisan test --parallel
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_DATABASE: chms_test
          DB_USERNAME: postgres
          DB_PASSWORD: password
          REDIS_HOST: 127.0.0.1

  deploy-staging:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/staging'
    steps:
      - name: Trigger Coolify deploy
        run: |
          curl -X POST "${{ secrets.COOLIFY_STAGING_WEBHOOK }}" \
            -H "Authorization: Bearer ${{ secrets.COOLIFY_TOKEN }}"

  deploy-production:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - name: Trigger Coolify deploy
        run: |
          curl -X POST "${{ secrets.COOLIFY_PRODUCTION_WEBHOOK }}" \
            -H "Authorization: Bearer ${{ secrets.COOLIFY_TOKEN }}"
```

### Deploy Script (run by Coolify on each deploy)

Coolify runs the app's deploy script after pulling the new image:

```bash
#!/bin/bash
set -e

echo "=== Checking the environment ==="
php artisan env:check

echo "=== Running migrations ==="
php artisan migrate --force

echo "=== Clearing caches ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "=== Restarting queue workers ==="
php artisan queue:restart

echo "=== Deploy complete ==="
```

### Zero-Downtime Deploys

Coolify uses a rolling restart strategy with Docker:
1. Pulls new image
2. Starts new container
3. Runs health check (`GET /api/health` must return 200)
4. Switches Nginx upstream to new container
5. Stops old container

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

### Database Backup Script

Runs as a scheduled Laravel command at 02:00 UTC:

```php
// app/Console/Commands/BackupDatabase.php
class BackupDatabase extends Command
{
    protected $signature = 'db:backup';

    public function handle(): void
    {
        $filename  = 'backup-' . now()->format('Y-m-d-H-i-s') . '.dump.gpg';
        $tmpPath   = '/tmp/' . $filename;
        $s3Path    = 'backups/database/' . $filename;

        // Dump (custom format is already compressed). Password via PGPASSWORD, never on the command line.
        $dumpCmd = sprintf(
            'PGPASSWORD=%s pg_dump -Fc -h %s -U %s %s',
            escapeshellarg(config('database.connections.pgsql.password')),
            escapeshellarg(config('database.connections.pgsql.host')),
            escapeshellarg(config('database.connections.pgsql.username')),
            escapeshellarg(config('database.connections.pgsql.database')),
        );

        // Encrypt with GPG
        $gpgCmd = sprintf(
            '%s | gpg --batch --yes --recipient %s --output %s --encrypt',
            $dumpCmd,
            config('backup.gpg_recipient'),
            $tmpPath,
        );

        exec($gpgCmd, $output, $exitCode);

        if ($exitCode !== 0) {
            Log::error('Database backup failed', ['exit_code' => $exitCode]);
            return;
        }

        // Upload to R2
        Storage::disk('r2-backup')->put($s3Path, file_get_contents($tmpPath));
        unlink($tmpPath);

        Log::info('Database backup complete', ['path' => $s3Path]);

        // Prune backups older than 30 days
        $this->pruneOldBackups();
    }
}
```

**Add to schedule:**
```php
// app/Console/Kernel.php
$schedule->command('db:backup')->dailyAt('02:00');
```

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

Queue workers must be restarted after every deploy to load new code. This is handled automatically by the deploy script (`php artisan queue:restart`). To do it manually:

```bash
# Via Coolify process manager UI: find "queue-*" processes, click Restart
# Or via SSH:
php artisan queue:restart
```

Workers finish their current job before restarting — no jobs are dropped.

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
