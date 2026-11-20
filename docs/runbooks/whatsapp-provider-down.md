# Runbook: WhatsApp (Meta) down or misbehaving

**Severity:** P3. Members are still checked in when Meta is slow (the attendance is saved before the answer is sent); only the "you are checked in" answer is late. P2 if the webhook itself is failing, so no CHECKIN is being recorded.

## Symptom
- Members say they sent CHECKIN and got no answer, or the live check-in screen is not counting WhatsApp check-ins.
- The log shows `Circuit opened: calls are being held back` for provider `whatsapp`, or `Meta refused a WhatsApp message`.
- `webhook_logs` has rows with `processed` false and an `error`.

## What the system already does
- A webhook that Meta posts is kept first and queued, then answered `200` at once, so a slow worker never makes Meta resend. Meta resends for 24 hours anyway; a message already handled is ignored (`handled_whatsapp_messages`).
- Answers that fail temporarily retry for 30 minutes. After 5 failures in 2 minutes the circuit opens for a minute, then lets one test answer through (`config/resilience.php`). An answer Meta refuses for good (for example the 24-hour window closed) is logged `failed` and not retried.

## Contain
- If answers are going wrong (wrong text, a flood): `php artisan whatsapp:pause`. Check-ins are still recorded; the answers wait and are sent after `whatsapp:resume` (up to 30 minutes, then given up).
- Tell the church to use the QR code or the manual check-in screen meanwhile.

## Diagnose
```bash
grep -a "Meta refused a WhatsApp message" storage/logs/laravel-$(date +%F).log | tail      # status, error_code, trace_id
grep -a "A WhatsApp webhook could not be processed" storage/logs/laravel-$(date +%F).log | tail
php artisan tinker --execute='echo App\Models\WebhookLog::query()->where("processed", false)->whereNotNull("error")->count();'
```
- `403` from our webhook in Meta's dashboard: `META_APP_SECRET` is wrong or missing (the signature does not match). Check it in Coolify.
- Meta cannot verify the address: `META_VERIFY_TOKEN` differs from the token typed in Meta's dashboard.
- `401` from Meta when answering: the access token expired or was revoked. Replace `META_ACCESS_TOKEN`.
- `131047` or similar: the member's 24-hour window is closed, so an answer needs an approved template. Nothing to fix; the check-in is saved.
- `429` or `5xx`: Meta is limiting or down. The retries back off by themselves.
- Many unprocessed `webhook_logs` rows: a queue worker is stopped, see [Queue worker stopped](queue-worker-stopped.md).

## Resolve
- Meta recovered: `php artisan whatsapp:resume` clears the circuit; held answers go out.
- Credentials fixed: update the environment variables in Coolify, restart workers (`php artisan queue:restart`), then `whatsapp:resume`.
- A payload that failed for good can be replayed by hand: `App\Jobs\ProcessWhatsAppWebhookJob::dispatch($webhookLogId)->onQueue('notifications')` from tinker. It is safe to run again.

## Verify
- Send CHECKIN from a test number to an open service; the answer arrives and the attendance count goes up.
- New `webhook_logs` rows show `processed` true.
- No new `Circuit opened` lines in the log.
