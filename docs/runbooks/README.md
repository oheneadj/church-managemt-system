# Runbooks

Step-by-step guides for known failures. Each one has the same parts: **symptom, contain, diagnose, resolve, verify.** Written when calm, to be followed when not. The rules around them are in [`docs/18-incident-response.md`](../18-incident-response.md).

Commands are run from the application folder on the server (in Coolify: the application's terminal). Several steps depend on the Coolify and Oracle setup that does not exist yet; those steps are marked **(unverified until staging exists)**.

| Runbook | Use it when |
|---|---|
| [Database connections exhausted](database-connections-exhausted.md) | The database check fails or requests fail with "too many connections" |
| [Queue worker stopped](queue-worker-stopped.md) | Codes or messages do not arrive, or a queue grows |
| [SMS provider down](sms-provider-down.md) | PayPerlez fails, the circuit opened, or texts are going wrong |
| [Payment provider errors](payment-provider-errors.md) | Giving fails or confirmations stop (from Sprint 4) |
| [Disk space critical](disk-space-critical.md) | The disk alert fires |
| [Deployment rollback](deployment-rollback.md) | A deploy made things worse |
| [Data breach first response](data-breach-first-response.md) | Anyone may have seen or taken data they should not |
| [Restore from backup](restore-from-backup.md) | Data is lost or damaged |
| [Church purge failed](church-purge-failed.md) | A cancelled church's data was not purged on time |
