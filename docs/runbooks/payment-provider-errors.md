# Runbook: payment provider errors

**Severity:** P1 if payments are failing for every church, P2 if confirmations are late.

> **Applies from Sprint 4.** Giving (Paystack cards, PayPerlez MoMo) is not built yet. This is the plan the first version must be able to follow; update it when the code exists. Until then, `docs/17` ("Alert: Zero Paystack webhooks on Sunday") holds the same steps in short form.

## Symptom
- Members say they paid but the church sees nothing, or a payment is stuck pending.
- No payment webhook arrived for an hour during Sunday service.
- The `payments` queue grows, or webhook signature checks fail in bulk.

## Contain
1. **Do not retry a payment by hand.** Retrying can charge a person twice. Every retry must use the same `Idempotency-Key` as the original.
2. Tell finance secretaries to record gifts as cash until it is fixed. No money is lost: the provider keeps its own record.
3. If our webhook handler is crashing, do not make it answer `2xx` to hide the problem: a provider that gets `2xx` will not send the event again. Fix the handler, then ask the provider to replay.

## Diagnose
- Provider status page (Paystack, PayPerlez).
- The provider dashboard: are deliveries failing? With which status?
- `webhook_logs` (encrypted payloads, kept 90 days): `source`, `event`, `processed`, `error`.
- Cloudflare: is the WAF blocking the provider's IP addresses?

## Resolve
- Provider outage: wait; the **polling fallback** (a scheduled job that checks every payment still pending after a grace period against the provider) settles them when it recovers. This job must exist before payments launch.
- Our bug: fix, deploy, then replay the failed webhooks from the provider dashboard. The handler is idempotent (it stores the provider's event id under a unique constraint), so a replay does not double-count.
- A confirmation that arrives after the hold on a resource expired: follow the rule in the Sprint 4 design (honour it if the resource is still free, otherwise refund and tell the person).

## Verify
- A small test payment goes through end to end in the provider's test mode first.
- Pending payments older than the grace period are zero.
- Reconcile the day's total against the provider's report.
