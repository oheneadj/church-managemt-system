# Vision

---

## The One-Sentence Version

Build the church management system that every mid-size Ghanaian church can actually afford, actually use, and actually trust — because it was built for them, not adapted from somewhere else.

---

## The Problem We're Solving

Walk into a typical Ghanaian church on a Sunday. The usher marks attendance in a paper register. The offering goes into an envelope with a name scrawled on it. The pastor's follow-up list for sick members lives in his personal WhatsApp. The youth director knows every child's name but has no system for tracking their school level, confirmation status, or birthday.

At the end of the month, the finance secretary types contribution totals into Excel. Data is lost. Members slip through the cracks. The church grows but the administration does not.

Existing software doesn't solve this cleanly:
- Western tools (Planning Center, Church Community Builder) are built around bank transfers, US church culture, and USD pricing.
- Ghana-origin tools either lack Mobile Money, lack WhatsApp integration, or hide their prices — all trust-killers.
- The one competitor that gets it right (Shepherd) has barely launched.

The gap is real. The market is ready. We are filling it.

---

## Our Vision

**To become the default operating system for the Ghanaian church** — the tool a church admin opens every morning, the system a pastor trusts for pastoral care follow-up, and the platform a finance secretary uses to produce Sunday's offering report in minutes instead of hours.

Within three years:
- 500+ churches actively using the platform across Ghana
- The name of our product synonymous with church management in the Ghanaian Christian conversation
- Expansion into Nigeria, Kenya, and diaspora churches in the UK and US who still operate with Ghanaian church culture

---

## Who We're Building For

### The Church Administrator (Primary User)
She manages everything — member records, communications, scheduling, and reports — often unpaid or on a small stipend. She's comfortable with WhatsApp and basic smartphones. She does not want to learn complex software. She needs things to work.

**Her biggest pain:** "I spend more time finding information than doing actual work."

### The Pastor
He's the authority in the church but not the day-to-day operator. He wants visibility — who hasn't been to church in three weeks, which members are sick, how giving is trending — without having to ask his admin every time.

**His biggest pain:** "I don't know what's happening in my own church unless someone tells me."

### The Finance Secretary
He handles contributions, tithes, pledges, and reports. Often a volunteer accountant or bookkeeper. He needs records he can trust and reports he can hand to the church board.

**His biggest pain:** "I'm manually adding up offering totals on Sunday evening. There must be a better way."

### The Member (Occasional User)
She attends church, gives via MoMo, and occasionally needs to check in digitally or receive a church broadcast. She will not install a church app. She already has WhatsApp.

**Her expectation:** "I send a message on WhatsApp to check in. That's it."

### The Member Without a Smartphone
He is elderly or uses a basic phone. He cannot use WhatsApp or scan a QR code. An usher or the church secretary checks him in at the door via the staff app. He receives his contribution receipt by SMS on his basic phone. He is fully supported — no smartphone required.

---

## What Success Looks Like

### 6 Months (MVP Live)
- 10 paying churches on the platform
- Church admins can onboard and be fully operational in under 30 minutes
- MoMo contributions recording end-to-end in a single Sunday service
- Zero data loss incidents
- Net Promoter Score ≥ 40 from early users

### 12 Months (Growth)
- 50 churches on Starter or Growth plans
- Monthly SMS credit top-ups generating meaningful secondary revenue
- Platform giving fee revenue covering at least 30% of infrastructure costs
- v2 features (pastoral care, youth tracking) in active use

### 24 Months (Scale)
- 200+ churches
- Denomination partnerships (one contract with a named denomination)
- Feature set that no Ghana-origin competitor can match end-to-end
- Profitable operations — platform fees + subscriptions covering all costs + salary

---

## Design Principles

These are the rules that every feature decision must pass through.

### 1. WhatsApp first, app never
Members will not install a church app. Any workflow that requires an app install has failed. Default to WhatsApp, SMS, or the web browser.

### 2. MoMo is not optional
Ghana runs on Mobile Money. Any giving feature that doesn't support MTN MoMo at minimum is a giving feature that won't be used. Paystack handles this natively.

### 3. GHS only — always
Pricing, reports, and contribution records are always in Ghana Cedis. Never expose a USD figure to a Ghanaian church user.

### 4. Speed over completeness
A working member directory today is worth more than a perfect accounting module in 18 months. Ship the core loop fast. Expand from there.

### 5. Don't touch the money
We record, we report, we facilitate. We do not keep funds, we do not do accounting, we do not give financial advice. Digital gifts pass through the platform's payment-provider account only long enough to be automatically paid out to the church, per transaction, with the platform fee deducted (card payments are split by Paystack at charge time). We never hold a church's balance. See D-018.

### 6. Earn trust, don't demand it
Data is sacred in a church context. Member records, pastoral care notes, and giving history are deeply personal. Our security and data handling must be above reproach, and we must communicate that clearly — not just build it.

---

## What We Are Not Trying to Be

- A church accounting system (that's for accountants and dedicated accounting software)
- A streaming or content platform
- A CRM built on Salesforce or similar
- A free tool (free plans attract non-paying users who demand enterprise support)
- An international platform that happens to support Ghana (we are a Ghana platform, full stop)

---

## The Bet

The bet we're making is this: **the first team to build a complete, affordable, WhatsApp-native, MoMo-integrated church management system in GHS will own the Ghanaian market for years.**

Shepherd is trying. DaChurchMan is incumbent but stale. Everyone else has blind spots.

We execute well, we win.
