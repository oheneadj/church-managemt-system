# Runbook: Email provider down

**Severity:** P2 once staff sign-in needs an emailed code (D-054): nobody can sign in. P3 while only invitations and export links are late. (Until D-054 ships no email is sent in production, so this applies from then.)

## Symptom
- Sentry shows an email job that failed after its retries, or the log shows `Circuit opened: calls are being held back` for provider `email`.
- Sign-in, password reset or invitation codes do not arrive. `message_logs` rows with channel `email` turn `failed`.
- Resend's status page or dashboard shows an incident, or the errors are connection timeouts, `535` (login refused) or `550` (address or domain refused).

## What the system already does
- A failed send is retried 3 times (30, 120 and 300 seconds). After 5 failures in 2 minutes the circuit **opens** and holds every email for a minute, then lets one test email through (`config/resilience.php`).
- Held emails wait on the queue for up to 15 minutes, then are given up and marked `failed`. A code is useless after its 10 minutes, so the person asks for a new one.
- Nothing is sent twice: the job checks the log line first.
- Nothing is written to the application log with an address or a code in it.

## Contain
- If emails are going wrong (wrong text, wrong people, a flood): `php artisan email:pause`. Nothing is lost; emails wait.
- Tell churches that codes may be late. Staff already signed in can keep working.

## Diagnose
```bash
php artisan tinker --execute='echo App\Models\MessageLog::query()->withoutGlobalScopes()->where("channel","email")->latest()->limit(10)->pluck("status")->implode(",");'
php artisan env:check          # the mail settings are checked here
php artisan queue:failed | head
```
- `535` or an authentication error: `MAIL_USERNAME` or `MAIL_PASSWORD` is wrong, or the Resend API key was revoked. Check them in Coolify.
- `550` or "domain not verified": the sending domain is not verified in Resend, or `MAIL_FROM_ADDRESS` is not on it.
- Timeouts or connection refused: Resend, or the network to it, is down. Check Resend's status page.
- Mail arrives but in spam: SPF, DKIM or DMARC is missing or wrong for the sending domain (docs/14, section 4).

## Resolve
- Provider recovered: `php artisan email:resume` clears the circuit; held emails go out.
- Credentials fixed: update the environment variables in Coolify, restart workers (`php artisan queue:restart`), then `email:resume`.
- Switch to another SMTP host: change `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, restart workers. No code change. **(unverified until staging exists)**
- A person locked out while this lasts: if they have SMS, sign-in by phone is the old route until D-054 ships; after that the church admin or the platform team resets their password once email is back.

## Verify
- Request a sign-in code for a test staff member; it arrives within a minute.
- `message_logs` shows new email rows with status `sent` and a `provider_reference`.
- No new `Circuit opened` lines for `email` in the log.
- Anything important that was given up after 15 minutes (an invitation) is sent again by hand.
