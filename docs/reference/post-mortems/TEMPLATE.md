# Post-Mortem: [Incident Title]

> **One rule:** This document is blameless. We name systems and processes, not people. The goal is to understand what happened and prevent it from happening again.

---

## Summary

| Field | Value |
|---|---|
| **Date** | YYYY-MM-DD |
| **Duration** | e.g. 47 minutes |
| **Severity** | P0 / P1 / P2 / P3 |
| **Affected churches** | e.g. All / 3 churches / Grace Assembly only |
| **Impact** | e.g. Giving page unavailable, members could not check in |
| **Detection** | e.g. UptimeRobot alert / Customer report / Engineer noticed |
| **Author** | Name |
| **Reviewers** | Names |

---

## Timeline

Use UTC timestamps. Be precise — "around 2pm" is not useful.

| Time (UTC) | Event |
|---|---|
| 14:03 | First alert fired (UptimeRobot: API health check failing) |
| 14:07 | On-call engineer acknowledged alert |
| 14:12 | Root cause identified: Redis connection pool exhausted |
| 14:18 | Mitigation applied: restarted queue workers |
| 14:20 | API health check passing |
| 14:28 | Confirmed: all services restored |
| 14:50 | Full post-mortem started |

---

## Root Cause

A single, clear sentence: **What caused the incident?**

> Example: "A deploy introduced an unbounded Redis connection loop in the SMS job, which exhausted the connection pool and caused all queued jobs to stall."

Then 1–3 sentences of technical detail if needed.

---

## What Went Wrong

List the chain of events / contributing factors. Keep it factual.

1. The new `SendBroadcastJob` did not close its Redis connection after each send
2. During a 500-member broadcast, 500 connections were opened and held
3. Redis connection pool limit (100) was hit at connection #101
4. All other queue workers (payments, notifications) could not connect to Redis
5. Queue stalled; no payments or OTPs processed for 47 minutes

---

## What Went Right

Acknowledge what worked. This is important — it shows the resilience already in the system.

- UptimeRobot detected the API health check failure within 3 minutes
- The health check correctly identified Redis as the failing component
- On-call engineer was reachable and responded quickly
- Paystack webhooks returned `200` during the incident (payload stored, jobs re-queued after recovery)
- No data was lost

---

## Impact Assessment

Be specific. Use numbers where possible.

- **Churches affected:** All churches (shared Redis instance)
- **Duration:** 47 minutes (14:03 – 14:50)
- **Failed OTP requests:** ~12 (users unable to log in during the window)
- **Failed payment webhooks:** 0 (Paystack retried successfully after recovery)
- **Receipts delayed:** ~8 SMS receipts delayed by ~45 minutes (sent after queue recovered)
- **Financial impact:** None — no contributions lost, Paystack stored all webhooks

---

## Resolution

What was done to resolve the incident:

1. Restarted all queue workers (immediate mitigation — freed Redis connections)
2. Deployed fix: connection closed in `finally` block after each Redis operation
3. Verified queue depths returned to normal
4. Monitored for 20 minutes post-fix to confirm stability

---

## Action Items

These must be assigned and tracked to completion.

| # | Action | Owner | Due | Status |
|---|---|---|---|---|
| 1 | Fix Redis connection leak in `SendBroadcastJob` | Dev | Sprint N | Done |
| 2 | Add Redis connection count to `MonitorQueueDepth` command | Dev | Sprint N | Done |
| 3 | Add alert threshold: alert when Redis connections > 80% | Ops | Sprint N+1 | Open |
| 4 | Add integration test for `SendBroadcastJob` with 500 recipients | Dev | Sprint N+1 | Open |
| 5 | Review all Jobs for similar connection leak patterns | Dev | Sprint N+1 | Open |

---

## Lessons Learned

What would you tell the team if this were a training exercise?

1. **Test with realistic data volumes.** The broadcast job was tested with 5 recipients, not 500. Bugs at scale don't show up at small scale.
2. **Monitor the resources your queues depend on.** We monitored queue depth but not Redis connections. Now we do both.
3. **Paystack's webhook retry mechanism saved us.** No payments were lost because Paystack stores and retries. Build systems that assume downstream failures will happen.

---

## Follow-Up

- [ ] Action items tracked in GitHub issues (link: )
- [ ] Monitoring changes deployed
- [ ] This document shared with the team
- [ ] Post-mortem reviewed in next team sync

---

*Post-mortem completed: YYYY-MM-DD*
