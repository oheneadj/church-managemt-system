# Risks & Open Questions

> Known risks are manageable risks. This document tracks everything that could go wrong, everything we don't know yet, and what we're doing about each one. Review this at every sprint retrospective. Close items when resolved.

---

## Risk Register

Each risk is rated on two axes:
- **Likelihood:** Low / Medium / High
- **Impact:** Low / Medium / High / Critical

**Priority = Likelihood × Impact**

---

### R1 — Paystack MoMo USSD prompt rejection rate
**Likelihood:** Medium | **Impact:** High | **Priority:** HIGH

**Description:** When a member receives a MoMo USSD prompt to approve a payment, a significant portion may ignore or decline it — either because they don't understand what it is, don't have sufficient balance, or are suspicious of an unsolicited prompt.

**Impact:** Low conversion on digital giving, finance secretaries resorting to manual cash recording, undermining the value proposition.

**Mitigation:**
- Train church admins to announce "you'll receive a MoMo prompt" before initiating payments during service
- Add a clear, friendly confirmation SMS *before* the USSD prompt: "Grace Assembly: Tap OK on the upcoming prompt to give GHS 200 as tithe. Thank you!"
- Record a cash fallback for every failed MoMo transaction
- Track MoMo success rate per church — flag churches with < 60% success rate for admin follow-up

**Owner:** Product Owner | **Review:** Sprint 4

---

### R2 — Meta WhatsApp Business API approval delays
**Likelihood:** Medium | **Impact:** High | **Priority:** HIGH

**Description:** Getting a verified WhatsApp Business account and approved message templates from Meta can take days to weeks. Rejection is common for first-time applicants without a clear business category.

**Impact:** Sprint 3 (WhatsApp CHECKIN) and Sprint 5 (WhatsApp broadcasts) are blocked until the account is approved.

**Mitigation:**
- Apply for Meta Business verification *before Sprint 1 begins* — this is a pre-build task
- Prepare and submit all required message templates (CHECKIN confirmation, receipt, broadcast) in one batch
- Build SMS as the full fallback — every WhatsApp feature has an SMS alternative
- If Meta approval is delayed past Sprint 3, ship with SMS-only CHECKIN using a keyword to a long code number

**Status:** ⚠️ Action required before Sprint 1 starts

**Owner:** Product Owner | **Review:** Sprint 1

---

### R3 — Africa's Talking sender ID approval (Ghana)
**Likelihood:** Medium | **Impact:** Medium | **Priority:** MEDIUM

**Description:** In Ghana, branded SMS sender IDs (e.g. "YourApp") must be registered with the National Communications Authority (NCA) via the telcos. This takes 2–6 weeks and can be rejected.

**Impact:** Until a sender ID is approved, messages send from a numeric short code — less trustworthy to recipients, higher chance of being ignored.

**Mitigation:**
- Start the sender ID application process immediately — this is a pre-build task
- Use Africa's Talking's shared alphanumeric ID while waiting (longer but functional)
- Design SMS messages to identify the church by name in the message body so sender ID matters less

**Status:** ⚠️ Action required before Sprint 1 starts

**Owner:** Product Owner | **Review:** Sprint 2

---

### R4 — Oracle Always Free instance resource limits
**Likelihood:** Low | **Impact:** High | **Priority:** MEDIUM

**Description:** Oracle's Always Free tier provides 4 OCPUs and 24GB RAM but has been subject to Oracle reclaiming "idle" instances in the past. It also has network egress limits (10TB/month).

**Impact:** If the instance is reclaimed or hits limits, the entire platform goes down.

**Mitigation:**
- Keep the instance active with a daily health-check cron (Coolify monitoring handles this)
- Monitor egress usage — at scale, R2 handles media delivery (zero egress cost), which is the biggest bandwidth consumer
- Maintain a Terraform/Coolify snapshot so the instance can be recreated in under 30 minutes on any provider
- Have a paid fallback provider identified (Hetzner CAX21 ARM, €6/month) — test migration before the first 50 churches

**Owner:** Lead Engineer | **Review:** Sprint 1, then monthly

---

### R5 — Church admin turnover breaking operations
**Likelihood:** High | **Impact:** Medium | **Priority:** HIGH

**Description:** In Ghanaian churches, admin roles often change with elections or pastoral transitions. If the person who set up the account leaves, access could be lost.

**Impact:** A church locked out of their own account with no IT support staff to recover it.

**Mitigation:**
- Require at least **two** staff accounts during onboarding (primary admin + backup)
- Platform-level account recovery: phone-based OTP recovery is always available to any registered phone
- Platform support contact (WhatsApp number) shown on the login page
- Build a "transfer admin" flow in v2 that doesn't require contacting us

**Owner:** Product Owner | **Review:** Sprint 2 (onboarding flow)

---

### R6 — Competitor (Shepherd) accelerates before we launch
**Likelihood:** Medium | **Impact:** Medium | **Priority:** MEDIUM

**Description:** Shepherd has a head start and is actively selling. They could sign 50+ churches before we launch, making acquisition harder.

**Impact:** We lose the early-mover window for churches actively looking for a solution right now.

**Mitigation:**
- Get 3 "design partner" churches signed up *before* building — they shape the product and commit to onboarding on launch day
- Launch Sprint 5 result publicly with a waiting list, even if it's not fully polished
- Target churches Shepherd hasn't reached yet: smaller denominations, regional churches outside Accra
- Track Shepherd publicly — follow their social, read their blog, note when they add features

**Owner:** Product Owner | **Review:** Sprint 3

---

### R7 — Ghana internet reliability during Sunday service
**Likelihood:** Medium | **Impact:** Medium | **Priority:** MEDIUM

**Description:** Churches in smaller cities and towns may have unreliable internet. If the platform goes down or is unreachable during Sunday service, the church has no fallback.

**Impact:** Church admin can't record attendance or giving in real-time. Trust in the platform damaged.

**Mitigation:**
- Implement an offline-capable contribution recording form using service workers + IndexedDB — syncs when connection returns
- Train admins: cash can always be recorded later; nothing is lost
- Show connection status indicator in the UI
- Target churches in Accra, Kumasi, Tema for MVP — better connectivity than rural areas
- Document the "offline Sunday" procedure in the onboarding guide

**Owner:** Lead Engineer (offline UI), Product Owner (onboarding docs) | **Review:** Sprint 5

---

### R8 — Platform fee causing church churn
**Likelihood:** Low | **Impact:** High | **Priority:** MEDIUM

**Description:** If churches feel the platform fee on digital giving is too high or not transparently communicated, they may avoid using digital giving or leave the platform entirely.

**Impact:** Reduced platform fee revenue; perception of the platform as "taking from the church."

**Mitigation:**
- Disclose the fee clearly during onboarding — not in fine print
- Frame it as: "This fee covers the cost of offering your members a seamless digital giving experience. Your church receives the net amount directly to your Paystack account."
- Set the fee at a level below Paystack's standard transaction fee so the total is still competitive
- Give the church a real-time view of fees charged vs. contributions received in the giving dashboard

**Owner:** Product Owner | **Review:** Sprint 4

---

### R9 — Data breach or unauthorised access
**Likelihood:** Low | **Impact:** Critical | **Priority:** HIGH

**Description:** A breach of member PII, giving records, or pastoral care data would cause severe reputational damage and potentially violate Ghana's Data Protection Act 2012.

**Impact:** Immediate loss of trust, likely loss of all church accounts, potential legal exposure.

**Mitigation:**
- All controls in [`15-security.md`](./15-security.md) and [`05-data-safety.md`](./05-data-safety.md) are implemented before launch
- Penetration test before first church goes live (at minimum a structured self-review using OWASP Top 10)
- Breach response plan documented and rehearsed
- Cyber liability insurance considered (v2, once revenue justifies it)

**Owner:** Lead Engineer | **Review:** Sprint 5 (pre-launch)

---

### R10 — Paystack API changes breaking payment flows
**Likelihood:** Low | **Impact:** High | **Priority:** MEDIUM

**Description:** Paystack occasionally updates their API. A breaking change to the charge, subaccount, or webhook format could silently break giving.

**Impact:** Contributions fail without the church or platform knowing. Real money is lost in the flow.

**Mitigation:**
- Pin to a specific Paystack API version where the API supports it
- All webhook payloads stored raw in `webhook_logs` before processing — can replay if processing logic changes
- Set up automated monitoring: if `charge.success` webhooks stop arriving for more than 15 minutes during Sunday hours (8am–1pm), alert immediately
- Subscribe to Paystack's status page and developer changelog

**Owner:** Lead Engineer | **Review:** Quarterly

---

## Open Questions

Items we need an answer to before the work they affect can begin. Sorted by sprint dependency.

---

### Q1 — What is the product name?
**Needed by:** Sprint 1 (used in: app name, sender ID, domain, branding, `.env APP_NAME`)
**Status:** Open
**Options explored:** Asafo (congregation/assembly), Ayeyi (praise), Hyira (blessing)
**Constraint:** Must be Ghanaian, modern, church-related, and available as a `.com` or `.com.gh` domain
**Owner:** Product Owner
**Decision target:** Before Sprint 1 begins

---

### Q2 — What is the primary domain?
**Needed by:** Sprint 1 (DNS, SSL, Coolify config, subdomains)
**Status:** Blocked on Q1 (name decision)
**Owner:** Product Owner
**Decision target:** Same day as Q1

---

### Q3 — What exact percentage is the platform giving fee?
**Needed by:** Sprint 4 (Paystack subaccount split configuration)
**Status:** Open
**Context:** Paystack's standard fee is ~1.5% + GHS 0.50 per local transaction. Structure decided in D-018: for MoMo/USSD the platform fee is a percentage of each gift, capped at a maximum GHS amount, calculated by us and deducted at disbursement; the church bears provider fees. **Decided (Oct 2026): 1% of the gift, capped at GHS 10.00, floor GHS 0.10, minimum digital gift GHS 1.00.** Card payments (Paystack subaccount split) still need a split percentage.
**Owner:** Product Owner
**Decision target:** Sprint 3

---

### Q4 — What are the exact SMS credit allocations per plan tier?
**Needed by:** Sprint 4 (sms_credit_ledger seeding on plan assignment)
**Status:** Open
**Context:** We know Starter / Growth / Pro get different allocations. Need the actual numbers so they can be hardcoded in the plan configuration.
**Owner:** Product Owner
**Decision target:** Sprint 3

---

### Q5 — Do we allow church admins to set a custom sender ID for SMS?
**Needed by:** Sprint 5 (broadcast feature)
**Status:** Open
**Context:** Custom sender IDs (e.g. "GraceChurch") require NCA registration per church, which takes time and money. Simpler to use one platform-wide sender ID for MVP.
**Recommendation:** Platform sender ID only in MVP. Custom sender IDs as a Growth/Pro feature in v2.
**Owner:** Product Owner
**Decision target:** Sprint 2

---

### Q6 — What is the WhatsApp number the CHECKIN command goes to?
**Needed by:** Sprint 3 (WhatsApp webhook handler, CHECKIN instruction to members)
**Status:** Blocked on Meta Business API approval (R2)
**Context:** Each church sends CHECKIN to the platform's WhatsApp number. The webhook maps the church by the member's phone number. One platform WhatsApp number handles all churches.
**Owner:** Lead Engineer (once Meta account approved)
**Decision target:** Sprint 2

---

### Q7 — Do we support multi-service Sundays? (e.g. 7am and 9am services)
**Needed by:** Sprint 3 (service creation UX)
**Status:** Open
**Context:** Many churches run two morning services. Members may attend either. Do we create two separate service records and let them check in to either? Or one "Sunday" service with a morning/evening split?
**Recommendation:** Two separate services. Simpler, more accurate reporting.
**Owner:** Product Owner
**Decision target:** Sprint 2

---

### Q8 — What is the monthly subscription price in GHS for each tier?
**Needed by:** Sprint 5 (Paystack payment links for subscription billing)
**Status:** Open
**Context:** We decided prices are stored only in Paystack links (not in the DB). Need the actual GHS amounts to create those links.
**Owner:** Product Owner
**Decision target:** Sprint 4

---

### Q9 — Do we send contribution receipts for cash by default, or is it opt-in per church?
**Needed by:** Sprint 4 (ContributionReceived listener logic)
**Status:** Open
**Context:** Sending SMS receipts for every cash contribution consumes SMS credits. A church with 200 cash offerings per Sunday will burn through credits fast. Should this be on by default or configurable?
**Recommendation:** Configurable per church, default ON. Shown clearly in onboarding.
**Owner:** Product Owner
**Decision target:** Sprint 3

---

## Closed Items

Items resolved — kept here for reference.

| # | Question / Risk | Resolution | Date |
|---|---|---|---|
| — | SaaS vs standalone | SaaS only. Standalone is v3 Enterprise, contract-only. | Oct 2026 |
| — | Single DB vs DB-per-tenant | Single DB with global scopes. | Oct 2026 |
| — | Full SPA vs Inertia vs Blade | React 19 SPA inside the Laravel repo (`resources/js`), REST only, no Inertia (D-022). | Oct 2026 |
| — | SMS billing model | Free monthly credits per tier + purchasable top-up bundles. | Oct 2026 |
| — | Platform fee on giving | Yes. Via Paystack subaccounts. Disclosed to churches. | Oct 2026 |
| — | Include church accounting? | No. Giving only in v1. Expenditure log in v2. | Oct 2026 |
| — | Domain strategy (path vs subdomain) | Subdomains. `{slug}.yourapp.com`. | Oct 2026 |
| — | Infrastructure provider | Oracle ARM Always Free + Coolify. | Oct 2026 |
| — | Email provider | Resend for MVP, own SMTP later. Laravel Mail abstraction. | Oct 2026 |
