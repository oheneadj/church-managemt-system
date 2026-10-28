# Integrations

> Deep-dive on every third-party service the platform depends on. For each integration: what it does, how it's wired up, the exact API flow, error handling, and what to do when it breaks.

---

## Integration Overview

| Service | Purpose | Auth Method | SDK / Client |
|---|---|---|---|
| Paystack | Payments (MoMo + card), subaccounts, webhooks | Secret key (header) | Official PHP SDK |
| PayPerlez | SMS, OTP, MoMo collections, signed webhooks | `X-API-Key` header | Raw HTTP via Laravel HTTP client |
| Africa's Talking | SMS (OTP, receipts, broadcasts) — fallback | API key | Official PHP SDK |
| Moolre | USSD giving (`*713#`), SMS | API key + webhook secret | Raw HTTP via Laravel HTTP client |
| Meta Business API | WhatsApp (CHECKIN, broadcasts, receipts) | Bearer token | Raw HTTP via Laravel HTTP client |
| Resend | Transactional email | API key (SMTP) | Laravel Mail (SMTP) |
| Cloudflare R2 | File storage (photos, exports) | Access key + secret | `league/flysystem-aws-s3-v3` |

**Provider toggles** — at least one SMS provider and at least one payment provider must be active at all times. See Decision 11 in `decisions/product-and-architecture-decisions.md` for the full provider interface pattern and `.env` toggle keys.

---

## 1. Paystack

> **Cards only (D-020).** MoMo collections and church payouts go through PayPerlez (section 7). The Paystack sections below remain the reference for card payments and the subaccount fee split. Any "MoMo charge" flow here is superseded by PayPerlez.

### What It Does
- Processes MoMo (MTN, Telecel, AirtelTigo) and card payments
- Routes platform fee to platform account via subaccounts
- Sends webhooks on payment events

### Account Setup

Each church needs a **Paystack subaccount**. The platform holds the master Paystack account. During church onboarding:

```
POST https://api.paystack.co/subaccount
Authorization: Bearer {PAYSTACK_SECRET_KEY}
{
  "business_name": "Grace Assembly International",
  "settlement_bank": "058",          // bank code (from Paystack bank list)
  "account_number": "0123456789",
  "percentage_charge": 80            // church receives 80%, platform keeps 20% (example)
}
```

Response: `{ "data": { "subaccount_code": "ACCT_abc123xyz" } }`

Store `subaccount_code` on the `churches` record. Every subsequent payment for this church uses this code.

**Important:** The `percentage_charge` is what the *church* receives. So if the platform keeps 2%, set `percentage_charge: 98`.

### MoMo Charge Flow

```php
// 1. Initialise transaction
POST https://api.paystack.co/transaction/initialize
{
  "email": "member@church.com",     // required by Paystack; use a placeholder if no email
  "amount": 20000,                  // in kobo/pesewas — GHS 200 = 20000
  "currency": "GHS",
  "subaccount": "ACCT_abc123xyz",
  "mobile_money": {
    "phone": "0244123456",
    "provider": "mtn"               // mtn | vodafone | tigo
  },
  "channels": ["mobile_money"],
  "reference": "unique_ref_here"    // generate with Str::uuid()
}

// Response
{
  "data": {
    "authorization_url": "https://checkout.paystack.com/...",
    "access_code": "...",
    "reference": "unique_ref_here"
  }
}
```

The member receives a USSD prompt on their phone to approve the payment. The platform listens for the webhook.

### Webhook Handling

Paystack POSTs to `POST /api/webhooks/paystack`.

**Signature verification (do this before anything else):**
```php
$signature = $request->header('X-Paystack-Signature');
$body = $request->getContent();
$computed = hash_hmac('sha512', $body, config('services.paystack.secret'));

if (!hash_equals($computed, $signature)) {
    return response()->json(['error' => 'Invalid signature'], 401);
}
```

**Events handled:**

| Event | Action |
|---|---|
| `charge.success` | Create contribution record. Fire `ContributionReceived` event. Send receipt. |
| `charge.failed` | Log failure. Notify admin via dashboard. No contribution record created. |
| `refund.processed` | Void the contribution record. Log with reason "Refund processed by Paystack". |

**Idempotency:** Before creating a contribution, check:
```php
if (Contribution::where('paystack_reference', $reference)->exists()) {
    return; // Already processed — discard duplicate webhook
}
```

**Payload storage:** Store raw payload in `webhook_logs` *before* processing. Even if processing fails, the payload is recoverable.

### Error Handling

| Paystack Error | Our Response |
|---|---|
| `charge.failed` (insufficient funds) | Update UI to "Payment failed". Offer to record as cash or retry. |
| `charge.failed` (timeout) | Same as above. |
| Webhook delivery failure | Paystack retries for 72 hours. Our `webhook_logs` catches duplicates. |
| Subaccount creation failure | Log error. Flag church onboarding as incomplete. Do not allow giving until resolved. |
| API 5xx error | Retry with exponential backoff (3 attempts). Alert if all fail. |

### Test vs Production

Use Paystack's test environment for development and staging:
- Test keys: `sk_test_*` / `pk_test_*`
- Test MoMo numbers: Paystack provides dummy numbers for each network in their docs
- Webhooks in test: Use Paystack's webhook test tool, or `ngrok` for local development

**Never mix test and production keys. Production keys only in the production `.env`.**

---

## 2. Africa's Talking (SMS)

> **Fallback provider (D-020).** PayPerlez is the default for SMS and OTP. Africa's Talking is implemented behind the same `SmsProvider` interface and is enabled by changing `SMS_PRIMARY_PROVIDER`. Credit tracking, character limits and OTP rules below apply to whichever provider is active.

### What It Does
- Delivers OTP codes to staff and members
- Sends contribution receipts
- Sends broadcast messages (pastoral, event announcements)
- Sends automated alerts (absence, pledge reminders, birthday)

### Setup

```php
// composer.json
"africastalking/africastalking": "^4.0"

// config/services.php
'africastalking' => [
    'api_key'   => env('AT_API_KEY'),
    'username'  => env('AT_USERNAME'),
    'sender_id' => env('AT_SENDER_ID'),
],
```

### Sending a Message

All SMS sends go through `SendSmsJob`. Never call the AT SDK directly from a controller or service.

```php
// SendSmsJob.php
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // seconds between retries

    public function __construct(
        private string $phone,
        private string $message,
        private ?string $messageLogId = null,
        private ?string $churchId = null,
    ) {}

    public function handle(): void
    {
        $AT = new AfricasTalking(
            config('services.africastalking.username'),
            config('services.africastalking.api_key')
        );

        $result = $AT->sms()->send([
            'to'      => [$this->phone],
            'message' => $this->message,
            'from'    => config('services.africastalking.sender_id'),
        ]);

        $recipients = $result['SMSMessageData']['Recipients'] ?? [];
        $status     = $recipients[0]['status'] ?? 'failed';
        $cost       = $recipients[0]['cost'] ?? '0';

        // Update message_log
        if ($this->messageLogId) {
            MessageLog::where('id', $this->messageLogId)->update([
                'status'            => $status === 'Success' ? 'sent' : 'failed',
                'provider_response' => json_encode($result),
                'cost_units'        => $this->parseCost($cost),
                'sent_at'           => now(),
            ]);
        }

        // Deduct credits from church balance
        if ($status === 'Success' && $this->churchId) {
            $this->deductCredits($this->churchId, $cost);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendSmsJob failed', [
            'phone' => $this->phone,
            'error' => $exception->getMessage(),
        ]);

        if ($this->messageLogId) {
            MessageLog::where('id', $this->messageLogId)
                ->update(['status' => 'failed']);
        }
    }
}
```

### Credit Management

```php
private function deductCredits(string $churchId, string $cost): void
{
    // AT cost format: "GHS 0.0600" — parse the numeric part
    $units = (float) preg_replace('/[^0-9.]/', '', $cost);

    DB::transaction(function () use ($churchId, $units) {
        $church = Church::lockForUpdate()->find($churchId);

        SmsCreditLedger::create([
            'church_id'     => $churchId,
            'type'          => 'consumption',
            'credits'       => -1,               // 1 credit per SMS regardless of cost
            'balance_after' => $church->sms_credits - 1,
        ]);

        $church->decrement('sms_credits');
    });
}
```

**Credit check before dispatch:**
```php
if ($church->sms_credits <= 0) {
    // Don't queue the job — notify admin instead
    Notification::send($church->adminUser(), new SmsCreditsExhausted($church));
    return;
}
```

### SMS Character Limits

- Standard GSM: 160 characters (1 SMS unit)
- Multi-part: 153 characters per part (each part = 1 credit)
- Unicode (for Twi, Ga, or emoji): 70 characters per part

Always check message length before dispatching. For broadcasts, the compose UI shows a character counter and part count.

### OTP Flow

OTPs are sent via `SendSmsJob` with no church credit deduction — OTP delivery is platform-funded:

```php
// OTP message format
"Your [AppName] code is: 382910. Valid for 10 minutes. Do not share."
```

OTP storage: 6-digit numeric, hashed with `bcrypt`, stored in Redis with 10-minute TTL:
```php
$otp   = random_int(100000, 999999);
$key   = "otp:{$phone}";
$hash  = bcrypt((string) $otp);

Redis::setex($key, 600, $hash); // 600 seconds = 10 minutes
```

Verification:
```php
$hash = Redis::get("otp:{$phone}");
if (!$hash || !Hash::check($otp, $hash)) {
    // Invalid or expired
}
Redis::del("otp:{$phone}"); // Single-use
```

---

## 3. Meta Business API (WhatsApp)

### What It Does
- Receives CHECKIN commands from members
- Sends CHECKIN confirmations (session messages)
- Sends contribution receipts (template messages)
- Sends broadcast messages to opted-in members (template messages)
- Sends weekly pastor digest (template messages)

### Account Requirements
- Verified Meta Business Account
- WhatsApp Business Phone Number
- Approved message templates for all outbound messages
- Webhook registered at `/api/webhooks/whatsapp`

### Webhook Setup

Meta requires two things:

**1. Verification (GET):**
```php
// GET /api/webhooks/whatsapp
public function verify(Request $request): Response
{
    $mode      = $request->query('hub_mode');
    $token     = $request->query('hub_verify_token');
    $challenge = $request->query('hub_challenge');

    if ($mode === 'subscribe' && $token === config('services.meta.verify_token')) {
        return response($challenge, 200);
    }

    return response('Forbidden', 403);
}
```

**2. Message handling (POST):**
```php
// POST /api/webhooks/whatsapp
public function receive(Request $request): JsonResponse
{
    // Store raw payload immediately
    WebhookLog::create([
        'source'  => 'meta_whatsapp',
        'event'   => 'message',
        'payload' => $request->getContent(),
    ]);

    ProcessWhatsAppWebhookJob::dispatch($request->all());

    // Always return 200 immediately — Meta will retry if we return anything else
    return response()->json(['status' => 'ok']);
}
```

### CHECKIN Command Flow

```php
// ProcessWhatsAppWebhookJob.php
public function handle(): void
{
    $messages = data_get($this->payload, 'entry.0.changes.0.value.messages', []);

    foreach ($messages as $message) {
        if ($message['type'] !== 'text') continue;

        $phone   = '+' . $message['from'];
        $text    = strtoupper(trim($message['text']['body']));

        if (!str_starts_with($text, 'CHECKIN')) {
            continue; // Not a CHECKIN command — ignore
        }

        // Parse: "CHECKIN GRA01" or just "CHECKIN"
        $parts = explode(' ', $text);
        $code  = $parts[1] ?? null;

        // Find church by phone
        $member = Member::where('phone', $phone)
            ->where('status', 'active')
            ->first();

        if (!$member) {
            $this->reply($phone, "We couldn't find your number in our records. Please contact the church office.");
            continue;
        }

        // Set tenant context manually (webhook bypasses tenant middleware)
        app()->instance('current.church', $member->church);

        // Find active service
        $service = Service::where('is_open', true)
            ->when($code, fn($q) => $q->where('checkin_code', $code))
            ->first();

        if (!$service) {
            $this->reply($phone, "No service is currently open for check-in. Please contact the church admin.");
            continue;
        }

        // Record attendance (idempotent)
        AttendanceRecord::firstOrCreate([
            'church_id'  => $member->church_id,
            'service_id' => $service->id,
            'member_id'  => $member->id,
        ], [
            'method'         => 'whatsapp',
            'checked_in_at'  => now(),
        ]);

        $this->reply($phone,
            "✅ Checked in to {$service->name} — " . $service->service_date->format('j M Y') .
            ". Welcome, {$member->preferred_name ?? $member->first_name}! God bless you. 🙏"
        );
    }
}

private function reply(string $phone, string $message): void
{
    Http::withToken(config('services.meta.token'))
        ->post("https://graph.facebook.com/v18.0/" . config('services.meta.phone_number_id') . "/messages", [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'text',
            'text'              => ['body' => $message],
        ]);
}
```

### Template Messages

Outbound messages to members who haven't messaged in the last 24 hours **must** use pre-approved templates. Submit these to Meta before launch:

**Template: contribution_receipt**
```
{{1}}, your gift of GHS {{2}} ({{3}}) to {{4}} has been received. 
Your total giving this month: GHS {{5}}. Thank you! 🙏
```
Variables: `[member_name, amount, fund_type, church_name, monthly_total]`

**Template: checkin_confirmation** (if session window expired)
```
Welcome to {{1}}, {{2}}! You're checked in for {{3}}. God bless you. 🙏
```

**Template: attendance_follow_up**
```
Hi {{1}}, we've missed you at {{2}}! We'd love to see you this Sunday. 
Reply STOP to unsubscribe.
```

**Template: broadcast_message**
```
{{1}} — {{2}}
```
Variables: `[church_name, message_body]`

### Opt-In Requirement

Meta requires that members opt in to receive WhatsApp messages. The opt-in is recorded in `members.whatsapp_opted_in`. A member opts in by:
1. Messaging the church WhatsApp number first (e.g. sending CHECKIN) — this constitutes opt-in
2. Admin marking them as opted-in during data capture

### Error Handling

| Error | Response |
|---|---|
| Member phone not found | Reply: "We couldn't find your number..." |
| No open service | Reply: "No service is currently open..." |
| Template rejected by Meta | Fall back to SMS via Africa's Talking |
| Meta API rate limit (1,000 messages/second) | Queue with rate limiting (100/second to be safe) |
| Webhook delivery failure | Meta retries for 24 hours. Our processing is idempotent. |

---

## 4. Resend (Email)

### What It Does
- Staff invitation emails
- Export download links
- System alerts (to platform admin — not churches)

### Setup

Resend is configured as a standard SMTP driver in Laravel:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=re_xxxxxxxxxxxx
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@yourapp.com
MAIL_FROM_NAME="YourApp"
```

### Switching to Own SMTP

When email volume justifies a self-hosted SMTP (e.g. Postal):

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.yourapp.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourapp.com
MAIL_PASSWORD=xxxx
MAIL_ENCRYPTION=tls
```

No code changes. No migration. Restart queue workers for the new config to take effect.

### Email Classes

```php
// app/Mail/StaffInvitationMail.php
class StaffInvitationMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: "You've been invited to {$this->church->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staff-invitation', with: [
            'church'  => $this->church,
            'inviter' => $this->inviter,
            'loginUrl' => config('app.url') . "/{$this->church->slug}",
        ]);
    }
}
```

All emails are dispatched via `Mail::to(...)->queue(...)` — never `send()`.

---

## 5. Cloudflare R2

### What It Does
- Stores member profile photos
- Stores export files (CSV downloads)
- Stores receipt attachments (v2)

### Setup

```php
// config/filesystems.php
'r2' => [
    'driver'   => 's3',
    'key'      => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
    'secret'   => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
    'region'   => 'auto',
    'bucket'   => env('CLOUDFLARE_R2_BUCKET'),
    'url'      => env('CLOUDFLARE_R2_URL'),
    'endpoint' => env('CLOUDFLARE_R2_URL'),
    'use_path_style_endpoint' => true,
],
```

### File Path Convention

```
churches/{church_id}/members/{member_id}/photo.jpg
churches/{church_id}/exports/{export_id}/members-{date}.csv
```

### Uploading a Photo

```php
public function uploadPhoto(Member $member, UploadedFile $file): string
{
    $path = "churches/{$member->church_id}/members/{$member->id}/photo.jpg";

    Storage::disk('r2')->put(
        $path,
        file_get_contents($file->getRealPath()),
        'private'
    );

    $member->update(['photo_url' => $path]);
    return $path;
}
```

### Signed URLs (Time-Limited Access)

Photos are private. Return a signed URL on every API response — valid for 1 hour:

```php
// In MemberResource
'photo_url' => $this->photo_url
    ? Storage::disk('r2')->temporaryUrl($this->photo_url, now()->addHour())
    : null,
```

### Cost Estimate

R2 pricing:
- Storage: $0.015/GB/month
- Class A operations (PUT, POST): $4.50/million
- Class B operations (GET): $0.36/million
- **Egress to internet: FREE**

At 500 churches × 200 members × 200KB average photo = 20GB storage = **$0.30/month**.

---

## Integration Health Monitoring

All integrations have a corresponding health check. See [`17-monitoring-and-alerting.md`](./17-monitoring-and-alerting.md) for alert thresholds.

| Integration | Health Signal | Alert On |
|---|---|---|
| Paystack | Last webhook received within 60 min during business hours | No `charge.success` in 60 min on a Sunday |
| Africa's Talking | Message log `failed` rate | > 10% failure rate in a 15-min window |
| Meta WhatsApp | CHECKIN response time | No CHECKIN response within 30s of receipt |
| Resend | Bounce rate | > 5% bounce rate on any send batch |
| Cloudflare R2 | Upload success rate | Any R2 upload failure in production |

---

## 6. Moolre (USSD Giving)

> **On hold (D-019): USSD giving is deferred past the MVP, so Moolre is not integrated in v1.** This section is reference only. The verified Moolre API notes near the end of this section supersede the earlier placeholder code (which assumed an HMAC signature that Moolre does not use).

> **Provider status:** Moolre selected as the reference implementation. Final provider decision pending — see `docs/13-decision-log.md`. When the provider changes, only this section and the `.env` keys need updating; the Job and Service interface stays the same.

### What It Does

USSD giving lets any member give directly from their phone by dialling a short code — no internet, no smartphone, no app required. The flow:

1. Member dials `*713*churchcode#` (or the platform's shared shortcode with church routing)
2. USSD menu guides them: select fund type → enter amount → confirm
3. Payment processed via the member's mobile money wallet (MTN MoMo, Telecel, AirtelTigo)
4. Funds settled to the church's bank account
5. Platform receives a webhook → contribution record created → SMS receipt sent

This makes giving accessible to every member regardless of their phone type or data connection.

### Account Setup

Moolre provides:
- A **platform shortcode** (e.g. `*713#`) shared across all churches, with each church identified by a numeric code
- Or a **dedicated shortcode per church** (premium, arranged directly with telcos)

Register at [moolre.co](https://moolre.co). Credentials issued per platform account (not per church).

```
Config key: services.moolre.*
MOOLRE_API_KEY=
MOOLRE_SHORTCODE=*713#
MOOLRE_CALLBACK_URL=https://yourapp.com/api/webhooks/ussd
```

### USSD Menu Flow (Platform Side)

Moolre handles the USSD session state. We configure the menu via their dashboard or API:

```
Level 1 (on dial):   "Welcome to [AppName]. Enter your church code:"
Level 2:             "Select fund: 1. Tithe  2. Offering  3. Project Fund"
Level 3:             "Enter amount (GHS):"
Level 4:             "Confirm GHS {amount} to {church name}? 1. Yes  2. No"
Level 5:             "Processing... You will receive a confirmation SMS."
```

The session resolves asynchronously — Moolre sends a webhook once the mobile money debit is confirmed.

### Webhook Handler

```php
// routes/api.php
Route::post('/webhooks/ussd', [UssdWebhookController::class, 'handle'])
    ->middleware('verify.moolre.signature');
```

```php
// app/Http/Controllers/UssdWebhookController.php
public function handle(Request $request): JsonResponse
{
    // 1. Store raw payload immediately (encrypted)
    WebhookLog::create([
        'source'  => 'moolre',
        'payload' => $request->all(), // 'encrypted' cast handles AES encryption
    ]);

    // 2. Dispatch async — return 200 immediately
    ProcessUssdWebhookJob::dispatch($request->all())
        ->onQueue('payments');

    return response()->json(['status' => 'received']);
}
```

### Signature Verification

```php
// app/Http/Middleware/VerifyMoolreSignature.php
public function handle(Request $request, Closure $next): Response
{
    $signature = $request->header('X-Moolre-Signature');
    $expected  = hash_hmac('sha256', $request->getContent(), config('services.moolre.secret'));

    if (! hash_equals($expected, $signature ?? '')) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    return $next($request);
}
```

> **Note:** Confirm Moolre's exact signature header name and algorithm before Sprint 4. The pattern above is standard — adjust if their docs differ.

### Processing the Webhook

```php
// app/Jobs/ProcessUssdWebhookJob.php
public function handle(): void
{
    $phone     = $this->payload['phone'];       // E.164 format from Moolre
    $amount    = (float) $this->payload['amount'];   // GHS — Moolre sends GHS, not pesewas
    $reference = $this->payload['reference'];   // Moolre transaction reference
    $churchCode = $this->payload['church_code'];

    // Idempotency — don't double-process
    if (Contribution::where('ussd_reference', $reference)->exists()) {
        Log::info('USSD webhook duplicate', ['reference' => $reference]);
        return;
    }

    // Resolve church from code
    $church = Church::where('ussd_code', $churchCode)->firstOrFail();

    // Resolve member from phone (may be null — anonymous if not found)
    $member = Member::where('phone', $phone)->first();

    // Create contribution
    $contribution = Contribution::create([
        'church_id'      => $church->id,
        'member_id'      => $member?->id,
        'amount'         => $amount,
        'fund_type_id'   => $this->resolveFundType($this->payload['menu_selection'], $church),
        'payment_method' => 'ussd',
        'ussd_reference' => $reference,
        'status'         => 'completed',
    ]);

    event(new ContributionReceived($contribution));
}
```

### Church USSD Code

Each church is assigned a short numeric code during onboarding (e.g. `1042`). Members dial `*713*1042#` to give to that specific church.

```php
// Generated during onboarding — stored on churches table
'ussd_code' => str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
```

The `ussd_code` column must be unique. Regenerate on collision.

### Error Handling

| Scenario | Handling |
|---|---|
| Moolre API down | Webhook not received — Moolre retries for 24 hours. No action needed. |
| Unknown church code | Log warning, do not create contribution, Moolre shows "Invalid church code" |
| Unknown phone (no member match) | Contribution created as anonymous — `member_id: null` |
| Duplicate reference | Silently skip (idempotency guard above) |
| Amount below GHS 1.00 | Rejected by Moolre before reaching our webhook |

### Config Reference

```php
// config/services.php
'moolre' => [
    'api_key'      => env('MOOLRE_API_KEY'),
    'secret'       => env('MOOLRE_WEBHOOK_SECRET'),
    'shortcode'    => env('MOOLRE_SHORTCODE', '*713#'),
    'callback_url' => env('MOOLRE_CALLBACK_URL'),
],
```

### Moolre pricing and facts (moolre.com/pricing, read 27 Oct 2026; verify before launch)

| Item | Published terms |
|---|---|
| Collection (MoMo) | Moolre fee 1% (min GHS 0.50, cap GHS 10) **plus** network fee 0.5–1% (cap GHS 20), added on top of the amount (e.g. GHS 1,000 gift: GHS 20 fees, giver pays GHS 1,020) |
| Transfer / payout | Moolre fee 1% (min GHS 0.50, cap GHS 10), network fee 0%; added on top (GHS 1,000 transfer costs GHS 1,010) |
| USSD, shared shortcode | GHS 420.00 per month (platform-wide fixed cost) + GHS 0.014 per session |
| USSD, dedicated shortcode | GHS 3,500 per network, or GHS 10,500 for all 3 networks |
| Settlement | "Daily bank settlements" listed for the merchant wallet. No timing for wallet availability or API transfers is published. |

Implications for us (D-018):
- Small USSD gifts are expensive: the 0.50 minimum fee dominates below about GHS 50. A GHS 5 gift costs the giver about GHS 0.53 extra, and a GHS 5 payout costs the church a further GHS 0.50 (10%). PayPerlez's sandbox fees were roughly 1% or less on small amounts. Consider a higher minimum for USSD than the GHS 1.00 digital minimum.
- The USSD subscription (GHS 420/month) is a fixed platform cost from day one, regardless of how many churches use USSD.
- The signature scheme and webhook code earlier in this section (`X-Moolre-Signature`, HMAC-SHA256, `phone`/`amount`/`reference` payload) were placeholders and are **wrong**. See the verified API below.

### Moolre API (verified from docs.moolre.com/llms-full-live.txt, 27 Oct 2026)

Base URLs: live `https://api.moolre.com`, sandbox `https://sandbox.moolre.com`. All bodies are JSON; replies are `{ status, code, message, data, go }` where `code` is Moolre's own status code (not HTTP).

**Credentials are wallet-specific.** Each wallet needs its own API Service with its own keys. Collections use `X-API-USER` + `X-API-PUBKEY` (public key). Transfers need `X-API-USER` + `X-API-KEY` (**private** key). Live wallets are created in the Moolre portal only; sandbox wallets via the Create Account API.

| Purpose | Endpoint | Key fields |
|---|---|---|
| Collect (USSD prompt to payer) | `POST /open/transact/payment` | `type:1`, `channel` (**13**=MTN, 6=Telecel, 7=AT), `currency:"GHS"`, `payer` (0-prefixed), `amount` (string), `externalref` (unique), `accountnumber`; optional `otpcode`, `sessionid`, `reference`, sandbox-only `skipotp` |
| Check collection | `POST /open/transact/status` | `type:1`, `idtype` (1=externalref, 2=Moolre ID), `id`, `accountnumber` |
| Validate payee name | `POST /open/transact/validate` | `type:1`, `receiver`, `channel` (1=MTN, 6, 7, 2=bank), `sublistid` (bank code when channel 2), `currency`, `accountnumber`. Name returned in `data` (code `AVD01`). |
| Pay out (transfer) | `POST /open/transact/transfer` | `type:1`, `channel` (**1**=MTN, 6, 7, 2=bank), `currency`, `amount`, `receiver`, `sublistid`, `externalref` (unique), `accountnumber` |
| Wallet balance | `POST /open/account/status` | `type:1`, `accountnumber` → `data.balance`, `data.callback` |
| Wallet to wallet | `POST /open/transact/internal` | `receiver` must be a Moolre account number |

**Things that differ from PayPerlez (and from our earlier assumptions):**
- **Collection codes:** `TR099` = USSD prompt triggered (outcome still pending). `TP14` = the payer must first complete an SMS verification (OTP) and the call must be retried with `otpcode`. `TP13` = missing/duplicate `externalref`. Always confirm via callback or the status call; a `200` with `status:1` is not payment.
- **No idempotency header.** Moolre relies on a **unique `externalref` per business action**; reuse the same one when retrying, and a duplicate is rejected (`TP13` collections, `APY18` payouts, `TR013` internal). Make `externalref` our contribution/disbursement ULID.
- **Transfer fees are charged on top of the amount sent.** Example from the docs: `amount "1"`, `fee "0.01"`, `amountfee "1.01"`, `networkfee "0.00"`: the recipient gets the full `amount` and our wallet is debited `amountfee`. To make the church bear the fee (D-018) we must send `amount = budget − fee(amount)`, where `budget = gross − platform_fee`. Use the published schedule (1%, min GHS 0.50, cap GHS 10) to compute this, then record the actual `fee` returned. **Caution:** the docs example shows a GHS 0.01 fee on GHS 1.00, which contradicts the published GHS 0.50 minimum; test in the sandbox and reconcile any difference.
- **The transfer is synchronous.** The reply already carries `data.txstatus` (1 = success), `receivername`, `transactionid`, `thirdpartyref`, `fee`, `networkfee`. Payout success code is `OBGH01`; `APY21` = insufficient balance; `APY17` = failed; `OTGH02` = unsupported channel. Uncertain outcomes (for example in-progress) must be treated as unfinished and checked through the status call before any retry.
- **Server IP whitelist for payouts.** Only IPs added under the API Service's Security → "Whitelist IP for Disbursement" can initiate transfers. Our Oracle server's public IP must be whitelisted, and re-whitelisted if the server moves (see the migrate-to-new-server runbook).

**Payment webhook (callback):**
- The callback URL is set on the wallet / API Service. Moolre POSTs `{ status, code, message, data, go }`; `status:1`, `code:"P01"`, `data.txstatus:1` = paid; `status:0`, `code:"P02"`, `data.txstatus:2` = failed (`data.reason` explains; `transactionid` may be null; `amount`/`value` may be `"0"`). `data.txstatus` 0 = pending.
- Fields in `data`: `payer`, `accountnumber`, `amount`, `value` (amount after fees), `transactionid`, `externalref` (correlate on this), `thirdpartyref`, `secret`, `ts` (GMT).
- **There is no HMAC signature.** Authenticity is the `data.secret` value (compare to our stored callback secret, constant-time) **plus** allowlisting Moolre's source IPs: IPv4 `174.138.44.22`, `174.138.40.67`, `192.241.135.134`; IPv6 `2604:a880:400:d1:0:3:4cf0:c001`, `2604:a880:400:d0::1a77:4001`, `2604:a880:400:d0::4234:2001`. IP allowlisting is an extra layer, not a replacement for checking the secret. Retry policy is not published; handle duplicates idempotently and also poll the status call for anything pending.
- Replace the `VerifyMoolreSignature` middleware with a `VerifyMoolreCallback` that checks source IP, then `data.secret`, before storing the payload.

**Still not in Moolre's public docs:** the USSD integration protocol (the guide is interactive-only: how a dialled `*713*code#` session reaches us, menu callbacks, session handling, whether our `ussd_code` model fits Moolre's shared shortcode); when collected funds are available for transfer (docs list only "daily bank settlements" and a `settlement` object on the Update Account call); fee schedule confirmation; maximum amounts and rate limits (docs say limits vary by account and to confirm with a Moolre contact).

### What to Update When Switching Providers

If a different USSD provider is chosen before Sprint 4:

1. Update this section with the new provider's webhook format and signature method
2. Update `VerifyMoolreSignature` middleware (rename + adjust header/algorithm)
3. Update `ProcessUssdWebhookJob` to map the new payload fields to our internal fields
4. Update `.env` keys
5. No changes needed to `Contribution` model, `ContributionReceived` event, or the receipt SMS flow

---

## 7. PayPerlez

> **Primary provider** for SMS, OTP, and MoMo collections. Can run alongside Paystack (which adds card support and per-church subaccounts) and Moolre (which adds USSD). Toggle with `SMS_PROVIDER_PAYPERLEZ` and `PAYMENT_PROVIDER_PAYPERLEZ` in `.env`.

### What It Does

- **SMS:** Send notification, receipt, and broadcast messages to any Ghanaian number
- **OTP:** Deliver 6-digit codes for staff/member login
- **Collections (MoMo):** Accept MoMo payments in GHS via MTN, Telecel, AirtelTigo
- **Disbursements:** Automatically pay each church its share of every MoMo/USSD gift (D-018)
- **Webhooks:** Signed event callbacks for payment confirmation

USSD is listed as "coming soon" on their docs — not available yet. When PayPerlez USSD launches, it can be wired as a second USSD provider alongside Moolre.

### Auth

Every request carries a business-scoped API key in the header:

```
X-API-Key: {PAYPERLEZ_API_KEY}
```

No per-church keys — our platform account holds one key and routes all traffic through it.

### Base URL

```
https://api.payperlez.org/api/v1
```

### Config

```php
// config/services.php
'payperlez' => [
    'api_key'      => env('PAYPERLEZ_API_KEY'),
    'webhook_secret' => env('PAYPERLEZ_WEBHOOK_SECRET'),
    'base_url'     => env('PAYPERLEZ_BASE_URL', 'https://api.payperlez.org/api/v1'),
],
```

```env
PAYPERLEZ_API_KEY=
PAYPERLEZ_WEBHOOK_SECRET=
PAYPERLEZ_BASE_URL=https://api.payperlez.org/api/v1

# Toggle
SMS_PROVIDER_PAYPERLEZ=true
PAYMENT_PROVIDER_PAYPERLEZ=true
```

### SMS Send

```php
// app/Services/Sms/PayPerlez SmsProvider.php
class PayPerlezSmsProvider implements SmsProvider
{
    public function send(string $phone, string $message): void
    {
        Http::withHeader('X-API-Key', config('services.payperlez.api_key'))
            ->post(config('services.payperlez.base_url') . '/messaging/sms', [
                'to'      => $phone,
                'message' => $message,
            ])
            ->throw(); // throws on 4xx/5xx — job's retry handles it
    }
}
```

> **Note:** Confirm the exact endpoint path and request body against PayPerlez docs before Sprint 1. The shape above follows their documented pattern; the specific field names may differ.

### OTP via PayPerlez

PayPerlez has a native OTP endpoint — use it instead of building our own via plain SMS:

```php
// Send OTP
POST /otp/send
{
  "phone": "0244123456",
  "length": 6,
  "expiry": 600   // seconds
}

// Verify OTP
POST /otp/verify
{
  "phone": "0244123456",
  "code": "382910"
}
```

If PayPerlez OTP is unavailable, fall back to generating a code ourselves and sending it via the active SMS provider (same pattern as Africa's Talking OTP flow in Section 2).

### MoMo Collection Flow

```php
// 1. Initiate collection  (verified against biz.payperlez.org/docs/collections, Oct 2026)
POST /api/v1/collections
Headers: X-API-Key, Content-Type: application/json,
         Idempotency-Key: <8–100 chars: letters, digits, . _ : ->   // build from the contribution attempt ID
{
  "amount":          200.00,        // GHS, a decimal — not pesewas
  "network":         "mtn",         // mtn | telecel | at   (note: "at", not "airteltigo")
  "customer_msisdn": "0244123456",
  "client_reference": "<our ULID for this attempt>"
}

// Response: returns a PayPerlez `reference` (exact response schema not shown in the docs)
// Statuses: pending | processing | unknown | succeeded
//   Only `succeeded` is success. `unknown` means the outcome is uncertain: poll, never assume.
// `fee` is the combined total fee and can be null until the collection settles.
```

Read status with `GET /api/v1/collections/{reference}` (refreshes provider status) or `GET /api/v1/status?client_reference=...`. The API key needs the `payments:collect` scope. There are **no fee, commission or split parameters** on collections, which is why the platform fee is applied by us at disbursement time (D-018).

**Workspace setting: use managed balance, not instant settlement.** Per PayPerlez's OpenAPI notes, managed balance keeps the GHS credit in our business balance, while instant settlement reserves and queues a payout to a single approved destination. We pay many different churches from our own code, so the workspace must be set to managed balance. Routing is captured when each collection is created, so set it before creating live collections.

> **Unconfirmed:** the docs say settlement routing (managed balance or "instant settlement" to an approved destination) is set in the PayPerlez workspace and captured when a collection is created, but they do **not** say when collected funds reach `available_balance`. Confirm with PayPerlez (info@payperlez.com) or test in the test environment before relying on "immediate".

Payment confirmation comes via webhook — do not poll.

### Webhook Handling

PayPerlez POSTs to `POST /api/webhooks/payperlez`.

**Signature verification:**
```php
// app/Http/Middleware/VerifyPayPerlezSignature.php
public function handle(Request $request, Closure $next): Response
{
    // Verified against biz.payperlez.org/docs/webhooks, Oct 2026.
    // Header: X-Payperlez-Signature = "t=<unix-seconds>,v1=<hex-hmac>"
    // Signed string: "{t}.{raw body}" with HMAC-SHA256, keyed with the FULL secret including its "whsec_" prefix.
    $header = (string) $request->header('X-Payperlez-Signature');
    parse_str(str_replace(',', '&', $header), $parts);
    $timestamp = $parts['t'] ?? '';
    $provided  = $parts['v1'] ?? '';

    $expected = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), config('services.payperlez.webhook_secret'));

    // Constant-time compare, and reject anything more than 5 minutes off our clock (replay protection)
    $fresh = abs(time() - (int) $timestamp) <= 300;

    if (! $fresh || ! hash_equals($expected, $provided)) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    return $next($request);
}
```

**Events handled:**

| Event | Action |
|---|---|
| `payment.success` | Create contribution record. Fire `ContributionReceived`. Send receipt. |
| `payment.failed` | Log failure. Notify admin. No contribution record created. |

**Idempotency:**
```php
if (Contribution::where('payperlez_reference', $reference)->exists()) {
    return; // Duplicate — discard silently
}
```

> **Not yet confirmed:** the public docs do not list the event names (`payment.success` / `payment.failed` above are our placeholders) or the payload shape. Each event has an `id` (`event.id`); store it for idempotency. Webhook deliveries retry six times per cycle with a new timestamp and signature but the same event ID and body. Return `2xx` once the event is durably stored (and also for a duplicate you already accepted). Get the real event names from the workspace webhook settings or the API reference (`/docs/api/`) before Sprint 4.
>
> **Do not trust the webhook alone.** Collection status `unknown` and late webhooks are possible, so pair the webhook with a scheduled poll of `GET /api/v1/collections/{reference}` for anything still `pending`/`processing`/`unknown` after a short grace period.

### Disbursements (automatic church payouts, D-018)

Verified against biz.payperlez.org/docs/disbursements, Oct 2026.

```
POST /api/v1/disbursements        (scope: transfers:create)
Headers: X-API-Key, Idempotency-Key (build from the disbursement ULID — one logical payout, one key)
{
  "amount":           187.50,       // GHS. The MAXIMUM total business-balance debit, INCLUDING provider fees
  "channel":          "mobile_money",   // or "bank_transfer"
  "recipient_name":   "Grace Assembly",
  "recipient_msisdn": "0244123456",
  "network":          "mtn",        // mtn | telecel | at
  "client_reference": "<disbursement ULID>"
}
GET /api/v1/disbursements/{reference}   (scope: transfers:read; refreshes provider status)
GET /api/v1/disbursements               (scope: transfers:read)
```

**Fees:** provider fees come out of the requested `amount`; the church receives the remainder, which can be less than `amount`. This matches our rule that the church bears transaction fees: we request `gross − platform fee`, and whatever the provider charges is deducted from that. Record the actual fee and the amount delivered when the provider reports them.

**Not final on creation:** pending and uncertain outcomes keep the funds reserved; only a confirmed failure releases the reservation. Subscribe to signed disbursement events and also poll `GET /disbursements/{reference}` for anything unresolved. **Never create a second payout while the first outcome is unresolved**; retries must reuse the same `Idempotency-Key` and body.

**Before paying out:** check `GET /api/v1/account-balance` (scope `balances:read`: `available_balance`, `pending_balance`, `reserved_balance`, `total_balance`, `reconciliation_required`). PayPerlez requires a minimum operating balance of **GHS 1.00**, so only send when `available_balance − requested_amount ≥ 1.00`. Otherwise leave the disbursement `pending` and retry rather than failing it. Collected funds are available immediately on all networks (confirmed by PayPerlez, Oct 2026), so in normal operation the balance already covers each gift's payout.

**Verifying the church's payout account (onboarding):** `POST /api/v1/account-name` (scope `transfers:verify`) with `type: momo` plus network and number, or `type: bank` plus bank code and account number; `GET /api/v1/banks` lists bank codes. Use the returned name to set `payout_account_name` and `payout_verified_at`.

**Bank transfers:** need an account number and a bank code or bank name; a name-only bank transfer is reported as manual execution, so always send the bank code.

**Confirmed request rules (API reference, Oct 2026):** `Idempotency-Key` is required (same key + different body returns `409`). `channel`, `recipient_name` (≤120 chars) and `amount` (min 0.01) are required; `narration` (≤160) and `client_reference` (≤100, unique per business and environment across ALL payment types, collections and disbursements) are optional. Bank fields: `recipient_bank_code`, `recipient_bank_name`, `recipient_account_number`. `201` = result known, `202` = pending/unknown; `400/401/409/422/503` return `{success:false, error:{code,message,fields}, meta:{request_id}}`. Only a single combined `fee` is exposed (null while unknown). Unexpected provider fees hold the payout for reconciliation. Unused reservation returns to `available_balance`. Collections: the fee is **added on top of** the requested `amount` (the business receives `amount`), so the giver pays `amount + fee`.

**Observed in the PayPerlez TEST environment (27 Oct 2026, real calls with a `ppz_test_` key):**
- `GET /account-balance` → `data: { available_balance, pending_balance, reserved_balance, total_balance, currency: "GHS", environment: "test", reconciliation_required }`. Values are plain GHS decimals (e.g. `50901.5`), not pesewas.
- `POST /collections` (GHS 5.00, mtn) → `201` with `data: { reference (UUID), type: "collection", amount: 5, fee: 0.04, received: 5, currency, channel: "mobile_money", network, status: "succeeded", is_test, execution_mode: null, client_reference, details: { customer_name, customer_msisdn (masked), client_reference }, created_at }`. `received` equals the requested `amount`; `fee` is separate and extra. Status was `succeeded` immediately and `available_balance` rose by exactly `amount` in the same moment (nothing went to `pending_balance`).
- `POST /disbursements` (GHS 4.50, mtn) → `201` with `data: { reference, type: "disbursement", amount: 4.5, fee: 0.07, received: 4.43, status: "succeeded", execution_mode: "automatic", client_reference, details: { recipient_name, recipient_account (masked), narration, client_reference }, ... }`. The balance was debited exactly `amount` (4.50); the recipient got `received` = `amount − fee` (4.43). So the church bears the payout fee, and our `provider_fee` = `fee`, `net_amount` = `received`.
- Replaying the same `Idempotency-Key` + body returned the same disbursement with `"idempotent_replay": true` and created no second payout.
- `POST /account-name` (`type: momo`, mtn, `0244000000`) → `data: { type, network, account_number, account_name: "Test Account" }`. The resolved name is **`account_name`**.
- `GET /banks` is public: `data.items[] = { code, name }`.
- `GET /collections/{reference}` and `GET /disbursements/{reference}` return the same object shape as creation.

Caveat: this is the sandbox. It settles instantly by design; production behaviour and fees must still be confirmed with PayPerlez (see D-018 open items). Fee amounts seen in the test (0.04 on 5.00, 0.07 on 4.50) are not a published schedule.

**Still unconfirmed:** webhook event names and payloads; the full status list (only `succeeded` was observed; `pending`, `processing`, `unknown` appear in the docs and `failed` is expected); rate limits and maximum amounts; production settlement timing and fee schedule. Moolre's disbursement API has not been checked at all.

### Data Model Impact

The `contributions` table needs a `payperlez_reference` column (alongside the existing `paystack_reference` and `ussd_reference`):

```php
// In migration
$table->string('payperlez_reference', 100)->nullable()->unique();
```

### Error Handling

| Scenario | Response |
|---|---|
| API 4xx (bad request) | Log the error, mark contribution attempt as failed, notify admin |
| API 5xx (PayPerlez down) | Job retries (3×, exponential backoff). If all fail, falls into `failed_jobs`. Operator switches primary provider in `.env`. |
| Invalid webhook signature | Return 401. Log attempted call with IP. |
| Duplicate webhook | Silent discard (idempotency guard) |

### What Does Not Change When Switching Off PayPerlez

- `Contribution` model and schema (other than adding the reference column)
- `ContributionReceived` event and all downstream listeners
- Receipt SMS content
- Any report or export
- `GivingService` — provider-agnostic

---

## Secrets Management

All integration credentials are environment variables managed by Coolify. They are:
- Never committed to version control (`.env` is in `.gitignore`)
- Rotated when any team member leaves
- Different between staging and production environments
- Stored encrypted at rest by Coolify

**Rotation procedure:** See [`16-deployment-and-ops.md`](./16-deployment-and-ops.md).
