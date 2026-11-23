# First staging (test) deploy on Coolify

**Use it when:** you are standing up the test site for the first time (D-074). It is written for Coolify, with the three applications from one image (docs/16). Follow it top to bottom; each step says how to tell it worked.

Throughout, `test.example.com` stands for the domain you choose. Replace it everywhere. **Use demo and test data only** on this site, never real members' data (docs/16).

## What you need before you start

| Need | Why | Where to get it |
|---|---|---|
| A domain you control, with DNS you can edit | The site and its churches live on it | Your registrar or Cloudflare |
| Coolify running on a server | Builds and runs the three applications | Already done |
| A **Cloudflare R2** bucket and an API token for it | Photos and exports. The worker builds exports and the web app serves them, so they must share storage (`FILESYSTEM_DISK=r2`). Free to start | Cloudflare dashboard, R2 |
| **Email (SMTP)** | Staff sign in with a code sent by email (D-054), so real email is required. Resend works (docs/14, section 4) | Resend, or any SMTP service |
| **PayPerlez** test key, sender id and webhook secret | SMS, MoMo and payouts (docs/14, section 7) | PayPerlez workspace, test environment |
| A real **inbox** for the demo admin and the platform admin | The sign-in code is emailed to it | Yours |

Not needed yet (the site starts with a warning for each): WhatsApp (Meta), an error monitor (Sentry) and a database backup key.

**Storage is not optional.** `GET /api/health` writes a test file to the storage bucket, so a web container with no working bucket never becomes healthy and Coolify rolls it back. Have the R2 bucket and token ready before the first deploy.

## 1. Choose the names and set DNS

- **Platform address:** `test.example.com` (sign-up page and the platform admin side at `/admin`).
- **Demo church:** `demo.test.example.com` (the seeder makes a church with the slug `demo`).

In DNS add an `A` record for `test.example.com` and one for `*.test.example.com`, both pointing at the server's IP. (A wildcard DNS record is easy and harmless; the certificate is the part to do per address, below.)

## 2. Create the databases in Coolify

1. In a Coolify **Project**, create an **Environment** called `staging`.
2. Add **PostgreSQL** (18 is Coolify's default and matches this project). Note its database name, user and password, and its **container name** (the internal host).
3. Add **Redis** and note its container name. Coolify defaults to Redis 7.2; this project runs 8, so change the image tag to a Redis 8 one if you can.

**Worked when:** both show *Running* and healthy.

## 3. Make the app key

On your own machine, in the project folder:

```bash
php artisan key:generate --show
```

Copy the `base64:...` value. All three applications must use the **same** key.

## 4. Create the three applications

Create each one from the same Git repository and branch, with the **Dockerfile** build pack:

| Setting | `chms-web` | `chms-worker` | `chms-scheduler` |
|---|---|---|---|
| Branch (set in **Git Source** after the application is created; the first screen cannot change it) | `staging` | same | same |
| Base Directory | `/` | `/` | `/` |
| Dockerfile Location | `/deploy/Dockerfile` | same | same |
| `CONTAINER_ROLE` | `web` | `worker` | `scheduler` |
| Exposed ports (General, **Internal access, Edit Networking**; Coolify guesses 3000 because the Dockerfile has no EXPOSE) | `8080` | not used | not used |
| Domains | see step 5 | **none** | **none** |

Leave health checks to the image: Coolify uses the image's own `HEALTHCHECK` when it has one, and this image does (Coolify's log says "Using the custom healthcheck from the Dockerfile"). Create `chms-web` first. Make the other two by cloning (**Resource Operations, Clone**), then change the name and `CONTAINER_ROLE`, and **clear the Domains field**: Coolify gives every clone a temporary `sslip.io` address.

### Build variables (important)

In **each** application, add these as environment variables and tick **Build Variable**. The browser app bakes them in when it is built, and **the build stops with an error if the first is missing**:

```env
VITE_TENANT_BASE_DOMAIN=test.example.com
VITE_APP_NAME=CHMS
```

`VITE_TENANT_BASE_DOMAIN` must be exactly the same as `TENANT_BASE_DOMAIN` below. Changing it later needs a **rebuild**, not just a restart. (`VITE_SENTRY_DSN`, `VITE_SENTRY_ENVIRONMENT` and `VITE_SENTRY_RELEASE` are optional build variables for the error monitor.)

## 5. Domains for `chms-web`

In the **Domains** field of `chms-web` enter both addresses, separated by a comma:

```
https://test.example.com,https://demo.test.example.com
```

Coolify gets a certificate for each by itself. This is the certain way to start. A single wildcard (`*.test.example.com`) so that every new church works at once needs a wildcard certificate by DNS challenge and is **not documented by Coolify**: leave it until this deploy works, then try it as the last step (docs/16, Wildcard routing).

## 6. Environment variables (all three applications)

Set these on **all three** applications (the worker and scheduler run jobs that need the provider keys). Values in `< >` are yours.

```env
# --- The app ---
APP_NAME=CHMS
APP_ENV=staging
APP_KEY=<the base64: value from step 3>
APP_DEBUG=false
APP_URL=https://test.example.com
TENANT_BASE_DOMAIN=test.example.com

# --- Database and Redis (use the container names from step 2) ---
DB_CONNECTION=pgsql
DB_HOST=<the Postgres resource's internal host: its ID, the text between @ and : in its internal URL>
DB_PORT=5432
DB_DATABASE=<database name>
DB_USERNAME=<user>
DB_PASSWORD=<password>
REDIS_HOST=<redis container name>
REDIS_PORT=6379
REDIS_USERNAME=default
REDIS_PASSWORD=<the Redis password from its Coolify page>
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

# --- Logs go to stderr as JSON so Coolify's log viewer shows them ---
LOG_CHANNEL=stderr
LOG_FORMAT=json
LOG_LEVEL=warning

# --- Cookies and the proxy (web needs these; harmless on the others) ---
SESSION_SECURE_COOKIE=true
# Do NOT set SESSION_DOMAIN: cookies must stay on one church's address (D-027).
TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16

# --- Files: Cloudflare R2 ---
FILESYSTEM_DISK=r2
CLOUDFLARE_R2_URL=https://<account id>.r2.cloudflarestorage.com
CLOUDFLARE_R2_ACCESS_KEY_ID=<key>
CLOUDFLARE_R2_SECRET_ACCESS_KEY=<secret>
CLOUDFLARE_R2_BUCKET=<bucket>

# --- Email (Resend shown) ---
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=resend
MAIL_PASSWORD=<the Resend API key>
MAIL_FROM_ADDRESS=<an address on a domain verified in Resend, for example mail@nxgmachines.com. Resend treats every subdomain as its own domain, so verifying the root does not cover mail.<root>. Set the SAME value on all three applications: the worker is the one that sends>
MAIL_FROM_NAME=CHMS

# --- SMS, MoMo and payouts: PayPerlez (test environment) ---
SMS_PRIMARY_PROVIDER=payperlez
PAYMENT_MOMO_PROVIDER=payperlez
PAYPERLEZ_API_KEY=<the ppz_test_ key>
PAYPERLEZ_SMS_SENDER_ID=<the approved sender UUID>
PAYPERLEZ_WEBHOOK_SECRET=<the whsec_ secret, copied whole>

# --- Demo sign-ins (staging has no defaults, D-074). Each pair is made only if both are set. ---
DEMO_ADMIN_EMAIL=<your real inbox>
DEMO_ADMIN_PASSWORD=<at least 12 characters>
DEMO_PLATFORM_ADMIN_EMAIL=<your real inbox>
DEMO_PLATFORM_ADMIN_PASSWORD=<at least 12 characters>
```

Optional, and each missing one only produces a warning at start-up: `WHATSAPP_DRIVER`, `META_VERIFY_TOKEN`, `META_APP_SECRET`, `SENTRY_LARAVEL_DSN`, `BACKUP_GPG_RECIPIENT` (until a backup key and the backup bucket exist, the 02:00 backup reports a failure each night; that is expected on a test site).

## 7. Deploy, in this order

1. Deploy **`chms-web`** first. It is the only one that migrates and seeds the database. Watch its log: it must print `Environment looks good.` (plus the warnings you expect), then run the migrations, then start.
2. When `chms-web` is healthy, deploy **`chms-worker`**, then **`chms-scheduler`**.

**Worked when:** all three show *Running (healthy)*. If `chms-web` stops straight away, read the first lines of its log: `env:check` names each missing or unsafe variable (never its value).

## 8. Check it works

| Check | How | Good result |
|---|---|---|
| Health | Open `https://test.example.com/api/health` | JSON saying the checks pass |
| The platform address is recognised | Open `https://test.example.com/` | The "start a church" page loads (this proves `VITE_TENANT_BASE_DOMAIN` was baked in) |
| Demo church sign-in | Open `https://demo.test.example.com/`, sign in with `DEMO_ADMIN_EMAIL` and `DEMO_ADMIN_PASSWORD` | A code arrives by email within a minute; the dashboard opens |
| Platform admin sign-in | Open `https://test.example.com/admin/login` | A code arrives; the packages screen opens |
| Workers | `chms-worker` log | Lines for the queues starting (`critical`, `payments` and the rest) and no restarts |
| Scheduler | `chms-scheduler` log | The scheduled tasks running each minute |
| Unknown church | Open `https://nothing.test.example.com/` | A not-found page, not an error (needs the wildcard, step 5) |

## 9. Tell PayPerlez about the webhook

In the PayPerlez workspace's webhook settings, add `https://test.example.com/api/v1/webhooks/payperlez` and copy its signing secret into `PAYPERLEZ_WEBHOOK_SECRET` (all three applications), then redeploy. Until the real event names are confirmed (open item B2) confirmations are also found by the polling job, so a payment is not lost without the webhook.

## 10. Prove the things the docs flagged as unverified

Tick these in `docs/16-deployment-and-ops.md` ("Verify on the first staging deploy") as you confirm them, and write any surprise in the CHANGELOG:

- The image builds on the server (CI builds amd64 only).
- `*.test.example.com` routes to `chms-web` (only if you tried the wildcard).
- Coolify accepts `chms-worker` and `chms-scheduler` with no domain and no port.
- Coolify's stop timeout is at least 120 seconds, so a stopping worker is not killed mid-job.
- The image's start-up works with the base image's entrypoint.
- The 120-second health `start-period` covers the first migration.

## Mistakes made on the first deploy (so they are not repeated)

- **Password authentication failed:** a copy slip in `DB_PASSWORD`. Use the copy icon on the database page, no quotes or spaces.
- **`nginx.conf` missing:** fixed in `deploy/bin/start` (the base image only builds the nginx config when its command is `/init`).
- **Health check failing with the bucket unset:** see the storage note above.
- **Sign-in code never arrives, Resend says the from address is not allowed:** the from-address domain is not verified in Resend (see `MAIL_FROM_ADDRESS`).
- Changing any variable needs a redeploy or restart of that application. Do it on all three when the variable is shared.

## If something goes wrong

| Symptom | Probably |
|---|---|
| The build fails with "Build argument VITE_TENANT_BASE_DOMAIN is not set" | Step 4: add it as a **Build Variable** and redeploy |
| `chms-web` exits at once and the log lists settings | Step 6: set what `env:check` names. Run `php artisan env:check` in the Coolify terminal to see the list again |
| The site loads but a church address shows the sign-up page, or the platform address shows a church sign-in | `VITE_TENANT_BASE_DOMAIN` differs from `TENANT_BASE_DOMAIN`, or was changed without a rebuild |
| Sign-in code never arrives | Email settings (step 6), or `chms-worker` is not running (codes go on the `critical` queue). See the queue and email runbooks |
| Signed in, then signed out on the next click | `SESSION_SECURE_COOKIE` or `TRUSTED_PROXIES` wrong, or `SESSION_DOMAIN` set. Cookies need HTTPS to be seen |
| A photo or export fails | R2 settings (step 6); the bucket and key must be the same on web and worker |
| "No church" at `demo.test.example.com` | `DEMO_ADMIN_EMAIL` or `DEMO_ADMIN_PASSWORD` was not set before the first deploy: set both and redeploy `chms-web` |

Roll back with the [deployment rollback](deployment-rollback.md) runbook.
