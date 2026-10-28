# Runbook: SMS provider down

**Severity:** P2 if sign-in codes are not leaving (nobody can sign in), P3 if only receipts and broadcasts are late.

## Symptom
- Sentry or the log shows `Circuit opened: calls are being held back` for provider `sms`.
- Sign-in or sign-up codes do not arrive. `message_logs` rows turn `failed`.
- PayPerlez status or support says there is a problem, or the errors are 5xx or 429.

## What the system already does
- Temporary failures (429, 5xx, no connection) retry. After 5 failures in 2 minutes the circuit **opens** and holds every message for a minute, then lets one test message through (`config/resilience.php`).
- Held messages wait on the queue for up to 30 minutes, then are given up. A message the provider refuses for good (unapproved sender, no credit) fails at once and is logged with the error code and request id.

## Contain
- If messages are going wrong (wrong text, wrong people, a flood): `php artisan sms:pause`. Nothing is lost; messages wait.
- Tell churches that codes may be late. Staff already signed in can keep working.

## Diagnose
```bash
grep -a "PayPerlez refused an SMS" storage/logs/laravel-$(date +%F).log | tail     # status, error_code, request_id
php artisan tinker --execute='echo App\Models\MessageLog::query()->withoutGlobalScopes()->latest()->limit(10)->pluck("status")->implode(",");'
```
- `error_code` and `request_id` are what PayPerlez support needs (info@payperlez.com).
- `401` or `403`: the API key is wrong, revoked or lacks `sms:send`. Check `PAYPERLEZ_API_KEY`.
- `409`: the sender id is not approved for this environment. Check `PAYPERLEZ_SMS_SENDER_ID` in the PayPerlez workspace.
- `422` with no credit: SMS credits ran out. Buy credits in the PayPerlez workspace (integration keys cannot).
- `429`: we are over their rate limit; the retries back off by themselves.
- `5xx` or timeouts: PayPerlez is having a problem. Wait, or switch provider (below).

## Resolve
- Provider recovered: `php artisan sms:resume` clears the circuit; held messages go out.
- Switch to the fallback provider: set `SMS_PRIMARY_PROVIDER` to the other driver and restart workers (`php artisan queue:restart`). Africa's Talking is the planned fallback and has no driver yet, so today the only option is to wait. **(unverified until a second driver exists)**
- Credentials fixed: update the environment variables in Coolify, restart workers, then `sms:resume`.

## Verify
- Request a sign-in code on a test church; it arrives.
- `message_logs` shows new rows with status `sent` and a `provider_reference`.
- No new `Circuit opened` lines in the log.
- Re-send anything important that was given up after 30 minutes (receipts), by hand.
