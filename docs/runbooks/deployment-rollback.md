# Runbook: deployment rollback

**Severity:** the severity of the problem the deploy caused.

> **(unverified until staging exists.)** The deploy runs through Coolify (`docs/16`). Nothing here has been rehearsed. Do the rehearsal on staging before launch.

## Symptom
Errors, a failing health check or a broken screen that started right after a deploy. Sentry shows the release the first error appeared in (`SENTRY_RELEASE` is the commit).

## Contain
- Do not try to fix forward under pressure unless the fix is one line and obvious. Roll back first.

## Roll back
1. In Coolify, open the application, go to its deployments, and redeploy the previous successful one. The old container stays up until the new one passes its health check.
2. **Database:** code rollbacks must not need a database rollback. Migrations are written backward compatible for one release (docs/16), so the old code runs on the new schema. Do **not** run `migrate:rollback` in production unless a migration is known to be the cause; a rollback that drops a column deletes data.
3. Restart the workers so they run the old code: `php artisan queue:restart`. A worker keeps running the code it started with.

## Verify
- `GET /api/health` is ok.
- Sign in on a test church, open a member, add a member.
- Sentry shows no new errors for the old release.
- Make a note of the bad commit, open an issue, and write the fix on a branch, not on the deployed one.

Write a post-mortem if this was P1 or P2.
