# Incident Response

> What we do when something breaks in production, written while calm. It covers how bad an incident is, who does what, the four phases, the runbooks, the post-mortem and what to do about a data breach. Agreement section 37.

This is one person's team today (the lead engineer, with the product owner for church communication). The roles below name jobs, not people, so they work whoever fills them.

---

## Severity levels

| Level | Meaning | Respond within | Examples |
|---|---|---|---|
| **P1 Critical** | The platform is down, data is lost or exposed, or money is going wrong | Immediately, any hour | Database unreachable, every sign-in failing, all payments failing, a data breach |
| **P2 High** | A main feature is broken for every church | 1 hour | Nobody can sign in, members cannot be added, text messages are not leaving |
| **P3 Medium** | A feature is broken for some churches, or things are slow | 4 hours | One church cannot sign in, pages are slow, texts are delayed |
| **P4 Low** | A small fault with a way around it | Next working day | A label is wrong, a report is off by one, a layout glitch |

When unsure, pick the higher level and lower it later. A suspected breach is always P1.

The old table in `docs/17` used P0 to P3. P0 is now P1, P1 is P2, and so on; the post-mortem template uses P1 to P4.

## Roles

| Role | Does | Today |
|---|---|---|
| **Incident lead** | Decides, keeps the timeline, calls severity changes | Lead engineer |
| **Communicator** | Tells churches what is happening, in plain words, and when it is fixed | Product owner |
| **Scribe** | Writes down what was seen and done, with UTC times | The incident lead, until there is a second person |

No breach is handled by one person alone: the lead engineer and the product owner are both involved (docs/05).

## The four phases, in this order

1. **Detect.** Monitoring finds it before a church does (docs/17: health check, UptimeRobot, Sentry, queue and disk alerts). If a church reports it first, the alerting failed, and fixing that is a post-mortem action.
2. **Contain.** Stop the damage from spreading before looking for the cause: pause sending (`php artisan sms:pause`), roll back the deploy, take an endpoint offline, suspend a church. Containing is allowed to be crude.
3. **Resolve.** Find and fix the cause, check it in staging if there is time, and confirm the system is healthy again (the verification steps in the runbook).
4. **Learn.** A blameless post-mortem within 48 hours for every P1 and P2.

## Runbooks

One per failure we expect, in `docs/runbooks/`. Each has the same parts: symptom, contain, diagnose, resolve, verify. They are in the repository on purpose, so they are not in anyone's head or notes.

| Runbook | Use it when |
|---|---|
| [Database connections exhausted](runbooks/database-connections-exhausted.md) | The health check says the database is failing, or requests fail with "too many connections" |
| [Queue worker stopped](runbooks/queue-worker-stopped.md) | Sign-in codes or messages do not arrive, or a queue keeps growing |
| [SMS provider down](runbooks/sms-provider-down.md) | PayPerlez is failing, the circuit opened, or text messages are going wrong |
| [Payment provider errors](runbooks/payment-provider-errors.md) | Giving fails or confirmations stop (applies from Sprint 4) |
| [Disk space critical](runbooks/disk-space-critical.md) | The disk alert fires, or the health check says free space is low |
| [Deployment rollback](runbooks/deployment-rollback.md) | A deploy made things worse |
| [Data breach first response](runbooks/data-breach-first-response.md) | Anyone may have seen or taken data they should not |
| [Restore from backup](runbooks/restore-from-backup.md) | Data is lost or damaged and a backup must be restored |
| [Church purge failed](runbooks/church-purge-failed.md) | A cancelled church's personal data was not purged on time |

Every alert message should link to its runbook (the table in docs/17 does).

## Safety switches that exist today

| Switch | What it does | Command |
|---|---|---|
| Pause text messages | Holds every text on the queue; nothing is lost | `php artisan sms:pause`, then `sms:resume` |
| SMS circuit breaker | Opens by itself after 5 failures in 2 minutes, tries again after a minute (`config/resilience.php`) | automatic; `sms:resume` clears it |
| Roll back a deploy | Coolify keeps the previous release | [Deployment rollback](runbooks/deployment-rollback.md) |
| Cancel or reinstate a church | Stops or restores a church's grace period | `church:cancel`, `church:reinstate` |

There is no general feature-flag system yet. When a risky feature is built (broadcasts, payments), it should ship with its own switch like the SMS pause, so it can be turned off without a deploy. This is recorded as an open item in D-043.

## Post-mortems

Required after every P1 and P2, in `docs/reference/post-mortems/YYYY-MM-DD-title.md`, from `TEMPLATE.md`, within 48 hours.

- **Blameless.** Name systems and processes, never people.
- It holds a timeline in UTC, the root cause, the impact, what worked, what did not, and actions.
- Every action has a named owner and a date. An action without both is not an action.
- It is shared with everyone who works on the product, and kept, so patterns can be found later.

## Data breach

The runbook is [Data breach first response](runbooks/data-breach-first-response.md). In short: contain, find out what was exposed, tell the affected churches within 72 hours of a confirmed breach, and tell the Data Protection Commission without undue delay (NDPA) and, for any EU or UK church, the regulator within 72 hours (GDPR).

A notice states what happened, which data and how many people, what we are doing, and what the church should do. The product owner sends it; the lead engineer provides the facts. The list of what we hold, to answer "what was exposed", is in `docs/05-data-safety.md` ("What Data We Hold") and `docs/07-data-model.md`; the encrypted fields are listed in docs/05.

## Before launch: what must be true

- [ ] Each runbook tried once on staging (they are written, not yet rehearsed: there is no staging).
- [ ] The restore runbook run for real on a copy of production data, with the time it took written down (target: under 4 hours).
- [ ] UptimeRobot and the Sentry alerts point at a channel someone watches, and each alert links to its runbook.
- [ ] A named person for each role, and a second person who can do the incident lead's job.
- [ ] The GPG private backup key stored somewhere safe and tested (1Password), and not on any server.
