# Changelog

All notable changes to this project are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

> Changes staged but not yet in a versioned release.

### Change record

Every commit is listed here with **what** was done, **why**, **which files** it touched and **the commit**, newest first. The bullets under Added and Changed below are the short summary of the same work. Rule for every new entry: write the entry before the commit, then add the commit hash to it right after (the small commit that only fills in a hash is not listed).

#### feat(giving): record, list, read and void cash gifts (2026-10-28)
- **What:** Built Sprint 4 stories 4.3 and 4.11. POST /contributions records a cash gift for a member or anonymously (fund, amount to the pesewa, optional service, the day it was given, notes); it needs an Idempotency-Key so a double click records one gift, and the gift and its change-history line are saved together. GET /contributions lists gifts newest first with filters (member, service, fund, method, dates, voided) and GET /contributions/{id} reads one. PATCH /contributions/{id}/void marks a gift voided with a required reason, who and when, under a row lock so two voids at once cannot both pass; the gift is never deleted (DELETE is 405) and drops out of every total. A new giving.view permission (church admin and finance secretary) opens the list and a single gift, apart from giving.record and giving.edit, so a church can later let a pastor see named gifts without letting them change any. Amounts are exact decimal strings with a GHS formatted sibling. Added a Voided change-history event, the contribution policy, repository methods, tests (feature, policy, contract, a concurrency test for two voids) and docs/08, 11.
- **Why:** docs/11 stories 4.3 and 4.11, docs/04 Corrections and Voids, QA-06 TC-06-01 to TC-06-05 and TC-06-11 to TC-06-13, QA-10. Cash is what most churches do every Sunday and moves no outside money, so it is built before the digital payments that wait on the legal opinion (D-046). The agreement requires an idempotency key on money-recording writes, row locking for a contested change, an audit trail for giving records, and money kept as exact decimals (sections 10, 11, 12).
- **Files:** app/Actions/Contributions/, app/DTOs/Contributions/, app/Http/Controllers/Api/V1/Contributions/, app/Http/Requests/Contributions/, app/Http/Requests/Concerns/DelegatesToPolicy.php, app/Http/Resources/Contribution*.php, app/Policies/ContributionPolicy.php, app/Repositories/ (Contribution interface and Eloquent), app/Exceptions/ContributionAlreadyVoidedException.php, app/Models/Contribution.php, app/Enums/ (StaffPermission, StaffRole, AuditEvent), app/Providers/AppServiceProvider.php, bootstrap/app.php, routes/api.php, config/giving.php, docs/ (08, 11, openapi.json), and new Pest tests
- **Commit:** pending

#### fix(members): stop a missing middle name leaving a double space in the full name (2026-10-28)
- **What:** A member's full name is now built from the parts that exist, joined with single spaces. Before, a member with no middle name came out as 'Emmanuel  Asante' with two spaces everywhere the full name is shown.
- **Why:** Found by a gift list test. The two spaces showed in lists, search results, receipts and exports for most members, since most have no middle name.
- **Files:** app/Models/Member.php, tests/Unit/Models/MemberTest.php
- **Commit:** `98235be` (2026-10-28)

#### feat(api): add Idempotency-Key support for writes that record money (2026-10-28)
- **What:** Added a reusable idempotent route middleware. A write sent with an Idempotency-Key header runs once: the first successful answer is kept for 24 hours and sent again, marked Idempotent-Replayed, when the same key comes back. The same key with a different request is a 422; a missing or badly shaped key is a 400; two requests with one key at the same moment take turns using a cache lock, and one that waits too long gets a 409. Failed requests are not kept so a corrected request can reuse its key, and keys are private to the church and the person. Documented in docs/08 Conventions. Nothing uses it yet: the cash gift endpoint is next.
- **Why:** Agreement section 11 (layer 1) requires an idempotency key on any endpoint that creates a record or starts a payment. A finance secretary who double-clicks Save would otherwise record the same cash gift twice, and money records are never deleted, only voided. It is built first, on its own, so every money endpoint of Sprint 4 can use it.
- **Files:** app/Http/Middleware/EnsureIdempotency.php, bootstrap/app.php, config/limits.php, docs/08-api.md, docs/13-decision-log.md (D-046 wording on who sees gifts), tests/Feature/Http/IdempotencyTest.php
- **Commit:** `d2f14d5` (2026-10-28)

#### feat(giving): add fund types with default funds for every church (2026-10-28)
- **What:** Built Sprint 4 story 4.2. GET /fund-types lists a church's giving categories in order (any signed-in role, paginated, optional active filter); POST, PUT and PATCH /fund-types add, rename, reorder or switch off a fund (church admin only, fund-types.manage). A name is unique inside a church ignoring capitals: a new validation rule (UniqueFundTypeName) says so as a 422, and a unique index on (church_id, lower(name)) holds it when two requests race. There is no delete; a fund is switched off and past gifts keep pointing at it. A new church gets seven default funds from config/giving.php when it registers, and the demo seeder gives them to the demo church. Recorded the order of Sprint 4 in D-046 (cash giving first; digital payments wait for the legal opinion and PayPerlez event names in D-018) and the rule that listing single gifts needs giving.record while reports need giving.summary.
- **Why:** docs/11 story 4.2, docs/04 Fund Types, docs/08 Fund Types and QA-10. The agreement asks for the simplest working slice first and database guarantees for natural keys (sections 3, 8, 11); Laravel's own unique rule is case-sensitive, so the browser-visible 422 and the case-insensitive index would have disagreed and a duplicate would have been a server error; a test caught that and the custom rule fixes it. Every other giving record points at a fund type, so it comes first.
- **Files:** app/Actions/FundTypes/, app/DTOs/FundTypes/, app/Http/Controllers/Api/V1/FundTypes/, app/Http/Requests/FundTypes/, app/Http/Requests/Concerns/DelegatesToPolicy.php, app/Http/Resources/FundType*.php, app/Policies/FundTypePolicy.php, app/Repositories/ (FundType interface and Eloquent), app/Rules/UniqueFundTypeName.php, app/Services/DefaultFundTypes.php, app/Actions/Onboarding/RegisterChurchAction.php, app/Providers/ (App, Repository), routes/api.php, config/giving.php, database/migrations (unique name index), database/seeders/DatabaseSeeder.php, docs/ (04 unchanged, 07, 08, 11, 13, openapi.json), and new Pest tests (feature, policy, onboarding, contract)
- **Commit:** `993aaa9` (2026-10-28)

#### test(attendance-ui): add a browser test for the QR check-in page (2026-10-28)
- **What:** Added a Playwright test: staff open check-in, click Show QR code, see the QR image and its address, and the address opened in a separate signed-out browser shows the member's check-in page for that service.
- **Why:** The QR screens were only covered by component tests; a real browser proves the public page works with no sign-in and the address in the QR code is right (QA-05 TC-05-09).
- **Files:** e2e/services.spec.ts
- **Commit:** `6549fd1` (2026-10-28)

#### feat(attendance): add WhatsApp CHECKIN through the Meta webhook (2026-10-28)
- **What:** Built Sprint 3 story 3.3. Meta posts to one platform-wide webhook (GET answers its verify check; POST is refused unless the X-Hub-Signature-256 HMAC of the raw body matches the app secret). A valid post is kept encrypted in webhook_logs, queued on notifications and answered 200 at once. A worker reads the text messages, and for each CHECKIN (any capitals, optional service code) finds the living member by phone across churches, picks the service (the code, or the one opened first in the member's church; a number in two churches with services open must send a code), records the attendance with method whatsapp and answers on WhatsApp with the wording from QA-05. Meta's message id is stored in the same transaction (new handled_whatsapp_messages table, unique), so repeats are ignored; the first message counts as agreeing to WhatsApp (flag plus consent record). Answers go through a WhatsAppGateway interface with a log driver and a Meta driver, in SendWhatsAppReplyJob behind a circuit breaker, with whatsapp:pause and whatsapp:resume. Added services.checkin_opened_at (first-opened service, TC-05-13), a TenantContext helper that sets and restores the church for background work, env:check rules for the Meta settings, a runbook, and docs 07, 08, 11, 13 (D-045), 14, 17, 18.
- **Why:** docs/11 story 3.3 and QA-05 TC-05-03 to TC-05-07, TC-05-10 and TC-05-13. The agreement requires webhooks that prove their sender and are idempotent (sections 10, 11), providers behind an interface with a circuit breaker and a manual switch (sections 15, 37), explicit tenant context in background work (section 34) and no personal data in logs (section 17). The Meta account is not approved yet, so the Meta driver is built from docs/14 and tested only against faked HTTP; the log driver and a fake gateway cover the rest.
- **Files:** app/Actions/WhatsApp/, app/Contracts/WhatsAppGateway.php, app/DTOs/WhatsApp/, app/Enums/CheckinReply.php, RecipientType.php, app/Exceptions/ (WhatsAppRejected, WhatsAppUnavailable), app/Http/Controllers/Api/V1/Webhooks/, app/Http/Middleware/VerifyMetaSignature.php, app/Http/Requests/Webhooks/, app/Jobs/ (ProcessWhatsAppWebhook, SendWhatsAppReply), app/Console/Commands/ (PauseWhatsApp, ResumeWhatsApp), app/Models/HandledWhatsappMessage.php, app/Models/Service.php, app/Providers/ (Provider, Repository, AppService), app/Repositories/ (webhook log, handled message, member, service), app/Services/ (TenantContext, WhatsApp/), app/Support/EnvironmentCheck.php, app/Actions/Services/SetServiceCheckinAction.php, 2 migrations, config/ (whatsapp, services, limits), bootstrap/app.php, routes/api.php, .env.example, docs/ (07, 08, 11, 13, 14, 17, 18, openapi.json, runbooks/), and new Pest tests
- **Commit:** `3054037` (2026-10-28)

#### feat(attendance-ui): add the QR check-in screens (2026-10-28)
- **What:** Added the full-screen QR page staff show in the sanctuary (/services/:id/qr, redraws the code as often as the server says, explains when check-in is closed) and the public page a member lands on after scanning (/checkin/:token: phone number, texted code, welcome; handles expired code, closed service and already checked in). Added a Show QR code button on the service page while check-in is open, the checkinApi and servicesApi.qr calls, the useServiceQr and useQrCheckIn hooks, and the qrcode.react and ajv-formats packages.
- **Why:** Story 3.11 and the member side of story 3.4 (docs/11): the QR backend was finished but nobody could use it. Tests cover both pages, the hook refresh timing, the API calls and the contract against the OpenAPI spec.
- **Files:** resources/js/pages/ServiceQrPage.tsx, QrCheckInPage.tsx (+tests), resources/js/hooks/useServiceQr.ts, useQrCheckIn.ts (+test), resources/js/services/api/checkin.ts (+test), services.ts, ServiceDetailPage.tsx, router/index.tsx, constants/routes.ts, queryKeys.ts, types/services.ts, test/fixtures.ts, test/contract.test.ts, package.json, package-lock.json
- **Commit:** `9fff07c` (2026-10-28)

#### feat(attendance): add QR code check-in on the backend
- **What:** Built the backend of Sprint 3 story 3.4. Staff who may check people in can ask for a fresh code to show as a QR code at the venue (only while the service is open); it lasts 2 minutes and the screen asks for a new one every minute. A member who scans it can see which service it is for, is texted a check-in code (the same answer for any phone number, and a text only to a living member of this church) and confirms with it to be recorded with method qr. Scanning twice is harmless. The texts are limited per phone and service and per IP address because they cost money, a code works for one phone and one service only, and the church is part of every cache key. Documented in docs/08 and D-044, with contract tests.
- **Why:** docs/02 Module 3 and QA-05 TC-05-09: the QR code proves the person is at the venue and the texted code proves the phone is theirs. The agreement requires cost-triggering endpoints to have their own rate limit, tenant-namespaced cache keys and no enumeration of members; each rule has a test that fails when it is removed.
- **Files:** `config/attendance.php`, `config/limits.php`, `app/Services/QrTokenService.php`, `app/Actions/Attendance/` (IssueQrCode, ShowQrService, RequestQrCheckinOtp, QrCheckIn), `app/DTOs/Attendance/`, `app/Http/Controllers/Api/V1/Attendance/`, `app/Http/Requests/Attendance/`, `app/Http/Resources/` (QrCode, QrService, QrCheckedIn), `app/Exceptions/` (InvalidQrCodeException, CheckinClosedException), `app/Repositories/` (member, service), `app/Providers/AppServiceProvider.php`, `routes/api.php`, `docs/08`, `docs/13`, `docs/openapi.json`, and new Pest tests
- **Commit:** `1b26247` (2026-10-28)

#### feat(attendance-ui): add the services pages, live check-in and manual check-in
- **What:** Built Sprint 3 stories 3.8, 3.9, 3.10 and 3.12 on the screen. A Services page lists services (filter by type, in pages, in the URL) with a New service dialog for staff who may create. A service page shows its check-in code, opens or closes check-in with one tap, and shows the headcount, which asks the server again every ten seconds while the service is open and is announced politely to screen readers. Staff who may edit can search members and tick several to mark them present together, and add a visitor with just a name; everyone can read the Attendance tab, a paged list with how and when each person arrived. The browser tests now sign in once and share it, and a new browser test runs a service end to end.
- **Why:** docs/11 Sprint 3 and the React addendum: server state in TanStack Query, forms in React Hook Form with Zod, filters and tabs in the URL, labels on every box, API calls only in the service layer, and a contract test that the screens' sample data matches the committed spec.
- **Files:** `resources/js/pages/` (ServicesPage, ServiceDetailPage), `resources/js/components/Services/`, `resources/js/hooks/` (useServices, useServiceAttendance, useServiceFilters), `resources/js/services/api/` (services, attendance), `resources/js/types/services.ts`, `resources/js/constants/` (enums, polling, queryKeys, routes), `resources/js/utils/formatClock.ts`, `resources/js/router/index.tsx`, `resources/js/components/layout/AppLayout.tsx`, `e2e/` and `playwright.config.ts`, `docs/11`, `tests/Feature/Operations/FrontendEnumsTest.php`, and new Vitest tests
- **Commit:** `0b68cb1` (2026-10-28)

#### feat(attendance): record attendance, capture visitors and report who came
- **What:** Built Sprint 3 stories 3.2, 3.5, 3.6, 3.7 and 3.13 on the backend. Staff who may edit members can check a list of members in to a service by hand (a repeat is counted as already checked in, never an error, and two requests at once leave one row); a closed service can still be added to. A visitor can be recorded with a name and optionally a phone number: a known phone makes it a return visit, and the same form twice counts once. Anyone signed in can see a service's headcount (everyone, members, visitors, each method, a whole-percent rate) and who came; visitors' names show only to church admin and pastor, and other roles see only members within their directory view. A member's attendance over 1 to 52 weeks, and a list of active members who missed the last 3 Sunday services (worked out when asked), are also in. Added a shared full-name field on members, a new link from a walk-in to their visitor record, and the contract tests for all the new endpoints.
- **Why:** docs/11 Sprint 3, docs/08 and QA-05/QA-10/QA-02. Every rule has a test that fails when the rule is removed (the absent-list filters, the insert that ignores a clash, the visitor hiding, the deceased rule), and a concurrency test proves the database lets only one of two simultaneous check-ins in.
- **Files:** `database/migrations/*_add_visitor_record_to_attendance_records_table.php`, `config/attendance.php`, `app/Services/AttendanceRecorder.php`, `app/Actions/Attendance/`, `app/DTOs/Attendance/`, `app/Http/Controllers/Api/V1/Attendance/`, `app/Http/Requests/Attendance/`, `app/Http/Resources/` (attendance, absent member, visitor), `app/Repositories/` (attendance, visitor, member), `app/Policies/ServicePolicy.php`, `app/Models/` (Member, AttendanceRecord, VisitorRecord, Church), `routes/api.php`, `docs/05`, `docs/07`, `docs/08`, `docs/13`, `docs/openapi.json`, and new Pest tests (feature, unit, concurrency, contract)
- **Commit:** `f3a17b7` (2026-10-28)

#### feat(attendance): add services with check-in codes and open or close check-in
- **What:** Built Sprint 3 story 3.1. Staff can list services (filters for dates, type and open state, in pages), open one, create one, change one and open or close it for check-in. The system makes each service's check-in code: 6 letters and numbers without look-alikes, unique across all churches so one shared WhatsApp number or QR code can tell which service is meant; the unique index in the database settles two codes made at the same moment. Everyone signed in can read services; the church admin and the pastor (members.edit) change them. An hourly command closes a service left open after its day. Recorded the whole Sprint 3 design as D-044.
- **Why:** docs/11 story 3.1, docs/08 and QA-05/QA-10/QA-02. Tests cover the permission matrix for all five roles, other churches' services (404 and empty list), the code rules and clashes, and a policy check that the HTTP tests cannot reach; the policy and the stale-service rules were shown to fail when removed.
- **Files:** `database/migrations/*_make_service_checkin_codes_unique_across_churches.php`, `config/attendance.php`, `app/Models/Service.php`, `app/Policies/ServicePolicy.php`, `app/Repositories/` (service), `app/Services/CheckinCodeGenerator.php`, `app/Actions/Services/`, `app/DTOs/Services/`, `app/Http/Controllers/Api/V1/Services/`, `app/Http/Requests/Services/`, `app/Http/Resources/` (ServiceResource, ServiceCollection), `app/Console/Commands/CloseStaleServices.php`, `app/Providers/`, `routes/api.php`, `routes/console.php`, `docs/07`, `docs/08`, `docs/13`, `docs/openapi.json`, and new Pest tests
- **Commit:** `71e52e0` (2026-10-28)

#### docs(incident): add incident response, nine runbooks and the decision record
- **What:** Wrote `docs/18-incident-response.md` (severity P1 to P4, roles, the four phases, post-mortem rules, data breach notice rules and a before-launch checklist) and nine runbooks in `docs/runbooks/`, each with symptom, contain, diagnose, resolve and verify: database connections, queue worker stopped, SMS provider down, payment provider errors, disk space, deployment rollback, data breach first response, restore from backup and a failed church purge. The alert table in docs/17 now links to them, the log-retention table lists the real commands, the backup section in docs/16 describes the built command, and D-043 records the decisions and what is still open.
- **Why:** The agreement (section 37) requires severity levels, runbooks committed to the repository for every expected failure, a post-mortem process and a breach procedure before launch. The runbooks are honest about what is unverified: nothing has been rehearsed because there is no staging, and the payment runbook applies from Sprint 4.
- **Files:** `docs/18-incident-response.md`, `docs/runbooks/` (README and nine runbooks), `docs/16-deployment-and-ops.md`, `docs/17-monitoring-and-alerting.md`, `docs/13-decision-log.md`, `docs/reference/post-mortems/TEMPLATE.md`
- **Commit:** `f5b2f69` (2026-10-28)

#### feat(backup): make the daily encrypted database backup
- **What:** Built `php artisan db:backup`, scheduled daily at 02:00 UTC and never twice at once. It dumps the database with pg_dump, encrypts the dump for a public backup key (so the server can write backups but not read them), streams it to the separate backup bucket, removes backups older than 30 days, and deletes the plain dump from the server afterwards even when something fails. The database password goes to pg_dump in its environment, not on the command line. It refuses to make an unencrypted backup, reports any failure to Sentry, and `env:check` now needs `BACKUP_GPG_RECIPIENT` outside development. Also ran it for real once against the development database: the file was unreadable with another key and held the same row counts as the live database after decrypting.
- **Why:** docs/05 and docs/16 promise a daily encrypted backup with 30 days kept, and the agreement requires a backup procedure that is tested, not assumed. A full restore into a new database could not be tried here (the development database account cannot create databases), so that stays on the pre-launch list.
- **Files:** `app/Console/Commands/BackupDatabase.php`, `app/Exceptions/BackupFailedException.php`, `config/backup.php`, `routes/console.php`, `app/Support/EnvironmentCheck.php`, `.env.example`, `tests/Feature/Operations/BackupDatabaseTest.php`, `tests/Feature/Operations/EnvironmentCheckTest.php`
- **Commit:** `bd602f1` (2026-10-28)

#### feat(resilience): add a circuit breaker and a pause switch for text messages
- **What:** Text messages now go through a circuit breaker. After 5 failures in 2 minutes the circuit opens and holds messages for a minute, then lets one test message through; if it works the circuit closes, if not it opens again. Held messages wait on the queue for up to 30 minutes and are not counted as failed attempts, and only three real failures give up on a message. A message the provider refuses for good does not count as the provider being down. `sms:pause` and `sms:resume` hold and release all sending by hand during an incident. Opening the circuit is logged as an error so it reaches Sentry.
- **Why:** The agreement (section 37) requires circuit breakers on external providers, so a provider outage fails fast instead of piling up thousands of retries that make recovery harder, and a safety switch that works without a deploy. Each safeguard in the job has a test that fails if it is removed.
- **Files:** `app/Services/CircuitBreaker.php`, `app/Console/Commands/PauseSms.php`, `app/Console/Commands/ResumeSms.php`, `app/Jobs/SendSmsJob.php`, `config/resilience.php`, `tests/Feature/Resilience/CircuitBreakerTest.php`, `tests/Feature/Auth/SendSmsJobTest.php`
- **Commit:** `bd9a4ca` (2026-10-28)

#### test(contract): check the API and the React app against the OpenAPI spec
- **What:** Added contract tests on both sides of the committed spec (docs/openapi.json). The backend test calls every endpoint the app uses for real (members, families, departments, sign-in, sign-up, export, erasure and more) and checks each answer against the schema the spec promises for that status. The React test checks the sample data the app is tested with against the same spec. Together they found two real mismatches, now fixed: the spec said a member without a photo had a text photo link in the directory list (it is empty, so the spec now says so), and the app's member type lacked two fields the API always sends (`status_changed_at`, `created_at`). Both tests were shown to fail when a required field is removed or a type is wrong.
- **Why:** The agreement (section 35) requires contract tests for consumer-facing endpoints: happy path shape, the 422 error shape and authentication. The existing drift test only proved the spec matched the code's annotations, not that real answers match the spec.
- **Files:** `tests/Feature/Contract/ApiContractTest.php`, `resources/js/test/contract.test.ts`, `resources/js/test/fixtures.ts`, `resources/js/types/members.ts`, `app/Http/Resources/MemberSummaryResource.php`, `docs/openapi.json`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`
- **Commit:** `f3aca44` (2026-10-28)

#### feat(retention): cancel a church and purge its personal data after 90 days
- **What:** Added church cancellation and the 90-day purge promised in docs/05. `church:cancel {slug}` starts a 90-day grace period (the church keeps working so it can export its data) and `church:reinstate {slug}` takes it back until the purge. A daily command (03:30 UTC) queues one purge job per church whose grace period is over. The purge erases every member the same way a single erasure does, removes staff, family, visitor and attendance-visitor details and contribution notes, deletes the church's messages and stored files, masks its whole change history, removes its contact and payout details, soft-deletes it and frees its web address. Money records stay for 7 years. The database part is one transaction and the file deletion can be repeated. Recorded as D-042.
- **Why:** docs/05 and agreement section 34 require a grace period, a data export, then a scheduled purge, with the purge enforced by a job and never by hand. Every purge step has a test that fails if the step is removed, and a second church's data is checked to be untouched.
- **Files:** `database/migrations/*_add_cancellation_to_churches_table.php`, `app/Models/Church.php`, `app/Actions/Churches/`, `app/DTOs/Churches/`, `app/Services/ChurchPurgeService.php`, `app/Repositories/` (church, church purge, audit log), `app/Jobs/PurgeChurchJob.php`, `app/Console/Commands/` (CancelChurch, ReinstateChurch, PurgeCancelledChurches), `app/Events/ChurchCancelled.php`, `app/Listeners/LogChurchCancelled.php`, `app/Exceptions/ChurchAlreadyPurgedException.php`, `config/retention.php`, `routes/console.php`, `docs/05`, `docs/13`, `tests/Feature/Churches/CancelAndPurgeChurchTest.php`
- **Commit:** `8c8d3fe` (2026-10-28)

#### feat(monitoring): report browser errors, work offline with Spotlight, and record the owner's decisions
- **What:** The React app now reports errors to the same monitor, but only when `VITE_SENTRY_DSN` is set, and the Sentry code loads on demand so the main bundle did not grow. Sentry 11 collects personal data unless told not to, so every collection switch is off: no user details, cookies, headers, request bodies, query strings, click text or local variables. A crashed screen now shows a plain 'Something went wrong' page that reports the error and never shows the error text. Added Spotlight (a local error screen that needs no account and no internet) and 1 and 2 second limits so a slow or unreachable monitor never holds up a page or a job; docs/17 explains offline use and self-hosting with GlitchTip. Recorded the owner's decisions: text messages stay on by default for a new member (WhatsApp off), and the one-time sign-in link, SMS delivery status and screen translation are deferred (DD-007 to DD-009).
- **Why:** The agreement requires errors in the browser to be monitored too, and an error monitor must never expose personal data or slow the app down. Sentry's newest version changed its privacy defaults, which was caught by reading its migration notes before using it.
- **Files:** `resources/js/lib/errorMonitoring.ts`, `resources/js/components/layout/RouteErrorPage.tsx`, `resources/js/router/index.tsx`, `resources/js/main.tsx`, `resources/js/vite-env.d.ts`, `config/sentry.php`, `.env.example`, `package.json`, `package-lock.json`, `docs/13-decision-log.md`, `docs/17-monitoring-and-alerting.md`, and new Vitest tests
- **Commit:** `c858716` (2026-10-28)

#### feat(privacy): keep a consent history for text and WhatsApp messages
- **What:** Added a `consents` table. Whenever a member is added or their text/WhatsApp choice changes, an agreement is recorded with the time, the privacy policy version (`config/privacy.php`) and the staff member who recorded it; switching a choice off marks the agreement withdrawn, and switching it on again adds a new row, so the whole history is kept. The database allows only one open agreement per member and purpose. Erasing a member withdraws everything. The member's data export now lists the consent history. Recorded as D-041, which also flags an open point: the text-message choice is on by default, which is not valid consent for broadcasts.
- **Why:** The agreement (section 36) requires consent to be recorded with a timestamp, purpose and policy version, with withdrawal support and effect from the moment it is withdrawn. Each listener method has a test that fails if the method is removed.
- **Files:** `database/migrations/2026_10_28_162256_create_consents_table.php`, `app/Models/Consent.php`, `app/Models/Member.php`, `app/Enums/ConsentPurpose.php`, `app/Repositories/` (ConsentRepositoryInterface, EloquentConsentRepository, member repository), `app/Providers/RepositoryServiceProvider.php`, `app/Services/ConsentRecorder.php`, `app/Listeners/RecordMemberConsent.php`, `app/Http/Resources/MemberDataExportResource.php`, `config/privacy.php`, `database/factories/ConsentFactory.php`, `docs/07`, `docs/08`, `docs/13`, `docs/openapi.json`, and new Pest tests
- **Commit:** `77aad0c` (2026-10-28)

#### test(e2e): add Playwright browser tests for sign-up, sign-in and members
- **What:** Installed Playwright and wrote four tests that drive the real Chrome on this machine against the running local sites: a church signs up with a texted code and gets its own address; mistakes at sign-up (a platform name, a wrong code) are refused beside their boxes; an admin signs in with a texted code, adds a member in three steps and finds them by name and by local phone; and a visitor who is not signed in is sent to the login page. The tests read sign-in codes from the log, so they need the log SMS driver and a running `critical` queue worker. `npm run e2e` runs them. They are not in CI yet because there is no staging copy to run against.
- **Why:** The React addendum requires end-to-end tests of the critical user flows, and the earlier work had only unit and component tests. The first run found two real problems: the pages had no main heading, and wrong codes were logged as errors (both fixed in their own commits).
- **Files:** `playwright.config.ts`, `e2e/` (specs, helpers, tsconfig), `package.json`, `package-lock.json`, `.gitignore`, `CLAUDE.md`
- **Commit:** `b9cccd6` (2026-10-28)

#### fix(a11y): give the sign-in and sign-up pages a real main heading
- **What:** The titles of the sign-in and start-a-church cards are now `h1` headings. They were plain `div`s, so a screen reader found no heading on either page. The sign-up page has a test for it.
- **Why:** The agreement (section 32) requires semantic HTML and a logical heading order on every page. Found by the browser test, which looks for the heading the way a screen reader does.
- **Files:** `resources/js/pages/StartChurchPage.tsx`, `resources/js/pages/LoginPage.tsx`, `resources/js/pages/StartChurchPage.test.tsx`
- **Commit:** `1b7be52` (2026-10-28)

#### fix(errors): stop logging and reporting mistakes the user made
- **What:** A wrong sign-in code, a locked-out phone, a taken web address, a broken family rule and a missing member number prefix still get their 4xx answer, but are no longer written to the log as errors or sent to Sentry. Real faults, such as the SMS provider refusing us, are still reported.
- **Why:** A wrong code is the user's mistake, not ours. Logged as errors they would bury real faults and flood the error monitor, which was found while running the browser tests (each wrong code wrote an error to laravel.log).
- **Files:** `bootstrap/app.php`, `tests/Feature/Operations/ExceptionReportingTest.php`
- **Commit:** `f2c55c0` (2026-10-28)

#### feat(monitoring): report errors to Sentry without personal data
- **What:** Installed the Sentry Laravel SDK. Every unhandled exception is reported once `SENTRY_LARAVEL_DSN` is set, and the app now refuses to start outside development without it (`env:check`). Each signed-in request adds the staff id, church id and role to the error, never a name, phone or email. Request bodies and cookies are not sent, personal data collection is off and SQL values are not recorded. `SENTRY_RELEASE` ties errors to a deploy. With the DSN empty (development) nothing is sent. Updated docs/17 and recorded D-040.
- **Why:** The agreement (sections 26 and 27) requires a dedicated error monitor in production. The code is done now; only a Sentry project and its DSN are needed to turn it on. The React app is not instrumented yet.
- **Files:** `composer.json`, `composer.lock`, `config/sentry.php`, `bootstrap/app.php`, `app/Http/Middleware/AddErrorMonitoringContext.php`, `routes/api.php`, `app/Support/EnvironmentCheck.php`, `.env.example`, `docs/17-monitoring-and-alerting.md`, `docs/13-decision-log.md`, `tests/Feature/Operations/ErrorMonitoringTest.php`, `tests/Feature/Operations/EnvironmentCheckTest.php`
- **Commit:** `39de8e8` (2026-10-28)

#### feat(privacy-ui): add download-their-data and erase buttons to the member profile
- **What:** The member profile now has a Personal data section. Staff with the export permission see 'Download their data', which saves the member's data as a .json file named after their member number. The church admin (erase permission) also sees 'Erase personal data', which opens a dialog that says what is removed and what stays, asks for a reason (kept in the change log, no personal details), and on success returns to the directory. Server messages show beside the reason box.
- **Why:** The frontend half of the export and erasure endpoints (D-039). Without the buttons a church admin could not use them. The React addendum requires a test for every reusable component and service function, and destructive actions need a confirmation step.
- **Files:** `resources/js/components/Members/` (MemberPrivacyActions, EraseMemberDialog), `resources/js/pages/MemberDetailPage.tsx`, `resources/js/utils/downloadJson.ts`, `resources/js/hooks/useMemberMutations.ts`, `resources/js/services/api/members.ts`, `resources/js/constants/permissions.ts`, and new Vitest tests
- **Commit:** `6b85b93` (2026-10-28)

#### test(members-ui): stop the form tests failing at random on a busy machine
- **What:** Three changes to the test setup. Each test may take up to 15 seconds (was 5), `findBy` and `waitFor` wait up to 5 seconds (was 1), and the two form tests that clicked Next twice now wait for 'Step 2 of 3' before the second click, so they no longer click the first step's button again. No assertion was changed or removed.
- **Why:** The member form tests failed at random in full runs (a timeout, a label not found, a second click on the wrong step), most often when the machine was busy. They passed alone and failed in about 1 run in 3, so a real failure could not be told from noise. Eight full runs in a row on a machine with load 18 now pass.
- **Files:** `vite.config.ts`, `resources/js/test/setup.ts`, `resources/js/pages/MemberFormPage.test.tsx`
- **Commit:** `d99cbe4` (2026-10-28)

#### feat(privacy): export and erase a member's personal data
- **What:** Added two admin endpoints. `GET /members/{id}/data-export` returns everything held about a member as JSON and is recorded in their change log. `POST /members/{id}/erasure` (new permission `members.erase`, church admin only, with a required reason) removes the member's personal details everywhere: name, phone, email, address, ID, birth date and the rest are emptied, the photo file is deleted, the phone and text are stripped from messages sent to them, milestone notes are cleared, a family they head loses its head, and the values in their change history become `[deleted]` while every line and field name stays. The record itself is archived and soft-deleted so giving and attendance records keep working, and its phone number is free to reuse. Recorded as D-039.
- **Why:** The agreement (section 36) and docs/05 require the right to access and the right to erasure, done by anonymising instead of hard-deleting, with the audit trail masked rather than removed. Each erasure step has a test that fails if the step is removed.
- **Files:** `app/Services/MemberErasureService.php`, `app/Services/MemberDataExporter.php`, `app/Actions/Members/` (EraseMemberAction, ExportMemberDataAction), `app/DTOs/Members/`, `app/Http/Controllers/Api/V1/Members/` (EraseMemberController, ExportMemberDataController), `app/Http/Requests/Members/`, `app/Http/Resources/MemberDataExportResource.php`, `app/Policies/MemberPolicy.php`, `app/Events/` (MemberErased, MemberDataExported), `app/Listeners/RecordMemberAudit.php`, `app/Enums/` (StaffPermission, AuditEvent), repositories (member, family, milestone, message log, audit log), `app/Models/AuditLog.php`, `config/privacy.php`, `routes/api.php`, `docs/openapi.json`, `docs/02`, `docs/05`, `docs/08`, `docs/13`, and new Pest tests (feature privacy API test; unit tests of both actions)
- **Commit:** `eec77d8` (2026-10-28)

#### fix(retention): satisfy static analysis in the prune queries
- **What:** The two prune queries now start from the model's own class, which gives PHPStan the exact type it expects.
- **Why:** The retention commit was made while PHPStan still reported 2 errors, because the check result was not looked at before committing. Every check now has to pass before a commit (the lesson from the earlier drift-test slip).
- **Files:** `app/Models/MessageLog.php`, `app/Models/WebhookLog.php`
- **Commit:** `997d51a` (2026-10-28)

#### feat(retention): delete old message logs and webhook payloads every day
- **What:** Message logs older than 12 months and webhook payloads older than 90 days are now deleted by a daily job (Laravel's `model:prune`, scheduled at 03:00 UTC on one server). The periods live in `config/retention.php`. Money records and audit logs are not touched.
- **Why:** docs/05 and docs/07 promise these retention periods, and the agreement requires retention to be enforced by an automated job, never by hand. The job looks across all churches on purpose (it is not a church's request).
- **Files:** `config/retention.php`, `app/Models/MessageLog.php`, `app/Models/WebhookLog.php`, `routes/console.php`, `tests/Feature/Operations/RetentionTest.php`
- **Commit:** `6516603` (2026-10-28)

#### feat(sms): send text messages through PayPerlez
- **What:** Added the PayPerlez SMS adapter behind the existing provider interface. The interface now takes the whole message and a retry key and returns a receipt. A message gets one retry key when its job is created and keeps it across retries, so a retry after a timeout never sends twice. Rate limits, server errors and lost connections retry; any other refusal fails the job at once and is logged with the provider's error code and request id only. The message log keeps PayPerlez's reference and the safe parts of its reply (never the phone or text). The app refuses to start with this provider unless the API key and an approved sender id are set, and `env:check` says so too. Wrote the verified request, reply and error details into docs/14 and recorded D-038 (we keep making our own sign-in codes).
- **Why:** PayPerlez is the chosen SMS provider (D-020), and real sign-in and sign-up codes cannot be delivered on staging or production until its adapter exists. The agreement requires every provider behind an interface, safe retries, normalised errors and a DB record of outbound calls.
- **Files:** `app/Contracts/SmsProvider.php`, `app/Services/Sms/` (PayperlezSmsProvider, LogSmsProvider), `app/DTOs/SmsReceipt.php`, `app/Exceptions/SmsRejectedException.php`, `app/Jobs/SendSmsJob.php`, `app/Providers/ProviderServiceProvider.php`, `app/Support/EnvironmentCheck.php`, `config/services.php`, `.env.example`, `docs/14-integrations.md`, `docs/13-decision-log.md`, `tests/Feature/Sms/PayperlezSmsProviderTest.php`, `tests/Feature/Auth/SendSmsJobTest.php`, `tests/Feature/Operations/EnvironmentCheckTest.php`
- **Commit:** `00c4f88` (2026-10-28)

#### docs(claude): tell people to give PHPStan 1 GB
- **What:** The project CLAUDE.md command list now shows `phpstan analyse --memory-limit=1G`. CI and `composer lint:check` already passed the flag; `composer audit` was re-run and is clean (no advisories, live network).
- **Why:** Running PHPStan with no flag crashed its parallel workers at the default 128 MB limit, which looked like a CI problem but was only a local command.
- **Files:** `CLAUDE.md`
- **Commit:** `04a2179` (2026-10-28)

#### feat(onboarding-ui): add the start-a-church page
- **What:** Built the page of story 2.1 at `/start`. One form takes the church name, an optional web address, denomination and town, the member number letters, the admin's name and phone; "Text me a code" sends the code and reveals the code box; "Start my church" creates it. Messages from the server appear next to the box they belong to (a taken address on the address box, a wrong code on the code box). When it works the page shows the church's address, a button to sign in there and a suggestion to add a second admin. On the platform's own address the home page now goes to this page instead of the sign-in. Added a check that the limits repeated in the browser match the server config. Marked story 2.1 done in the roadmap.
- **Why:** Sprint 2 story 2.1 (the screen half). The React addendum requires React Hook Form with Zod, a label on every input, server errors on their fields and tests for every service function and reusable part; the agreement requires placeholders to be extra to visible labels, not instead of them.
- **Files:** `resources/js/pages/StartChurchPage.tsx`, `resources/js/components/Onboarding/` (StartChurchForm, ChurchStarted, startChurchSchema), `resources/js/components/layout/HomeRedirect.tsx`, `resources/js/hooks/useStartChurch.ts`, `resources/js/services/api/onboarding.ts`, `resources/js/types/onboarding.ts`, `resources/js/constants/` (onboarding, routes), `resources/js/lib/platformHost.ts`, `resources/js/router/index.tsx`, `resources/js/vite-env.d.ts`, `.env.example` (VITE_TENANT_BASE_DOMAIN), `tests/Feature/Operations/FrontendLimitsTest.php`, `docs/11-roadmap-and-sprints.md`, and 5 new Vitest test files
- **Commit:** `a6c161a` (2026-10-28)

#### feat(onboarding): let a new church start itself
- **What:** Built the backend of story 2.1. Two public calls on the platform's own host: one texts a sign-up code to the phone of the person starting a church, the other creates the church (plan starter) and its first church-admin together, only if the code is right. The web address is typed or built from the church name; a taken one returns free alternatives and does not use up the code; names kept for the platform (www, api, admin, docs and others) are refused; a deleted church's address stays taken. The member number prefix is asked here. Sign-up codes reuse the sign-in code rules (hashed, 10 minutes, 5 tries, 15-minute lockout) under their own scope, and are limited per phone and per IP address. The shared code steps were split out of `OtpService` into `issue` and `check`. The platform host now serves the app page without needing a church. The code's text message is logged with no church, so `message_logs.church_id` is nullable (the down step deletes those rows). Recorded as D-037, with the QA-03 differences written into QA-03.
- **Why:** Sprint 2 story 2.1, and nobody can use the system until a church can exist. Proving the phone first means no half-made churches and no squatting on addresses. The agreement requires atomic provisioning, a separate rate limit on cost-triggering calls, a committed OpenAPI spec and tests for every endpoint.
- **Files:** `app/Actions/Onboarding/`, `app/DTOs/Onboarding/`, `app/Http/Controllers/Api/V1/Onboarding/`, `app/Http/Requests/Onboarding/`, `app/Http/Resources/ChurchRegisteredResource.php`, `app/Services/` (ChurchSlugGenerator, OnboardingOtpService, OtpService), `app/Exceptions/ChurchSlugTakenException.php`, `app/Events/ChurchRegistered.php`, `app/Listeners/LogChurchRegistered.php`, `app/Repositories/` (church and user: slug check, create), `app/DTOs/OutboundSms.php`, `app/Enums/RecipientType.php`, `app/Providers/AppServiceProvider.php`, `routes/api.php`, `routes/web.php`, `config/onboarding.php`, `config/limits.php`, `database/migrations/2026_10_28_141726_allow_platform_message_logs_on_message_logs_table.php`, `docs/openapi.json`, `docs/07-data-model.md`, `docs/08-api.md`, `docs/13-decision-log.md`, `docs/qa/QA-03-onboarding.md`, and new Pest tests (feature: request code and register church; unit: slug generator and register action; the SMS job test)
- **Commit:** `5558023` (2026-10-28)

#### docs(roadmap): bring the current sprint up to date
- **What:** The "Current Sprint" section of the roadmap said Sprint 1 with no dates. It now says Sprint 2, lists which stories are done, names the one still open (2.1 church onboarding) and records which Sprint 1 ops stories wait on accounts and servers. `composer audit` was re-run: no advisories (Packagist timed out, so it used the local cache).
- **Why:** The agreement requires the current sprint to be checked at session start, so it has to be true.
- **Files:** `docs/11-roadmap-and-sprints.md`
- **Commit:** `41ddeac` (2026-10-28)

#### refactor(members): offer male and female only for gender
- **What:** Removed "Other" from the gender list on both sides: the PHP `Gender` enum, the frontend `GENDERS` list, the data model doc and the committed OpenAPI spec. Added a test that the API rejects `gender: other`. The drift test that compares the two lists needed no change; it passes with both lists reduced. The unfinished edits that added a Visitor membership type and webp/avif photo types were reverted, because the server does not support them (a visitor is a separate record, docs/03; photos are jpg or png, D-032).
- **Why:** Product decision from the project owner: gender is male or female. No stored member had "other", and the column is a plain string, so no migration is needed.
- **Files:** `app/Enums/Gender.php`, `resources/js/constants/enums.ts`, `resources/js/constants/photo.ts` (reverted), `docs/07-data-model.md`, `docs/openapi.json`, `tests/Feature/Members/MemberApiTest.php`
- **Commit:** `c4da7ba` (2026-10-28)

#### fix(members-ui): move the select caret off the border and add placeholders
- **What:** Drop-downs now hide the browser's own arrow and draw a chevron 12px in from the right edge, with room reserved so long labels do not run under it. Added example placeholders to the member form's text boxes that had none (names, second phone, email, home address, occupation, tribe, ID number) and to the milestone and family notes boxes. Labels stay visible above every box. Added tests for the select field.
- **Why:** The default arrow sat against the border and looked cramped. Placeholders show the expected format. The React addendum requires a test for every reusable component, and the agreement requires a visible label, not only a placeholder.
- **Files:** `resources/js/components/form/SelectField.tsx`, `resources/js/components/form/SelectField.test.tsx`, `resources/js/components/Members/` (IdentityFields, ContactFields, ChurchFields, AddMilestoneDialog), `resources/js/components/Families/FamilyFormDialog.tsx`
- **Commit:** `ef5d942` (2026-10-28)

#### fix(spa): serve stored files instead of the React shell
- **What:** The React catch-all route answered `/storage/...`, so a member photo's signed link returned the app's HTML page instead of the image. `storage` is now one of the paths the catch-all leaves alone. Added a test that a stored-file path reaches the file route (it is refused without a signature) rather than the shell.
- **Why:** An uploaded photo was saved but could not be shown. Found by uploading a photo to the local API and following the link it returned (200 `text/html` instead of `image/jpeg`). The test fails without the fix.
- **Files:** `routes/web.php`, `tests/Feature/Operations/SpaShellTest.php`
- **Commit:** `e7aac9c` (2026-10-28)

#### feat(members-ui): add the family list and family view pages
- **What:** Built story 2.13. A Families page lists the church's households with their member counts, searchable by name, paged, with the search and page in the URL, and a "New family" dialog for staff who may edit (it opens the new family afterwards). A family page shows the name, notes and members with the head first, each one tap from their profile, and staff who may edit can rename it. Added a Families item to the navigation. The member form's family picker now reads every page of families, so a church with more than 100 families is not cut off. The search box is now one shared component used by the member and family lists. Added direct tests of every API service function.
- **Why:** Sprint 2 story 2.13, and a family cannot be used from the member form unless it can be created and reached somewhere. The React addendum requires every API service function to be covered by tests and filters and pages to live in the URL.
- **Files:** `resources/js/pages/FamiliesPage.tsx`, `resources/js/pages/FamilyDetailPage.tsx`, `resources/js/components/Families/` (FamilyFormDialog, FamilyListItem, FamilyMemberList), `resources/js/components/ui/SearchBox.tsx`, `resources/js/components/Members/MemberFilterBar.tsx`, `resources/js/components/Members/ChurchFields.tsx`, `resources/js/hooks/useFamilyFilters.ts`, `resources/js/hooks/useFamilies.ts`, `resources/js/services/api/families.ts`, `resources/js/constants/queryKeys.ts`, `resources/js/router/index.tsx`, `resources/js/components/layout/AppLayout.tsx`, and 6 new Vitest test files
- **Commit:** `c99b7c6` (2026-10-28)

#### feat(members-ui): add the add and edit member form
- **What:** Built story 2.12. One screen at `/members/new` and `/members/:id/edit` adds or edits a member in three short steps (who they are, how to reach them, church life). Each step is checked before moving on, Enter moves to the next step and only the last step saves, the step is kept in the URL, and a message from the server appears beside its box and takes the user back to the step that holds it. The save button is disabled while saving. Departments and messaging choices are native tick boxes, the family picker asks for a role once a family is chosen, and the national ID is only sent when typed. A new member chooses a starting status (prospect or active); editing leaves status to the profile. Staff without the edit permission and records of deceased members get a plain message. The phone rule is now shared with the login page, and the date helper with the milestone dialog.
- **Why:** Sprint 2 story 2.12 and QA-04 TC-04-01 to TC-04-03. The React addendum requires React Hook Form with Zod, a label on every input, server errors mapped back to fields, and `isSubmitting`-style double-submit protection; the agreement requires a mobile-friendly form.
- **Files:** `resources/js/pages/MemberFormPage.tsx`, `resources/js/components/Members/` (MemberForm, memberFormSchema, IdentityFields, ContactFields, ChurchFields), `resources/js/components/form/CheckboxField.tsx`, `resources/js/components/ui/StepIndicator.tsx`, `resources/js/hooks/useUrlStep.ts`, `resources/js/hooks/useMembers.ts`, `resources/js/constants/validation.ts`, `resources/js/utils/today.ts`, `resources/js/types/members.ts`, `resources/js/pages/LoginPage.tsx`, `resources/js/components/Members/AddMilestoneDialog.tsx`, `resources/js/router/index.tsx`, and 3 new Vitest test files
- **Commit:** `5009208` (2026-10-28)

#### test(members-ui): fix the leaving-status drift test
- **What:** The test that compares the frontend `LEAVING_STATUSES` list with `MemberStatus::leavesActiveLife()` compared array keys as well as values; it now compares values only.
- **Why:** It was committed in the profile page commit while failing, because the check result was not looked at before committing. The suite is green again.
- **Files:** `tests/Feature/Operations/FrontendEnumsTest.php`
- **Commit:** `a998c85` (2026-10-28)

#### feat(members-ui): add the member profile page
- **What:** Built story 2.11. A member's page has a header (photo, name, number, status) and three tabs: Profile (contact, personal, church life, family link, departments, milestones), Giving (this month, this year and all time as the API formatted them) and Attendance (last 4, 8 and 12 weeks). The selected tab is kept in the URL. Staff who may edit can change the photo (JPG or PNG under 5 MB, checked before upload), change the status with an optional reason, and add milestones. Moving someone out of active life asks "Are you sure?" first and is offered only to staff with the deactivate permission. A deceased member's record is read-only. Server validation messages appear next to their fields. Also added a timezone-safe date formatter, a full-name helper, a shared field-error helper (moved out of the login page), and a test that checks the "leaving" status list against the PHP enum.
- **Why:** Sprint 2 story 2.11 and QA-04 (lifecycle changes, photo, milestones, no timezone shift on dates). The agreement requires confirmation for destructive actions, errors shown on the field, accessible labels, and no money arithmetic in the browser.
- **Files:** `resources/js/pages/MemberDetailPage.tsx`, `resources/js/components/Members/` (MemberHeader, MemberProfileTab, MemberGivingTab, MemberAttendanceTab, ChangeStatusDialog, AddMilestoneDialog, MilestoneList, PhotoUploadButton), `resources/js/components/ui/DetailList.tsx`, `resources/js/hooks/useUrlTab.ts`, `resources/js/lib/showFieldErrors.ts`, `resources/js/utils/` (formatDate, fullNameOf), `resources/js/constants/` (photo, enums), `resources/js/pages/LoginPage.tsx`, `resources/js/router/index.tsx`, `tests/Feature/Operations/FrontendEnumsTest.php`, and 5 new Vitest test files
- **Commit:** `0a89ce5` (2026-10-28)

#### feat(members-ui): add the member directory page
- **What:** Built story 2.10, the member directory. It lists members phone-first with search, status, gender and department filters, loading, empty and error states, and paging. Filters and the page live in the URL. "Add member" shows only for staff who may edit members. Added the shared pieces it needs (page header, status badge, empty state, pagination, confirm dialog, money display, error notice, labelled form fields), typed API services for members, families and departments with their query and mutation hooks, mirrors of the backend enums and permissions, the shadcn parts it uses, toasts, a `Members` nav item and the route. Added a production bundle budget that fails the build above 180 kB gzipped, and a backend test that fails if the frontend enum lists drift from the PHP enums.
- **Why:** Sprint 2 needs the church to manage its people on a phone. The React addendum asks for URL state for filters, TanStack Query for server data, React Hook Form with Zod for forms, no API calls in components, and a bundle budget.
- **Files:** `resources/js/pages/MembersPage.tsx`, `resources/js/components/Members/` (5), `resources/js/components/ui/` (shadcn parts and 7 shared pieces), `resources/js/components/form/` (4), `resources/js/hooks/` (6), `resources/js/services/api/` (3), `resources/js/constants/` (enums, permissions, query keys, routes), `resources/js/types/members.ts`, `resources/js/utils/initialsOf.ts`, `resources/js/test/` (render and fixtures), `resources/js/router/index.tsx`, `resources/js/main.tsx`, `vite.config.ts`, `package.json` (adds `sonner`), `tests/Feature/Operations/FrontendEnumsTest.php`, `ARCHITECTURE.md`, `docs/13-decision-log.md` (D-036), and 14 new Vitest test files
- **Commit:** `ae9b84c` (2026-10-28)

#### fix(members): find members by local phone numbers and say plainly when a phone is taken
- **What:** Directory search now also matches a typed phone number written as `0201234567`, `233201234567` or `+233201234567` against the stored international number. The duplicate-phone error now reads "A member with this phone number already exists." The tests pin `TENANT_BASE_DOMAIN=localhost` in `phpunit.xml`.
- **Why:** QA-04 TC-04-08 requires searching by the local phone format, and TC-04-02 gives the exact duplicate-phone message. The tenant domain was pinned because setting it to `church.test` in a developer's `.env` for Valet made every test host return 404: tests must not depend on a local `.env`.
- **Files:** `app/Models/Member.php`, `app/Http/Requests/Members/MemberInputRequest.php`, `phpunit.xml`, `tests/Feature/Members/MemberApiTest.php`
- **Commit:** `6d66265` (2026-10-28)

#### refactor(errors): use named exceptions and match the addendum's test layout
- **What:** Domain rules now throw named exceptions instead of the generic validation or runtime ones: `FamilyHeadAlreadyTakenException`, `FamilyRoleNeedsFamilyException` and `MemberAlreadyInFamilyException` (all 422 with the field named, through the shared `FieldRuleException`), `PhotoNotStoredException`, `SmsProviderUnavailableException` and `MisconfiguredEnvironmentException`. Member feature tests now sign in with Sanctum's `actingAs`. `PhoneNumberTest` moved to `tests/Unit/ValueObjects`, and unit tests were added for the `MemberStatus` and `EducationalLevel` enums and the `Member` and `Church` models.
- **Why:** Section 17 of the agreement asks for named custom exceptions for domain errors; the Laravel addendum says authenticated tests use `actingAs` and sets the unit-test folders (`Actions`, `Services`, `Models`, `Enums`, `ValueObjects`).
- **Files:** `app/Exceptions/` (7 new files), `app/Services/MemberFamilyService.php`, `app/Services/MemberPhotoService.php`, `app/Actions/Families/CreateFamilyAction.php`, `app/Providers/ProviderServiceProvider.php`, `app/Providers/AppServiceProvider.php`, `tests/Feature/Members/` (5 files), `tests/Concurrency/ContestedSlotsTest.php`, `tests/Unit/Actions/Families/CreateFamilyActionTest.php`, `tests/Unit/Services/MemberFamilyServiceTest.php`, `tests/Unit/Enums/` (2 new), `tests/Unit/Models/` (2 new), `tests/Unit/ValueObjects/PhoneNumberTest.php`
- **Commit:** `b1877a2` (2026-10-28)

#### feat(security): add the security headers middleware
- **What:** Added the `SecurityHeaders` middleware, registered globally, which sets `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection` and `Referrer-Policy` on every response and `Strict-Transport-Security` on HTTPS requests. The header list lives in `config/security.php`. Updated `docs/15-security.md` to point at the real code.
- **Why:** `docs/15` specified these headers and the Laravel addendum checklist asks "Security headers set in middleware?", but the middleware had never been written.
- **Files:** `app/Http/Middleware/SecurityHeaders.php`, `config/security.php`, `bootstrap/app.php`, `docs/15-security.md`, `tests/Feature/Operations/SecurityHeadersTest.php`
- **Commit:** `90fe26d` (2026-10-28)

#### refactor(code): move fixed values into config and simplify tangled logic
- **What:** Moved the rate limits, page sizes (default 25, maximum 100), photo size, quality, limits and link time, and the member number width into `config/limits.php` and `config/members.php`. The phone-number pattern is written once (`PhoneNumber::E164_PATTERN`). Raw HTTP status numbers became the framework's `Response::HTTP_*` constants. The "default directory" and "leaves active life" status lists became methods on `MemberStatus`. The member visibility scope now starts from "nobody" and opens up per role, and the audit listener has one short method per event instead of a `match(true)`.
- **Why:** The new project rule: no hard-coded values, simple and straightforward logic, framework before language. Each fixed value now lives in one place and can be changed without touching code.
- **Files:** `config/limits.php`, `config/members.php`, `app/Providers/AppServiceProvider.php`, `app/DTOs/Members/ListMembersDTO.php`, `app/DTOs/Families/ListFamiliesDTO.php`, `app/DTOs/Departments/ListDepartmentsDTO.php`, `app/Http/Requests/` (List, Upload photo, Auth and Member input requests), `app/ValueObjects/PhoneNumber.php`, `app/Http/Middleware/` (3 files), `app/Services/` (photo, member number, health check), `app/Enums/MemberStatus.php`, `app/Policies/MemberPolicy.php`, `app/Repositories/EloquentMemberRepository.php`, `app/Models/Member.php`, `app/Listeners/RecordMemberAudit.php`, `app/Exceptions/` (3 files), `app/Http/Controllers/` (6 files), and the unit tests that build the list DTOs and call the listener
- **Commit:** `01514dd` (2026-10-28)

#### feat(api): add the OpenAPI spec, docs route and the simplicity rule
- **What:** Added `dedoc/scramble`, generated and committed the OpenAPI 3.1 spec at `docs/openapi.json`, served interactive docs at `/docs/api`, and added a test and a CI step that fail when the spec is out of date, has an untyped field, or misses an endpoint. Typed the fields Scramble could not work out. Added the rule "keep code simple, framework first, no hard-coding, clear comments" to the project and Universal `CLAUDE.md`. Gave the test run 512 MB of memory. Documented the changelog format below.
- **Why:** Section 35 of the agreement requires a committed spec that CI checks and docs that are not public in production. The docs path also had to be kept out of the React catch-all route. The rule and the changelog format were requested by the Product Owner. The full test run had reached PHP's 128 MB limit.
- **Files:** `composer.json`, `composer.lock`, `config/scramble.php`, `docs/openapi.json`, `docs/08-api.md`, `docs/10-definition-of-done.md`, `docs/13-decision-log.md` (D-035), `.github/workflows/ci.yml`, `routes/web.php`, `phpunit.xml`, `CLAUDE.md`, `Universal CLAUDE.md`, `app/Providers/AppServiceProvider.php`, `app/DTOs/Auth/OtpChallenge.php`, `app/DTOs/Members/GivingSummary.php`, `app/Services/MemberSummaries.php`, the Resources, Requests and Controllers that needed a type or response annotation (`app/Http/Resources/` ×5, `app/Http/Requests/` ×4, `app/Http/Controllers/` ×3), `tests/Feature/Operations/ApiDocsTest.php`, `tests/Unit/Services/MemberSummariesTest.php`
- **Commit:** `e059f86` (2026-10-28)

#### refactor(auth): replace role and permission strings with enums
- **What:** Replace role and permission strings with enums.
- **Why:** The agreement forbids magic strings, so roles and permissions became enums.
- **Files:** `CHANGELOG.md`, `app/Actions/Members/` (4 files), `app/Enums/StaffPermission.php`, `app/Enums/StaffRole.php`, `app/Http/Resources/MemberResource.php`, `app/Models/Member.php`, `app/Policies/DepartmentPolicy.php`, `app/Policies/FamilyPolicy.php`, `app/Policies/MemberPolicy.php`, `database/seeders/DatabaseSeeder.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `tests/Unit/Enums/StaffRoleTest.php` — 15 files in total
- **Commit:** `31ec57d` (2026-10-28)

#### test(members): add concurrency tests, inverse relationships and factory use
- **What:** Add concurrency tests, inverse relationships and factory use.
- **Why:** The agreement requires a concurrency test for contested slots, relationships defined on both sides, and factories for test data.
- **Files:** `CHANGELOG.md`, `app/Models/Department.php`, `app/Models/FundType.php`, `app/Models/Member.php`, `app/Models/Service.php`, `app/Models/User.php`, `phpunit.xml`, `tests/Concurrency/ContestedSlotsTest.php`, `tests/Feature/Database/RelationshipsTest.php`, `tests/Feature/Members/` (3 files), `tests/Pest.php` — 13 files in total
- **Commit:** `c338d15` (2026-10-28)

#### docs(code): add a description docblock to every class and method
- **What:** Add a description docblock to every class and method.
- **Why:** The agreement requires a plain-language docblock on every class and method.
- **Files:** `app/Actions/` (22 files), `app/Console/` (2 files), `app/Contracts/SmsProvider.php`, `app/DTOs/` (27 files), `app/Events/` (4 files), `app/Exceptions/` (3 files), `app/Http/` (58 files), `app/Jobs/SendSmsJob.php`, `app/Listeners/RecordMemberAudit.php`, `app/Models/` (18 files), `app/Observers/UserObserver.php`, `app/Policies/` (3 files), `app/Providers/` (3 files), `app/Repositories/` (20 files), `app/Scopes/ChurchScope.php`, `app/Services/` (9 files), `app/Support/EnvironmentCheck.php`, `app/Traits/BelongsToChurch.php`, `app/ValueObjects/` (2 files) — 178 files in total
- **Commit:** `bd8df6c` (2026-10-28)

#### feat(api): add labels, nested resources, collections and a view log
- **What:** Add labels, nested resources, collections and a view log.
- **Why:** The addendum requires a label beside every enum value, nested resources and collection classes, and the agreement requires personal-data access to be logged.
- **Files:** `CHANGELOG.md`, `app/Actions/Members/ShowMemberAction.php`, `app/Enums/AuditEvent.php`, `app/Events/MemberViewed.php`, `app/Http/` (16 files), `app/Listeners/RecordMemberAudit.php`, `app/ValueObjects/Money.php`, `docs/07-data-model.md`, `docs/08-api.md`, `docs/13-decision-log.md`, `tests/Feature/Members/MemberApiTest.php`, `tests/Unit/` (3 files) — 29 files in total
- **Commit:** `6fcf864` (2026-10-28)

#### refactor(auth): move sign-in and health behind Actions and Repositories
- **What:** Move sign-in and health behind Actions and Repositories.
- **Why:** The same layer rules apply to the Sprint 1 sign-in and health code, which still queried models directly.
- **Files:** `ARCHITECTURE.md`, `CHANGELOG.md`, `app/Actions/` (6 files), `app/Console/Commands/ResetOtpLockout.php`, `app/Contracts/` (9 files), `app/DTOs/` (8 files), `app/Http/` (10 files), `app/Jobs/SendSmsJob.php`, `app/Providers/RepositoryServiceProvider.php`, `app/Repositories/` (11 files), `app/Services/OtpService.php`, `tests/Feature/Auth/SendSmsJobTest.php`, `tests/Pest.php`, `tests/Unit/` (16 files) — 68 files in total
- **Commit:** `7dc78ef` (2026-10-28)

#### refactor(members): adopt Actions, DTOs and Repositories
- **What:** Adopt Actions, DTOs and Repositories.
- **Why:** The Universal agreement and Laravel addendum require Controller, Form Request, DTO, Action, Service, Repository, with Actions owning the transaction and firing events. Nine empty interface stubs from the generator were committed here by mistake in app/Contracts/Repositories; they were removed in 2ecac33.
- **Files:** `ARCHITECTURE.md`, `CHANGELOG.md`, `CLAUDE.md`, `app/Actions/` (16 files), `app/Contracts/` (9 files), `app/DTOs/` (18 files), `app/Events/` (3 files), `app/Http/` (35 files), `app/Listeners/RecordMemberAudit.php`, `app/Models/Member.php`, `app/Policies/MemberPolicy.php`, `app/Providers/` (2 files), `app/Repositories/` (18 files), `app/Services/` (9 files), `bootstrap/providers.php`, `docs/09-engineering-principles.md`, `docs/13-decision-log.md`, `tests/Pest.php`, `tests/Unit/` (18 files) — 138 files in total
- **Commit:** `aff40cb` (2026-10-28)

#### refactor(members): use single-action controllers
- **What:** Use single-action controllers.
- **Why:** The project rules say one public method per action, and the sign-in controllers already worked that way, so the member controllers were brought in line.
- **Files:** `CHANGELOG.md`, `app/Http/Controllers/` (19 files), `app/Http/Resources/` (2 files), `app/Services/DepartmentService.php`, `routes/api.php` — 24 files in total
- **Commit:** `c3393d8` (2026-10-28)

#### feat(members): define youth by age, with a per-church limit
- **What:** Define youth by age, with a per-church limit.
- **Why:** The documents said the youth pastor sees "youth only" without saying what that means. The Product Owner chose an age limit that each church can set.
- **Files:** `CHANGELOG.md`, `app/Models/Church.php`, `app/Models/Member.php`, `config/members.php`, `docs/03-member-lifecycle.md`, `docs/08-api.md`, `docs/13-decision-log.md`, `tests/Feature/Members/MemberApiTest.php` — 8 files in total
- **Commit:** `e86ee05` (2026-10-28)

#### feat(members): add member photo upload
- **What:** Add member photo upload.
- **Why:** Each member can have a profile photo. It is stored privately and re-encoded so hidden location data in the picture is removed.
- **Files:** `.github/workflows/ci.yml`, `CHANGELOG.md`, `app/Http/Controllers/Api/V1/MemberController.php`, `app/Http/Requests/Members/UploadMemberPhotoRequest.php`, `app/Http/Resources/` (2 files), `app/Models/Member.php`, `app/Providers/AppServiceProvider.php`, `app/Services/MemberPhotoService.php`, `composer.json`, `composer.lock`, `deploy/Dockerfile`, `docs/08-api.md`, `docs/13-decision-log.md`, `docs/14-integrations.md`, `docs/16-deployment-and-ops.md`, `routes/api.php`, `tests/Feature/Members/MemberPhotoApiTest.php` — 18 files in total
- **Commit:** `9ab9666` (2026-10-28)

#### feat(members): add member milestones
- **What:** Add member milestones.
- **Why:** Baptisms, confirmations and similar milestones are part of a member's record, and only some roles may see them.
- **Files:** `CHANGELOG.md`, `app/Http/Controllers/Api/V1/MemberController.php`, `app/Http/Requests/Members/StoreMilestoneRequest.php`, `app/Http/Resources/` (2 files), `app/Services/MemberService.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `docs/08-api.md`, `docs/13-decision-log.md`, `routes/api.php`, `tests/Feature/Members/MemberMilestoneApiTest.php` — 11 files in total
- **Commit:** `db15abc` (2026-10-28)

#### feat(members): add department endpoints
- **What:** Add department endpoints.
- **Why:** Members serve in ministries such as the choir, so departments and their members can be managed.
- **Files:** `CHANGELOG.md`, `app/Http/Controllers/Api/V1/DepartmentController.php`, `app/Http/Requests/` (5 files), `app/Http/Resources/DepartmentResource.php`, `app/Models/Department.php`, `app/Models/MemberDepartment.php`, `app/Policies/DepartmentPolicy.php`, `app/Services/DepartmentService.php`, `docs/08-api.md`, `routes/api.php`, `tests/Feature/Members/DepartmentApiTest.php` — 15 files in total
- **Commit:** `c5968ed` (2026-10-28)

#### feat(members): add family endpoints
- **What:** Add family endpoints.
- **Why:** Churches think in households, so members can be grouped into families with one head.
- **Files:** `CHANGELOG.md`, `app/Http/Controllers/Api/V1/FamilyController.php`, `app/Http/Requests/` (3 files), `app/Http/Resources/FamilyResource.php`, `app/Models/Family.php`, `app/Policies/FamilyPolicy.php`, `app/Services/FamilyService.php`, `docs/08-api.md`, `routes/api.php`, `tests/Feature/Members/FamilyApiTest.php` — 12 files in total
- **Commit:** `f54f052` (2026-10-28)

#### feat(members): add the member directory API
- **What:** Add the member directory API.
- **Why:** Sprint 2 goal: the church can manage its people. This is the list, view, add, edit and status endpoints.
- **Files:** `CHANGELOG.md`, `app/Enums/EducationalLevel.php`, `app/Http/Controllers/` (2 files), `app/Http/Requests/` (5 files), `app/Http/Resources/` (2 files), `app/Models/Department.php`, `app/Models/Member.php`, `app/Models/MemberMilestone.php`, `app/Policies/MemberPolicy.php`, `app/Services/MemberNumberGenerator.php`, `app/Services/MemberService.php`, `app/Services/MemberSummaries.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `docs/08-api.md`, `docs/13-decision-log.md`, `routes/api.php`, `tests/Feature/Members/` (2 files) — 24 files in total
- **Commit:** `bc4cbcb` (2026-10-28)

#### feat(members): add member numbering, audit log and pastor edit rights
- **What:** Add member numbering, audit log and pastor edit rights.
- **Why:** The member documents require numbered members, a change log and pastor edit rights, but none of them existed yet.
- **Files:** `CHANGELOG.md`, `app/Enums/AuditEvent.php`, `app/Exceptions/MemberNumberPrefixMissingException.php`, `app/Models/AuditLog.php`, `app/Models/Church.php`, `app/Services/AuditLogger.php`, `app/Services/MemberNumberGenerator.php`, `database/factories/AuditLogFactory.php`, `database/factories/ChurchFactory.php`, `database/migrations/2026_10_28_101135_add_member_numbering_to_churches_table.php`, `database/migrations/2026_10_28_101136_create_audit_logs_table.php`, `database/seeders/DatabaseSeeder.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `docs/06-architecture.md`, `docs/07-data-model.md`, `docs/13-decision-log.md`, `tests/Feature/Members/` (3 files) — 19 files in total
- **Commit:** `7f2d326` (2026-10-28)

#### feat(deploy): add Dockerfile, role-based start scripts and Coolify docs
- **What:** Add Dockerfile, role-based start scripts and Coolify docs.
- **Why:** Staging and production run in Docker behind Coolify, with one image that can act as the web server, the queue worker or the scheduler.
- **Files:** `.dockerignore`, `.github/dependabot.yml`, `.github/workflows/ci.yml`, `CHANGELOG.md`, `config/logging.php`, `deploy/Dockerfile`, `deploy/bin/healthcheck`, `deploy/bin/start`, `deploy/supervisor/supervisord.conf`, `docs/13-decision-log.md`, `docs/16-deployment-and-ops.md` — 11 files in total
- **Commit:** `956bed2` (2026-10-28)

#### fix(deploy): make dev-only packages optional and trust the proxy
- **What:** Make dev-only packages optional and trust the proxy.
- **Why:** The production image is built without development packages, so a development-only tool could not be required at start-up. The app also has to trust the proxy so it knows requests are HTTPS.
- **Files:** `.env.example`, `CHANGELOG.md`, `app/Providers/AppServiceProvider.php`, `app/Support/EnvironmentCheck.php`, `bootstrap/providers.php`, `composer.json`, `config/trustedproxy.php`, `docs/17-monitoring-and-alerting.md`, `tests/Feature/Operations/` (3 files) — 11 files in total
- **Commit:** `21f5aa7` (2026-10-28)

#### docs(integrations): record the verified R2 behaviour
- **What:** Record the verified R2 behaviour.
- **Why:** The R2 behaviour was tested against a real bucket; the verified facts are recorded so nobody relies on a guess.
- **Files:** `CHANGELOG.md`, `docs/14-integrations.md`
- **Commit:** `6562395` (2026-10-28)

#### docs(architecture): add ARCHITECTURE.md and record the layering decision
- **What:** Add ARCHITECTURE.md and record the layering decision.
- **Why:** The agreement requires the layers of the code base to be written down so they are not forgotten.
- **Files:** `ARCHITECTURE.md`, `CHANGELOG.md`, `docs/13-decision-log.md`
- **Commit:** `8ca4297` (2026-10-28)

#### chore(ci): add GitHub Actions workflow and Dependabot
- **What:** Add GitHub Actions workflow and Dependabot.
- **Why:** Every change must run the style, type, test and audit checks automatically, and dependencies must be kept up to date.
- **Files:** `.github/dependabot.yml`, `.github/workflows/ci.yml`, `CHANGELOG.md`, `docs/16-deployment-and-ops.md`
- **Commit:** `1ef923d` (2026-10-28)

#### docs(integrations): cite the real source for the separate backup bucket
- **What:** Cite the real source for the separate backup bucket.
- **Why:** A document cited the wrong source for the separate backup bucket; it now cites the real one.
- **Files:** `docs/14-integrations.md`
- **Commit:** `6b1ea30` (2026-10-28)

#### chore(storage): add Cloudflare R2 disks and prune the AWS SDK
- **What:** Add Cloudflare R2 disks and prune the AWS SDK.
- **Why:** Member photos and exports live in Cloudflare R2. The AWS library it needs was 1 GB on disk, so only the S3 part is kept.
- **Files:** `.env.example`, `CHANGELOG.md`, `composer.json`, `composer.lock`, `config/filesystems.php`, `docs/14-integrations.md`, `docs/16-deployment-and-ops.md`, `tests/Feature/Storage/R2DiskTest.php` — 8 files in total
- **Commit:** `f5ae04b` (2026-10-28)

#### chore(tooling): add Laravel Boost MCP server and skills
- **What:** Add Laravel Boost MCP server and skills.
- **Why:** Lets the AI assistant read the real Laravel documentation and database schema instead of guessing from memory.
- **Files:** `.claude/skills/` (34 files), `.mcp.json`, `CHANGELOG.md`, `CLAUDE.md`, `boost.json`, `composer.json`, `composer.lock` — 40 files in total
- **Commit:** `73279a9` (2026-10-28)

#### feat(ops): add health endpoint, request ids, JSON logs and environment checks
- **What:** Add health endpoint, request ids, JSON logs and environment checks.
- **Why:** Problems must be found quickly and a mis-configured server must refuse to start: a health check, a request id on every request, JSON logs and an environment check.
- **Files:** `.env.example`, `CHANGELOG.md`, `app/Console/Commands/CheckEnvironment.php`, `app/Http/Controllers/HealthController.php`, `app/Http/Middleware/` (3 files), `app/Providers/AppServiceProvider.php`, `app/Services/HealthCheck.php`, `app/Support/EnvironmentCheck.php`, `bootstrap/app.php`, `config/health.php`, `config/logging.php`, `docs/16-deployment-and-ops.md`, `docs/17-monitoring-and-alerting.md`, `phpunit.xml`, `routes/health.php`, `tests/Feature/Operations/` (3 files) — 20 files in total
- **Commit:** `60b38f2` (2026-10-28)

#### feat(auth): keep browser users signed in with Sanctum cookie sessions
- **What:** Keep browser users signed in with Sanctum cookie sessions.
- **Why:** A page refresh must not sign staff out (decision D-027), so the browser now holds a secure session cookie instead of a token.
- **Files:** `.env.example`, `CHANGELOG.md`, `app/Http/` (4 files), `app/Providers/AppServiceProvider.php`, `app/Services/` (2 files), `bootstrap/app.php`, `config/sanctum.php`, `docs/08-api.md`, `docs/13-decision-log.md`, `docs/15-security.md`, `docs/qa/QA-01-authentication.md`, `phpunit.xml`, `resources/js/` (14 files), `routes/api.php`, `tests/Feature/` (2 files) — 33 files in total
- **Commit:** `ca25359` (2026-10-28)

#### feat(frontend): add React SPA shell with OTP sign-in
- **What:** Add React SPA shell with OTP sign-in.
- **Why:** Staff need a screen to sign in and a shell to work in, built mobile-first for churches that use cheap phones.
- **Files:** `.prettierignore`, `.prettierrc.json`, `CHANGELOG.md`, `components.json`, `docs/06-architecture.md`, `docs/13-decision-log.md`, `eslint.config.js`, `package-lock.json`, `package.json`, `resources/css/app.css`, `resources/js/` (29 files), `resources/views/` (2 files), `routes/web.php`, `tsconfig.json`, `vite.config.js`, `vite.config.ts` — 45 files in total
- **Commit:** `ae3dd77` (2026-10-28)

#### fix(auth): align OTP with QA-01 and revoke tokens on deactivation
- **What:** Align OTP with QA-01 and revoke tokens on deactivation.
- **Why:** The first sign-in code did not match the QA-01 test cases, and a staff member who was deactivated could keep using an old token.
- **Files:** `CHANGELOG.md`, `app/Console/Commands/ResetOtpLockout.php`, `app/Exceptions/InvalidOtpException.php`, `app/Exceptions/TooManyOtpAttemptsException.php`, `app/Models/User.php`, `app/Observers/UserObserver.php`, `app/Services/OtpService.php`, `docs/06-architecture.md`, `docs/16-deployment-and-ops.md`, `docs/17-monitoring-and-alerting.md`, `docs/qa/QA-01-authentication.md`, `tests/Feature/Auth/` (3 files) — 14 files in total
- **Commit:** `c9ceb29` (2026-10-28)

#### feat(auth): add OTP sign-in with per-church codes and a critical SMS queue
- **What:** Add OTP sign-in with per-church codes and a critical SMS queue.
- **Why:** Staff sign in with a phone number and a code, not a password. The code is checked per church, and sign-in texts get their own queue so a slow job can never delay them.
- **Files:** `CHANGELOG.md`, `CLAUDE.md`, `README.md`, `app/Contracts/SmsProvider.php`, `app/DTOs/OutboundSms.php`, `app/Exceptions/` (2 files), `app/Http/` (8 files), `app/Jobs/SendSmsJob.php`, `app/Models/` (3 files), `app/Providers/` (2 files), `app/Services/` (3 files), `app/ValueObjects/PhoneNumber.php`, `bootstrap/app.php`, `bootstrap/providers.php`, `config/otp.php`, `config/permission.php`, `config/services.php`, `database/seeders/` (2 files), `docs/08-api.md`, `docs/13-decision-log.md`, `routes/api.php`, `tests/Feature/` (4 files), `tests/Unit/PhoneNumberTest.php` — 40 files in total
- **Commit:** `787e21c` (2026-10-28)

#### feat(tenancy): add enums, models, factories and tenant resolution
- **What:** Add enums, models, factories and tenant resolution.
- **Why:** Every church shares one database, so each row must belong to a church and every query must be limited to the current church. This keeps one church from ever seeing another's data.
- **Files:** `CHANGELOG.md`, `app/Enums/` (22 files), `app/Http/Middleware/ResolveTenant.php`, `app/Models/` (18 files), `app/Providers/AppServiceProvider.php`, `app/Scopes/ChurchScope.php`, `app/Traits/BelongsToChurch.php`, `bootstrap/app.php`, `config/tenancy.php`, `database/factories/` (17 files), `docs/08-api.md`, `docs/13-decision-log.md`, `phpstan.neon`, `routes/api.php`, `tests/Feature/` (3 files), `tests/Pest.php` — 72 files in total
- **Commit:** `9f31867` (2026-10-28)

#### chore(tooling): add Pest, Pint strict types and Larastan level 8
- **What:** Add Pest, Pint strict types and Larastan level 8.
- **Why:** Tests, a code formatter and static analysis are required on every project, so they had to exist before any feature code.
- **Files:** `CHANGELOG.md`, `app/Http/Controllers/Controller.php`, `app/Models/User.php`, `app/Providers/` (2 files), `bootstrap/app.php`, `bootstrap/providers.php`, `composer.json`, `composer.lock`, `config/app.php`, `config/auth.php`, `config/cache.php`, `config/database.php`, `config/filesystems.php`, `config/logging.php`, `config/mail.php`, `config/permission.php`, `config/queue.php`, `config/sanctum.php`, `config/services.php`, `config/session.php`, `config/telescope.php`, `database/factories/UserFactory.php`, `database/migrations/` (22 files), `database/seeders/DatabaseSeeder.php`, `phpstan.neon`, `phpunit.xml`, `pint.json`, `public/index.php`, `routes/console.php`, `routes/web.php`, `tests/Feature/` (2 files), `tests/Pest.php`, `tests/TestCase.php`, `tests/Unit/ExampleTest.php` — 57 files in total
- **Commit:** `beb44ae` (2026-10-28)

### Added
- Cash gifts (stories 4.3 and 4.11, D-046): record a cash gift (with or without a member), list and read gifts, and void a gift with a reason; a new giving.view permission keeps seeing named gifts apart from recording and voiding
- Idempotency-Key support for writes that record money: the same key never does the same thing twice (replays the first answer), a different request with the same key is refused, and concurrent requests with one key take turns
- Fund types (story 4.2, D-046): list, add, rename and switch off a church's giving categories; every new church starts with seven defaults; names are unique ignoring capitals
- WhatsApp CHECKIN (story 3.3, D-045): members text CHECKIN (or CHECKIN and the service code) to the shared church WhatsApp number and are recorded and answered; the Meta webhook proves its sender, keeps every payload and works on a worker; WhatsApp answers sit behind a provider interface, a circuit breaker and a pause switch
- Church onboarding (story 2.1): a public sign-up page where a church proves the admin's phone with a texted code, picks a web address and member number letters, and gets its first admin (D-037)
- Family pages (story 2.13): a searchable list of families with a New family dialog, and a family view with its members and a rename option
- Add and edit member form (story 2.12): three steps, checked one at a time, with the step in the URL and server messages beside their boxes
- Member profile page (story 2.11): Profile, Giving and Attendance tabs, photo upload, status change with confirmation, and milestones
- Member directory page (story 2.10): search and filter members by status, gender and department, one tap to a profile, with filters in the URL (D-036)
- Frontend bundle budget: the production build fails if any script passes 180 kB gzipped
- Security headers on every response (nosniff, frame deny, referrer policy, HSTS over HTTPS), set by the `SecurityHeaders` middleware from `config/security.php`
- OpenAPI 3.1 spec generated by Scramble and committed at `docs/openapi.json`, with interactive docs at `/docs/api` (staff with settings access outside local development), a test and a CI step that fail when the spec is out of date or has untyped fields, and a check that every API route is documented (D-035)
- Project and Universal `CLAUDE.md`: a rule to keep code simple, use the framework before the language, avoid hard-coded values and write clear comments and docblocks
- Concurrency tests (`tests/Concurrency`) that prove, with two real database connections, that only one of two simultaneous requests can take a family's head role or the next member number; they fail when the row locks are removed
- Inverse relationships on every model that was missing one (staff to their members and departments, member to department links, family head, pledges, change history, fund and service to gifts), with tests
- Youth age limit: the youth pastor's view goes by date of birth (younger than the church's `youth_age_limit` setting, default 25), falling back to educational level when there is no birth date (D-031)
- Member photo upload (story 2.4): `POST /api/v1/members/{id}/photo` checks the file's real type and size, rotates, crops and re-encodes it (dropping EXIF/GPS data), stores it in the private bucket and returns a signed link; `photo_url` is now on the member record and directory. Adds `intervention/image` and the `gd` and `exif` PHP extensions (D-032)
- Member milestones (story 2.9): `POST /api/v1/members/{id}/milestones`; milestones show on the member record only for roles with the new `milestones.view` permission
- Department endpoints (story 2.8): list, create and update departments, add and remove members; adding twice only changes the role, removing keeps the history
- Family endpoints (story 2.7): list, show, create and rename families, with an optional first head; counts and member lists respect each role's directory view
- Member directory API (stories 2.2, 2.3, 2.5, 2.6): `GET/POST /api/v1/members`, `GET/PUT /api/v1/members/{id}` and `PATCH /api/v1/members/{id}/status`, with `MemberPolicy`, form requests, `MemberService`, and list/full resources. Search, filters, sort and pagination; role-scoped visibility for department heads and youth pastors; one head per family; departments set with the member; every create, edit and status change in the change log with the reason (D-031)
- `members.deactivate` permission, held by the church admin only
- Sprint 2 groundwork (D-030): per-church member number prefix and a locked sequence so numbers like `GRC-0042` are unique and never reused, an append-only `audit_logs` table with an `AuditLogger` for member change history, and `members.edit` for the pastor role
- Docker and Coolify deployment (D-029): one image from `deploy/Dockerfile` with `web`, `worker` and `scheduler` roles chosen by `CONTAINER_ROLE`, a start script (env check, migrate, seed, optimize), role-aware health check, Supervisor config for the six queues, `.dockerignore`, a CI `image` job (hadolint, build, smoke check) and Dependabot for Docker; Coolify setup documented in `docs/16`
- Laravel 13 application scaffold in the repo root, alongside `docs/` (D-022)
- PostgreSQL migrations for all 16 v1 tables plus Sanctum, Spatie Permission and Telescope, all with ULID keys and working `down()` methods
- Pest, Pint (strict types enforced) and Larastan level 8; tests run against PostgreSQL (`chms_test`)
- 22 backed enums for every fixed-value column, Eloquent models (ULID keys, soft deletes, encrypted casts, `BelongsToChurch` tenancy trait) and factories
- `ResolveTenant` middleware (subdomain or custom domain, 404 when unknown or suspended), run before auth so tokens only resolve within their own church
- API is versioned from day one under `/api/v1` (D-024)
- OTP sign-in: `POST /api/v1/auth/otp/request`, `POST /api/v1/auth/otp/verify`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`. Codes are bcrypt-hashed in Redis per church, locked out after 5 misses, and sent by a queued `SendSmsJob` on the new `critical` queue (D-025)
- Tokens are revoked immediately when a staff member is deactivated or changes phone number; `php artisan otp:reset-lockout {church} {phone}` for support
- `SmsProvider` contract with a development-only log driver; the PayPerlez and Africa's Talking adapters come next
- Roles and permissions seeder, ULID-keyed `Role` and `Permission` models, `PhoneNumber` value object
- Tenancy and database-constraint tests (cross-tenant, partial unique indexes, double check-in)
- React 19 SPA in `resources/js` served through one Blade shell (`/{any?}` catch-all, 404 for unknown churches): passwordless OTP sign-in (React Hook Form + Zod), auth guard, sidebar app layout, church context and a dashboard placeholder. Sessions are httpOnly Sanctum cookies, so a refresh keeps the user signed in; bearer tokens remain for non-browser clients (D-027)
- Vitest + Testing Library suite (16 tests), ESLint 9 with jsx-a11y and react-hooks, Prettier, strict TypeScript
- shadcn/ui primitives (button, input, label, card) and the Tailwind v4 theme
- `GET /api/health` readiness probe (database, cache, queue, storage, disk) and Laravel's `/up` liveness probe; `X-Request-ID` on every response with the id, church and user added to log context and queued jobs; JSON log format for production; `php artisan env:check` and a fail-fast guard so a misconfigured deploy stops at start-up
- `ARCHITECTURE.md`: the backend and frontend layers, tenancy rules, auth, queues, providers and observability as built, with a checklist of where new code goes (D-028)
- GitHub Actions CI/deploy workflow (`.github/workflows/ci.yml`): backend and frontend checks on every PR, migration round-trip, dependency audits, Coolify deploys with a health smoke test, manual approval for production; weekly Dependabot PRs
- Cloudflare R2 disks (`r2`, `r2-backup`): private, throw on failure, no public URL, tested offline with locally signed URLs. The AWS SDK is pruned to S3 only (1 GB to 37 MB). `docs/14-integrations.md` corrected: R2 does not implement ACLs, and a real upload, read-back, signed link, tamper and expiry test against a dev bucket confirmed R2 ignores the ACL header and keeps files private
- Laravel Boost MCP server (`.mcp.json`, `boost.json`) with Laravel, testing, Telescope, Tailwind and convention-inference skills for Claude Code; the project's MCP usage is documented in `CLAUDE.md`
- `.env.example` rewritten for this stack (PostgreSQL, Redis, tenancy, SMS, health)
- `scripts/setup-dev-db.sh` for local Postgres role and databases

### Fixed
- Telescope is now registered only locally and excluded from auto-discovery, as the Laravel docs prescribe for a `--dev` package. Before, `bootstrap/providers.php` always loaded it, so a `composer install --no-dev` production build would have crashed on boot
- Behind Coolify's HTTPS proxy Laravel believed every request was plain HTTP. `config/trustedproxy.php` (default: the private Docker ranges, `TRUSTED_PROXIES`) makes it trust only the proxy
- `env:check` and the start-up guard also require an `https://` APP_URL, secure session cookies and no blanket proxy trust
- A session cookie from one church could be replayed on another church's host, because Sanctum loaded the user before the church was resolved. The tenant middleware now runs first, `AuthenticateSession` is off, and a test fails if the fix is removed (D-027)

### Changed
- Roles and permissions are now the `StaffRole` and `StaffPermission` enums, used by the seeder, policies, scopes and resources instead of bare strings
- Every class and method now has a plain-language description docblock, as the agreement requires
- API responses send a `_label` beside every enum value, nest Resources for related records, page lists through `MemberCollection`, `FamilyCollection` and `DepartmentCollection`, and show money through the new `Money` value object with a `_formatted` sibling (D-034)
- Opening a member's full record is written to the change log as `viewed`, because it shows the encrypted address (D-034)
- Sign-in, sign-out, the current-user endpoint, the OTP lockout command and the health check now go through Actions, DTOs and Resources too; every remaining direct model query (tenant lookup, OTP user lookup, message log) moved behind a Repository; repositories call `Model::query()->create()`, never static `Model::create()`; unit-test helpers build models with factories
- Members, families and departments now follow the Universal layer stack and the Laravel addendum in full (D-033): single-action controllers, DTOs, one Action per intent that owns the transaction and fires events, reusable Services, and a Repository interface per model. The member audit line is now written by a listener to `MemberCreated`, `MemberUpdated` and `MemberStatusChanged`. Form Requests authorise through Policies. Adds unit tests for every Action and Service using mocked repositories
- Member, family and department endpoints are now single-action controllers (one invokable class per route), with response shaping moved into resources and "member not in department" raised by the service
- OTP errors and lockout now follow QA-01 ("Invalid code"; the 5th wrong code returns 429); the ops runbook and queue lists include the `critical` queue
- Database is now PostgreSQL instead of MySQL; data model, CI, backup and restore docs updated (D-021)
- React SPA now lives in the Laravel repo (`resources/js`) behind one Blade shell view, no Inertia; replaces the separate `chms-frontend` repo (D-022, amends D-008)
- Local development is native (PostgreSQL 18, Redis 8, no Docker); staging and production stay on Docker via Coolify (D-023)
- Primary keys are now ULIDs (`CHAR(26)`) instead of UUIDs (`CHAR(36)`) across the data model, API reference, security and QA docs (D-016)
- Fixed-value fields are now `VARCHAR(30)` columns backed by PHP enums instead of native MySQL `ENUM` columns; money stays `DECIMAL(12,2)` in GHS (D-017)
- MoMo/USSD gifts are now paid out to churches automatically per transaction by the platform, with the platform fee deducted; cards keep the Paystack split. Added the `disbursements` table and church payout fields (D-018)
- Platform fee on MoMo/USSD gifts set to 1% capped at GHS 10.00 with a GHS 0.10 floor; minimum digital gift GHS 1.00, maximum GHS 30,000 per transaction (D-018)
- USSD giving (and the Moolre integration) deferred past the MVP; MoMo through PayPerlez is the only MoMo provider at launch (D-019)
- Provider abstraction reworked: PayPerlez is the default for SMS, OTP, MoMo and payouts, Paystack is cards only, Africa's Talking is the SMS fallback. Amounts crossing provider interfaces are decimal strings (D-020)
- README now describes the React 19 frontend (was still listing Vue 3)

### Planning
- Completed full documentation set (docs/01 through docs/17, qa/, reference/)
- Defined 5-sprint MVP plan covering Foundation through Communications & Polish
- Finalised tech stack, data model, and API contract

---

## Pre-release History

### [0.0.1] — 2026-10-23 — Project initialised

#### Added
- Project documentation scaffolded (all 17 planning docs)
- Product vision, personas, and success metrics defined
- Full data model designed (16 tables, UUID PKs, multi-tenancy via `church_id`)
- REST API contract documented (auth, members, attendance, giving, communications, reports)
- Architecture decisions logged (D-001 through D-014)
- Risk register created (R1–R10) and open questions catalogued (Q1–Q9)
- Sprint backlog defined (Sprint 1–5, 77 stories, ~165 story points)
- QA test plan templates created
- Definition of Done established
- Engineering principles and code standards documented
- Security controls and threat model documented
- Deployment runbooks and monitoring thresholds established

---

## Versioning Convention

Once the platform ships, versions follow this pattern:

```
v1.0.0 — MVP launch (Sprint 5 complete, first church live)
v1.x.x — Bug fixes and minor improvements (v1 stabilisation sprints)
v2.0.0 — Relationship Layer launch (pastoral care, youth tracking, small groups)
v2.x.x — Relationship Layer improvements
v3.0.0 — Scale Layer launch (volunteers, events, multi-branch)
```

**API versioning:** Breaking API changes increment the major version. The API version is embedded in the URL (`/api/v1/...`) — clients that haven't updated continue to work on the old version for 6 months after a breaking change.

---

## How to Add a Changelog Entry

When you merge a PR that ships a feature, bug fix, or change:

1. Add an entry under `[Unreleased]`
2. Use one of these change type headings:
   - **Added** — new feature or capability
   - **Changed** — change to existing behaviour
   - **Fixed** — bug fix
   - **Removed** — removed feature or capability
   - **Security** — security fix or improvement
   - **Deprecated** — feature that will be removed
3. Write one line per change, in plain language that a church admin could understand
4. Reference the issue or PR number where possible

When a sprint ends and we cut a release, the `[Unreleased]` section is tagged with the version number and date, and a new empty `[Unreleased]` section is added at the top.
