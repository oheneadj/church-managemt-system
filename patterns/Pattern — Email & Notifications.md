# Pattern — Email & Notifications

> Read this file before building any email sending feature, notification system, or communication channel.

## 1. When to Read This

Read this file before:

- Adding any email sending capability to a project
- Building a notification system (in-app, email, SMS, push)
- Integrating an email provider (Resend, SendGrid, Postmark, Mailgun, SES)
- Setting up transactional email templates
- Building marketing or bulk email functionality
- Setting up SMS notifications (Twilio, Africa's Talking, Mnotify)

**The core problem with email:** Email is not a simple HTTP call that succeeds or fails. Email has deliverability — your emails can be silently dropped, sent to spam, or blocked by receiving mail servers based on your sending reputation. Most developers do not think about this until their password reset emails stop arriving.

## 2. Email Deliverability

Deliverability is whether your emails reach the inbox or get dropped/spammed. It is determined by your sending reputation, DNS configuration, and content quality. Set this up before sending a single email.

**The three DNS records you must configure:**

**SPF (Sender Policy Framework)**

- A DNS TXT record that lists which servers are allowed to send email on behalf of your domain
- Receiving mail servers check SPF to verify you are authorized to send from your domain
- Without SPF, your emails are more likely to be marked as spam
- Example: `v=spf1 include:sendgrid.net ~all`

**DKIM (DomainKeys Identified Mail)**

- Adds a cryptographic signature to every outgoing email
- The receiving server verifies the signature using a public key in your DNS
- Proves the email was not tampered with in transit and genuinely came from you
- Your email provider generates the DKIM key pair and gives you the DNS record to add

**DMARC (Domain-based Message Authentication, Reporting & Conformance)**

- A policy that tells receiving servers what to do when SPF or DKIM fails: `none` (monitor), `quarantine` (spam folder), or `reject` (block)
- Also configures reporting — you receive reports when emails fail authentication
- Start with `p=none` to monitor, then move to `p=quarantine` once you are confident
- Example: `v=DMARC1; p=quarantine; rua=mailto:dmarc@yourdomain.com`

**Sending reputation rules:**

- Use a dedicated sending domain or subdomain for transactional email — `mail.yourdomain.com` not `yourdomain.com` — so your main domain reputation is not affected by email issues
- Warm up new sending domains/IPs gradually — start with low volume and increase over weeks
- Never send to invalid or unverified addresses — high bounce rates destroy reputation
- Never send to unsubscribed users — spam complaints destroy reputation
- Keep your spam complaint rate below 0.1% (Google's threshold for inbox delivery)
- Use a reputable email provider — Resend, Postmark, or SendGrid for transactional; never send from a raw VPS SMTP server

## 3. Transactional vs Marketing Email

These are fundamentally different categories — they have different rules, different infrastructure requirements, and different legal obligations. Mixing them destroys your deliverability.

**Transactional email:**

- Triggered by a user action — password reset, order confirmation, receipt, OTP, account verification
- Expected by the user — they will look for it
- Must always be delivered — never throttled or batched
- Not subject to unsubscribe requirements (users cannot opt out of their receipt)
- Sent from your primary transactional sending domain
- Providers: Resend, Postmark (excellent reputation), SendGrid (transactional tier)

**Marketing / bulk email:**

- Not triggered by a user action — newsletters, promotions, re-engagement campaigns
- User must have explicitly opted in
- Subject to unsubscribe requirements — one-click unsubscribe mandatory (see Section 6)
- Subject to GDPR, NDPA, CAN-SPAM, and other regulations
- Sent from a separate sending domain or subdomain — never the same as transactional
- Providers: Mailchimp, Klaviyo, Brevo (Sendinblue), SendGrid (marketing tier)

**Why they must be separate:**

- If a user marks your marketing email as spam, it affects your sending reputation for that domain
- If your marketing domain gets blocked, your transactional emails still get through
- Using a single domain for both means a spam complaint on a newsletter can prevent password reset emails from being delivered

**Infrastructure separation:**

```
transactional: mail.yourdomain.com  → Resend / Postmark
marketing:     news.yourdomain.com  → Mailchimp / Klaviyo
```

Separate DNS records (SPF, DKIM, DMARC) for each subdomain.

## 4. Bounce & Complaint Handling

Bounces and spam complaints are not edge cases — they happen on every active mailing list. How you handle them determines whether your emails reach inboxes.

**Hard bounces:**

- The email address does not exist or the domain does not accept mail
- Immediately remove the address from your sending list — add to suppression list
- Never retry a hard-bounced address — it damages your reputation
- Store the bounce in a `email_suppressions` table: `{ email, type: 'hard_bounce', occurred_at }`

**Soft bounces:**

- Temporary failure — mailbox full, server temporarily unavailable
- Retry with exponential backoff — most providers do this automatically
- After 3–5 consecutive soft bounces over several days, treat as a hard bounce

**Spam complaints:**

- The user clicked "This is spam" in their email client
- Immediately add to suppression list — never send to them again
- A complaint rate above 0.1% will get your sending domain blocked by Gmail and others
- Most providers send you complaint notifications via webhook — configure this on day one

**Suppression list — required:**

```sql
CREATE TABLE email_suppressions (
  id         bigserial PRIMARY KEY,
  email      text NOT NULL,
  reason     text NOT NULL,  -- 'hard_bounce', 'complaint', 'unsubscribe', 'manual'
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON email_suppressions (email);
```

- Check the suppression list before every send — never send to a suppressed address
- Most email providers maintain a suppression list for you — sync it to your own DB as well
- Your provider's suppression list only covers their platform — if you switch providers, you need your own

**Feedback loops:**

- Register for feedback loops (FBL) with major ISPs (Yahoo, Outlook/Hotmail)
- FBL delivers a complaint notification to you when a user marks your email as spam in their client
- Configure your provider to forward these to a webhook in your application

## 5. Notification Architecture

**Channels — define all channels upfront:**

| Channel | Best for | Notes |
| --- | --- | --- |
| Email | Receipts, summaries, account events | High trust, async, rich content |
| SMS | OTPs, time-sensitive alerts | Immediate, expensive, character limit |
| Push notification | Mobile app alerts | Requires app install, opt-in |
| In-app notification | Activity feed, alerts inside the app | No delivery risk, always fresh |
| Webhook | Developer integrations | For API consumers, not end users |

**Notification preferences — users must control what they receive:**

- Every notification type must be individually toggleable by the user
- Store preferences per user per channel: `{ userId, channel, notificationType, enabled }`
- Transactional notifications (OTP, password reset, order confirmation) cannot be disabled
- All other notifications must be opt-out (or opt-in for marketing)
- Check preferences before dispatching every notification

**Template management:**

- Templates live in the codebase or a template service — not hardcoded in business logic
- One template per notification type per channel
- Templates are versioned — a template change does not break in-flight notifications
- Plain text fallback for every HTML email — not all email clients render HTML
- Test every template in multiple email clients (Gmail, Outlook, Apple Mail) before shipping

**Notification dispatching — always via queue:**

```
User action
  → Action fires NotificationEvent
    → Listener receives event
      → Checks user preferences
        → Dispatches NotificationJob to queue
          → Job sends via the appropriate channel provider
            → Logs result to notifications_log table
```

- Never send notifications synchronously in the request/response cycle
- Every notification send is logged: `{ userId, type, channel, status, sentAt, failedAt, errorMessage }`
- Failed notifications are retried with exponential backoff — see Section 7

## 6. Unsubscribe Management

Unsubscribe handling is not optional — it is legally required under CAN-SPAM, GDPR, and most other email regulations. Getting it wrong can get your domain blacklisted and expose you to fines.

**One-click unsubscribe (required for marketing email):**

- Every marketing email must have a visible unsubscribe link in the body
- Google and Yahoo now require `List-Unsubscribe` and `List-Unsubscribe-Post` headers for bulk senders — this enables one-click unsubscribe directly in the email client without opening the email
- Unsubscribe must take effect within 10 business days (CAN-SPAM) — immediately is better
- Never require the user to log in to unsubscribe — the unsubscribe link must work without authentication
- Use a signed token in the unsubscribe URL — `https://yourdomain.com/unsubscribe?token=<hmac-signed-token>` — prevents others from unsubscribing users maliciously

**Unsubscribe link headers:**

```
List-Unsubscribe: <mailto:unsubscribe@yourdomain.com?subject=unsubscribe>, <https://yourdomain.com/unsubscribe?token=abc>
List-Unsubscribe-Post: List-Unsubscribe=One-Click
```

**Granular vs global unsubscribe:**

- Give users the option to unsubscribe from specific notification types — not just "unsubscribe from everything"
- "Update preferences" link alongside the unsubscribe link lets users reduce emails without fully opting out
- Record the specific type unsubscribed from: `{ userId, notificationType: 'weekly_digest', unsubscribedAt }`

**Re-subscription:**

- Never automatically re-subscribe a user who has unsubscribed — not on account update, not on import
- If you re-enable sending to a user, require explicit re-consent and log it with a timestamp

**Compliance record:**

- Keep a full audit log of all subscribe and unsubscribe events
- Store: `{ userId, email, action, notificationType, ipAddress, userAgent, timestamp }`
- This is your proof of consent if a regulator asks

## 7. Queue, Reliability & Idempotency

**Always queue email sends — never send synchronously:**

- Email sending blocks for hundreds of milliseconds to seconds
- The provider may be temporarily unavailable
- Sending in the request/response cycle means a provider outage returns a 500 to your user
- Queue the send job and return a 200/201 immediately

**Job structure — every email job must declare:**

- `tries`: 3–5 attempts
- `backoff`: exponential — \[30, 120, 300\] seconds
- `timeout`: 30 seconds per attempt
- `failed()`: log the failure, mark the notification as failed, alert if it is a transactional email

**Idempotency — prevent duplicate sends:**

- OTP emails and password reset emails sent twice = serious UX and security issue
- Store a unique send key per notification event: `{ userId, notificationType, referenceId, sentAt }`
- Check for an existing send record before dispatching — if already sent, skip
- Use the outbox pattern for critical notifications — see `docs/patterns/outbox-pattern.md`

```ts
// Check before send
const alreadySent = await notificationLog.findOne({
  where: { userId, type: 'order_confirmation', referenceId: orderId }
});
if (alreadySent) return; // idempotent — skip duplicate
```

**Retry strategy for different email types:**

| Type | Retry | On permanent failure |
| --- | --- | --- |
| OTP / password reset | 3 attempts, fast backoff | Alert ops immediately |
| Order confirmation | 3–5 attempts, slow backoff | Alert ops, mark order |
| Marketing email | 1–2 attempts | Log, skip — do not over-retry |

**Rate limiting outbound sends:**

- Most providers have per-second and per-day sending limits
- Use a queue with concurrency control — not a flood of parallel jobs
- Bulk sends should be batched and spread over time — not all at once
- Monitor your provider's rate limit headers and back off when approaching the limit

## 8. Testing Email & Notifications

**Never send real emails in tests.** Fake the email provider and assert against what would have been sent.

**Laravel:**

```php
Mail::fake();
// trigger the action
Mail::assertSent(OrderConfirmationMail::class, function ($mail) use ($order) {
    return $mail->hasTo($order->customer->email)
        && $mail->order->id === $order->id;
});
Mail::assertNothingSentTo('someone@else.com');
```

**Node/TypeScript:**

```ts
vi.mock('../lib/email', () => ({ sendEmail: vi.fn().mockResolvedValue({ id: 'msg_123' }) }));
// trigger the action
expect(sendEmail).toHaveBeenCalledWith(expect.objectContaining({
  to: 'user@example.com',
  template: 'order_confirmation',
}));
```

**Local development — use a mail trap:**

- Mailtrap, Mailpit, or MailHog — catches all outgoing email and displays it in a UI
- Never use real email addresses in development — all emails go to the trap
- Configure via environment variable: `MAIL_DRIVER=mailtrap` in development

**What to test:**

- Email is dispatched when the triggering action completes
- Email is sent to the correct recipient
- Email is not sent to suppressed addresses
- Email is not sent to users who have unsubscribed from that notification type
- Duplicate sends are prevented — idempotency check works
- Failed send is retried and eventually logged as failed
- Transactional emails (OTP, password reset) cannot be unsubscribed from

**Preview testing:**

- Render every email template and visually inspect it before shipping
- Test in Gmail, Outlook, Apple Mail — they render HTML very differently
- Use a service like Litmus or Email on Acid for automated cross-client preview
- Test the plain text version — it must make sense without the HTML

## 9. Email & Notifications Checklist

**Deliverability (before first send):**

- [ ] SPF DNS record configured for the sending domain?
- [ ] DKIM signing enabled and DNS record added?
- [ ] DMARC policy configured — starting with `p=none` to monitor?
- [ ] Sending from a dedicated subdomain — not the root domain?
- [ ] Using a reputable transactional email provider?

**Infrastructure:**

- [ ] Transactional and marketing email on separate sending domains?
- [ ] Suppression list table created?
- [ ] Bounce webhook configured with provider?
- [ ] Spam complaint webhook configured with provider?
- [ ] Notification log table created?

**Sending:**

- [ ] All email sends via queue — never synchronous in request/response?
- [ ] Jobs declare tries, backoff, timeout, and failed() handler?
- [ ] Suppression list checked before every send?
- [ ] User notification preferences checked before every non-transactional send?
- [ ] Duplicate send prevention — idempotency key or send record check?

**Unsubscribe:**

- [ ] Every marketing email has an unsubscribe link?
- [ ] `List-Unsubscribe` and `List-Unsubscribe-Post` headers on marketing emails?
- [ ] Unsubscribe works without authentication?
- [ ] Unsubscribe token is HMAC-signed?
- [ ] Unsubscribe events logged with timestamp?

**Testing:**

- [ ] Email provider mocked in all tests — no real emails sent?
- [ ] Mailtrap or equivalent configured for local development?
- [ ] Every email template previewed in Gmail, Outlook, Apple Mail?
- [ ] Plain text fallback for every HTML email?
- [ ] Duplicate send test passing — same event does not send twice?
