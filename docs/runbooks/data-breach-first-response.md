# Runbook: data breach first response

**Severity:** P1 from the moment a breach is suspected. Do not wait to be sure.

## Symptom
Anyone may have seen or taken data they should not: a leaked key or `.env`, a church reporting another church's data on screen, unexplained database access, a stolen laptop with access, a backup bucket made public, odd entries in the audit log.

## Contain (first hour)
1. **Write the time down** (UTC) and start a private timeline. Say what you saw, not what you assume.
2. Cut off access. Rotate whatever may be exposed: `APP_KEY` is NOT rotated casually (it decrypts stored data, see `docs/16`); rotate database password, R2 keys, `PAYPERLEZ_API_KEY`, Paystack keys and Sentry DSN first.
3. End all sessions: `php artisan tinker --execute='App\Models\PersonalAccessToken::query()->delete();'` and clear the session store (users sign in again).
4. Suspend an affected church if the exposure is confined to it: set `is_active` to false in the database; its address then answers 404.
5. Do not delete logs or data. They are evidence.
6. Tell the product owner. A breach is never handled by one person.

## Find out what happened
- `audit_logs`: who viewed, exported or changed which records and when (`viewed` and `exported` events record each time a member's record or data export was opened).
- `message_logs`, `webhook_logs`, the JSON application log (filter by the `request_id`), Sentry, the Cloudflare and Coolify access logs.
- Which tables and which churches were reachable? Use `docs/05-data-safety.md` ("What Data We Hold") and the encrypted fields list: encrypted fields (`address`, `national_id_number`, webhook payloads) are protected unless `APP_KEY` leaked too.

## Decide and notify
Record: what data, which churches, how many people, when it started and ended, whether it is contained.

| Who | When | By |
|---|---|---|
| Affected churches | Within 72 hours of a confirmed breach | Product owner |
| Data Protection Commission (Ghana, NDPA) | Without undue delay | Product owner |
| EU or UK regulator, for churches there (GDPR) | Within 72 hours of discovery | Product owner |
| Affected people, if they are at high risk | Without undue delay | The church, with our help |

The notice states: what happened, which data and how many people, what we have done, and what the church should do now. Plain words, no blame, no guessing.

## Resolve
- Close the hole (patch, remove the exposed file, fix the permission) and confirm it with a test.
- Restore anything damaged from a backup ([restore](restore-from-backup.md)).
- Rotate everything that was exposed, with no downtime where possible.

## Verify and learn
- Access that should be refused is refused.
- Post-mortem within 48 hours (a breach is always P1). The actions have owners and dates.
- Update `docs/05` and this runbook with anything that was missing.
