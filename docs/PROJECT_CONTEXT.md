# Project Context

> **One-stop brief for anyone joining this project.** Read this before anything else.

---

## What We're Building

A multi-tenant, SaaS church management system built specifically for mid-size Ghanaian churches (100–800 members). It handles member records, attendance, giving, and communications — all through a single, affordable, GHS-priced web platform.

This is not a generic ChMS repackaged for Africa. Every design decision — Mobile Money payments, WhatsApp check-in, Ghana youth lifecycle tracking, OTP-only member login — is native to how Ghanaian churches actually operate.

---

## The Problem

Ghana has ~71,000 registered churches. The vast majority still run on paper registers, WhatsApp groups, and Excel sheets. The few churches that have tried existing software face one or more of these problems:

- **USD pricing** — unaffordable and psychologically foreign to a GHS-based congregation
- **No Mobile Money** — MTN MoMo processed over GHS 1.4 trillion annually; a giving module that ignores this is irrelevant
- **App install required** — most members will not install a church app; WhatsApp they already have
- **Built for the West** — fields, workflows, and assumptions that don't match Ghanaian church structure (e.g. no educational lifecycle tracking, no pledge/tithe culture)
- **Opaque pricing** — most Ghana-origin tools don't publish prices, which kills trust

---

## Target Market

**Primary:** Mid-size Ghanaian churches — 100 to 800 members, one location, 2–5 administrative staff.

**Secondary (v3+):** Denominations with multiple branches (e.g. Presbyterian, Methodist, Assemblies of God regional structures).

**Not targeting now:** Mega-churches (1,000+ members, complex IT needs), micro-churches (<100 members, can't pay), international/diaspora churches.

---

## The Business Model

- **SaaS subscription** — monthly recurring revenue in GHS
- **Three tiers:** Starter → Growth → Pro (plan names stored in DB; prices live only in Paystack)
- **SMS credits:** Free monthly allocation per tier + purchasable top-up bundles (like airtime)
- **Platform fee on giving:** Small % on every MoMo/card contribution processed through the platform (disclosed to churches upfront via Paystack subaccounts)
- **No standalone/self-hosted product** in v1 or v2 — every "standalone" ask is handled as a Dedicated Hosted Instance upsell

---

## Competitive Landscape (Summary)

| Platform | Mobile Money | WhatsApp | GHS Pricing | Key Weakness |
|---|:---:|:---:|:---:|---|
| Asoriba | Partial | ✗ | Partial | No WhatsApp, limited docs |
| DaChurchMan | Partial | ✗ | ✗ | Opaque pricing, no MoMo clarity |
| ChurchCast | Partial | ✗ | Partial | Newer, fewer reviews |
| **Shepherd** | ✓ | ✓ | ✓ | Only 7 churches — barely launched |
| Akwaaba | ✗ | ✗ | Partial | Hardware-dependent, no giving |

**Closest competitor: Shepherd** — WhatsApp-first, MoMo-native, GHS pricing. Weakness: tiny scale. We must execute fast before they grow.

**Our edge:** Shepherd + biometric attendance vision + better onboarding UX + stronger pastoral care and youth lifecycle features.

---

## Tech Stack at a Glance

| Layer | Choice |
|---|---|
| Backend API | Laravel (latest stable) |
| Frontend | React 19 SPA — in the Laravel repo (`resources/js`) |
| Frontend build | Vite 6.x + TypeScript 5.x |
| Routing | React Router v7 |
| Global state | Zustand v5 |
| Server state | TanStack Query v5 |
| Styling | Tailwind CSS v4 + shadcn/ui |
| Auth — staff | Laravel Sanctum + Spatie Permission |
| Auth — members | OTP via SMS/WhatsApp (no passwords) |
| Payments | Paystack / PayPerlez / Moolre (provider abstraction) |
| SMS | PayPerlez / Africa's Talking / Moolre (provider abstraction) |
| WhatsApp | Meta Business API |
| Email | Resend (→ own SMTP in v2+) |
| Queues | Redis + Laravel Queues |
| File storage | Cloudflare R2 |
| Infrastructure | Oracle Always Free ARM + Coolify |
| CI/CD | GitHub → Coolify auto-deploy |

---

## Key People & Roles

| Role | Responsibility |
|---|---|
| Product Owner | Feature decisions, roadmap prioritisation, pricing |
| Lead Engineer | Architecture, code standards, code review |
| Backend Dev | Laravel API, queue jobs, integrations |
| Frontend Dev | React 19 SPA, component library |
| QA | Test strategy, regression, acceptance |

---

## What This Project Is Not

- **Not a church accounting system** — no double-entry ledgers, no statutory financial reporting. Churches use external accountants.
- **Not a native mobile app** — PWA covers the use case; no iOS/Android build planned for v1 or v2.
- **Not a video/streaming platform** — out of scope.
- **Not priced in USD** — GHS only, always.

---

## Status

> **October 2026** — Architecture and planning complete. Full documentation set in progress. Engineering scaffold next.

---

## Key Docs to Read Next

1. [`01-vision.md`](./01-vision.md) — product vision and success metrics
2. [`02-scope-and-backlog.md`](./02-scope-and-backlog.md) — what we're building and when
3. [`06-architecture.md`](./06-architecture.md) — system design
4. [`07-data-model.md`](./07-data-model.md) — database schema
5. [`../decisions/product-and-architecture-decisions.md`](../decisions/product-and-architecture-decisions.md) — every major decision and its rationale
