# Changelog

All notable changes to this project are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

> Changes staged but not yet in a versioned release.

### Change record

Every commit is listed here with **what** was done, **why**, **which files** it touched and **the commit**, newest first. The bullets under Added and Changed below are the short summary of the same work. Rule for every new entry: write the entry before the commit, then add the commit hash to it right after (the small commit that only fills in a hash is not listed).

#### fix(spa): serve stored files instead of the React shell
- **What:** The React catch-all route answered `/storage/...`, so a member photo's signed link returned the app's HTML page instead of the image. `storage` is now one of the paths the catch-all leaves alone. Added a test that a stored-file path reaches the file route (it is refused without a signature) rather than the shell.
- **Why:** An uploaded photo was saved but could not be shown. Found by uploading a photo to the local API and following the link it returned (200 `text/html` instead of `image/jpeg`). The test fails without the fix.
- **Files:** `routes/web.php`, `tests/Feature/Operations/SpaShellTest.php`
- **Commit:** `fe5c75e` (2026-10-28)

#### feat(members-ui): add the family list and family view pages
- **What:** Built story 2.13. A Families page lists the church's households with their member counts, searchable by name, paged, with the search and page in the URL, and a "New family" dialog for staff who may edit (it opens the new family afterwards). A family page shows the name, notes and members with the head first, each one tap from their profile, and staff who may edit can rename it. Added a Families item to the navigation. The member form's family picker now reads every page of families, so a church with more than 100 families is not cut off. The search box is now one shared component used by the member and family lists. Added direct tests of every API service function.
- **Why:** Sprint 2 story 2.13, and a family cannot be used from the member form unless it can be created and reached somewhere. The React addendum requires every API service function to be covered by tests and filters and pages to live in the URL.
- **Files:** `resources/js/pages/FamiliesPage.tsx`, `resources/js/pages/FamilyDetailPage.tsx`, `resources/js/components/Families/` (FamilyFormDialog, FamilyListItem, FamilyMemberList), `resources/js/components/ui/SearchBox.tsx`, `resources/js/components/Members/MemberFilterBar.tsx`, `resources/js/components/Members/ChurchFields.tsx`, `resources/js/hooks/useFamilyFilters.ts`, `resources/js/hooks/useFamilies.ts`, `resources/js/services/api/families.ts`, `resources/js/constants/queryKeys.ts`, `resources/js/router/index.tsx`, `resources/js/components/layout/AppLayout.tsx`, and 6 new Vitest test files
- **Commit:** `fa0ff62` (2026-10-28)

#### feat(members-ui): add the add and edit member form
- **What:** Built story 2.12. One screen at `/members/new` and `/members/:id/edit` adds or edits a member in three short steps (who they are, how to reach them, church life). Each step is checked before moving on, Enter moves to the next step and only the last step saves, the step is kept in the URL, and a message from the server appears beside its box and takes the user back to the step that holds it. The save button is disabled while saving. Departments and messaging choices are native tick boxes, the family picker asks for a role once a family is chosen, and the national ID is only sent when typed. A new member chooses a starting status (prospect or active); editing leaves status to the profile. Staff without the edit permission and records of deceased members get a plain message. The phone rule is now shared with the login page, and the date helper with the milestone dialog.
- **Why:** Sprint 2 story 2.12 and QA-04 TC-04-01 to TC-04-03. The React addendum requires React Hook Form with Zod, a label on every input, server errors mapped back to fields, and `isSubmitting`-style double-submit protection; the agreement requires a mobile-friendly form.
- **Files:** `resources/js/pages/MemberFormPage.tsx`, `resources/js/components/Members/` (MemberForm, memberFormSchema, IdentityFields, ContactFields, ChurchFields), `resources/js/components/form/CheckboxField.tsx`, `resources/js/components/ui/StepIndicator.tsx`, `resources/js/hooks/useUrlStep.ts`, `resources/js/hooks/useMembers.ts`, `resources/js/constants/validation.ts`, `resources/js/utils/today.ts`, `resources/js/types/members.ts`, `resources/js/pages/LoginPage.tsx`, `resources/js/components/Members/AddMilestoneDialog.tsx`, `resources/js/router/index.tsx`, and 3 new Vitest test files
- **Commit:** `db8bc6f` (2026-10-28)

#### test(members-ui): fix the leaving-status drift test
- **What:** The test that compares the frontend `LEAVING_STATUSES` list with `MemberStatus::leavesActiveLife()` compared array keys as well as values; it now compares values only.
- **Why:** It was committed in the profile page commit while failing, because the check result was not looked at before committing. The suite is green again.
- **Files:** `tests/Feature/Operations/FrontendEnumsTest.php`
- **Commit:** `63c1189` (2026-10-28)

#### feat(members-ui): add the member profile page
- **What:** Built story 2.11. A member's page has a header (photo, name, number, status) and three tabs: Profile (contact, personal, church life, family link, departments, milestones), Giving (this month, this year and all time as the API formatted them) and Attendance (last 4, 8 and 12 weeks). The selected tab is kept in the URL. Staff who may edit can change the photo (JPG or PNG under 5 MB, checked before upload), change the status with an optional reason, and add milestones. Moving someone out of active life asks "Are you sure?" first and is offered only to staff with the deactivate permission. A deceased member's record is read-only. Server validation messages appear next to their fields. Also added a timezone-safe date formatter, a full-name helper, a shared field-error helper (moved out of the login page), and a test that checks the "leaving" status list against the PHP enum.
- **Why:** Sprint 2 story 2.11 and QA-04 (lifecycle changes, photo, milestones, no timezone shift on dates). The agreement requires confirmation for destructive actions, errors shown on the field, accessible labels, and no money arithmetic in the browser.
- **Files:** `resources/js/pages/MemberDetailPage.tsx`, `resources/js/components/Members/` (MemberHeader, MemberProfileTab, MemberGivingTab, MemberAttendanceTab, ChangeStatusDialog, AddMilestoneDialog, MilestoneList, PhotoUploadButton), `resources/js/components/ui/DetailList.tsx`, `resources/js/hooks/useUrlTab.ts`, `resources/js/lib/showFieldErrors.ts`, `resources/js/utils/` (formatDate, fullNameOf), `resources/js/constants/` (photo, enums), `resources/js/pages/LoginPage.tsx`, `resources/js/router/index.tsx`, `tests/Feature/Operations/FrontendEnumsTest.php`, and 5 new Vitest test files
- **Commit:** `5e9f280` (2026-10-28)

#### feat(members-ui): add the member directory page
- **What:** Built story 2.10, the member directory. It lists members phone-first with search, status, gender and department filters, loading, empty and error states, and paging. Filters and the page live in the URL. "Add member" shows only for staff who may edit members. Added the shared pieces it needs (page header, status badge, empty state, pagination, confirm dialog, money display, error notice, labelled form fields), typed API services for members, families and departments with their query and mutation hooks, mirrors of the backend enums and permissions, the shadcn parts it uses, toasts, a `Members` nav item and the route. Added a production bundle budget that fails the build above 180 kB gzipped, and a backend test that fails if the frontend enum lists drift from the PHP enums.
- **Why:** Sprint 2 needs the church to manage its people on a phone. The React addendum asks for URL state for filters, TanStack Query for server data, React Hook Form with Zod for forms, no API calls in components, and a bundle budget.
- **Files:** `resources/js/pages/MembersPage.tsx`, `resources/js/components/Members/` (5), `resources/js/components/ui/` (shadcn parts and 7 shared pieces), `resources/js/components/form/` (4), `resources/js/hooks/` (6), `resources/js/services/api/` (3), `resources/js/constants/` (enums, permissions, query keys, routes), `resources/js/types/members.ts`, `resources/js/utils/initialsOf.ts`, `resources/js/test/` (render and fixtures), `resources/js/router/index.tsx`, `resources/js/main.tsx`, `vite.config.ts`, `package.json` (adds `sonner`), `tests/Feature/Operations/FrontendEnumsTest.php`, `ARCHITECTURE.md`, `docs/13-decision-log.md` (D-036), and 14 new Vitest test files
- **Commit:** `aeca497` (2026-10-28)

#### fix(members): find members by local phone numbers and say plainly when a phone is taken
- **What:** Directory search now also matches a typed phone number written as `0201234567`, `233201234567` or `+233201234567` against the stored international number. The duplicate-phone error now reads "A member with this phone number already exists." The tests pin `TENANT_BASE_DOMAIN=localhost` in `phpunit.xml`.
- **Why:** QA-04 TC-04-08 requires searching by the local phone format, and TC-04-02 gives the exact duplicate-phone message. The tenant domain was pinned because setting it to `church.test` in a developer's `.env` for Valet made every test host return 404: tests must not depend on a local `.env`.
- **Files:** `app/Models/Member.php`, `app/Http/Requests/Members/MemberInputRequest.php`, `phpunit.xml`, `tests/Feature/Members/MemberApiTest.php`
- **Commit:** `d80b99c` (2026-10-28)

#### refactor(errors): use named exceptions and match the addendum's test layout
- **What:** Domain rules now throw named exceptions instead of the generic validation or runtime ones: `FamilyHeadAlreadyTakenException`, `FamilyRoleNeedsFamilyException` and `MemberAlreadyInFamilyException` (all 422 with the field named, through the shared `FieldRuleException`), `PhotoNotStoredException`, `SmsProviderUnavailableException` and `MisconfiguredEnvironmentException`. Member feature tests now sign in with Sanctum's `actingAs`. `PhoneNumberTest` moved to `tests/Unit/ValueObjects`, and unit tests were added for the `MemberStatus` and `EducationalLevel` enums and the `Member` and `Church` models.
- **Why:** Section 17 of the agreement asks for named custom exceptions for domain errors; the Laravel addendum says authenticated tests use `actingAs` and sets the unit-test folders (`Actions`, `Services`, `Models`, `Enums`, `ValueObjects`).
- **Files:** `app/Exceptions/` (7 new files), `app/Services/MemberFamilyService.php`, `app/Services/MemberPhotoService.php`, `app/Actions/Families/CreateFamilyAction.php`, `app/Providers/ProviderServiceProvider.php`, `app/Providers/AppServiceProvider.php`, `tests/Feature/Members/` (5 files), `tests/Concurrency/ContestedSlotsTest.php`, `tests/Unit/Actions/Families/CreateFamilyActionTest.php`, `tests/Unit/Services/MemberFamilyServiceTest.php`, `tests/Unit/Enums/` (2 new), `tests/Unit/Models/` (2 new), `tests/Unit/ValueObjects/PhoneNumberTest.php`
- **Commit:** `f3e1dc2` (2026-10-28)

#### feat(security): add the security headers middleware
- **What:** Added the `SecurityHeaders` middleware, registered globally, which sets `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection` and `Referrer-Policy` on every response and `Strict-Transport-Security` on HTTPS requests. The header list lives in `config/security.php`. Updated `docs/15-security.md` to point at the real code.
- **Why:** `docs/15` specified these headers and the Laravel addendum checklist asks "Security headers set in middleware?", but the middleware had never been written.
- **Files:** `app/Http/Middleware/SecurityHeaders.php`, `config/security.php`, `bootstrap/app.php`, `docs/15-security.md`, `tests/Feature/Operations/SecurityHeadersTest.php`
- **Commit:** `fca3595` (2026-10-28)

#### refactor(code): move fixed values into config and simplify tangled logic
- **What:** Moved the rate limits, page sizes (default 25, maximum 100), photo size, quality, limits and link time, and the member number width into `config/limits.php` and `config/members.php`. The phone-number pattern is written once (`PhoneNumber::E164_PATTERN`). Raw HTTP status numbers became the framework's `Response::HTTP_*` constants. The "default directory" and "leaves active life" status lists became methods on `MemberStatus`. The member visibility scope now starts from "nobody" and opens up per role, and the audit listener has one short method per event instead of a `match(true)`.
- **Why:** The new project rule: no hard-coded values, simple and straightforward logic, framework before language. Each fixed value now lives in one place and can be changed without touching code.
- **Files:** `config/limits.php`, `config/members.php`, `app/Providers/AppServiceProvider.php`, `app/DTOs/Members/ListMembersDTO.php`, `app/DTOs/Families/ListFamiliesDTO.php`, `app/DTOs/Departments/ListDepartmentsDTO.php`, `app/Http/Requests/` (List, Upload photo, Auth and Member input requests), `app/ValueObjects/PhoneNumber.php`, `app/Http/Middleware/` (3 files), `app/Services/` (photo, member number, health check), `app/Enums/MemberStatus.php`, `app/Policies/MemberPolicy.php`, `app/Repositories/EloquentMemberRepository.php`, `app/Models/Member.php`, `app/Listeners/RecordMemberAudit.php`, `app/Exceptions/` (3 files), `app/Http/Controllers/` (6 files), and the unit tests that build the list DTOs and call the listener
- **Commit:** `da4d226` (2026-10-28)

#### feat(api): add the OpenAPI spec, docs route and the simplicity rule
- **What:** Added `dedoc/scramble`, generated and committed the OpenAPI 3.1 spec at `docs/openapi.json`, served interactive docs at `/docs/api`, and added a test and a CI step that fail when the spec is out of date, has an untyped field, or misses an endpoint. Typed the fields Scramble could not work out. Added the rule "keep code simple, framework first, no hard-coding, clear comments" to the project and Universal `CLAUDE.md`. Gave the test run 512 MB of memory. Documented the changelog format below.
- **Why:** Section 35 of the agreement requires a committed spec that CI checks and docs that are not public in production. The docs path also had to be kept out of the React catch-all route. The rule and the changelog format were requested by the Product Owner. The full test run had reached PHP's 128 MB limit.
- **Files:** `composer.json`, `composer.lock`, `config/scramble.php`, `docs/openapi.json`, `docs/08-api.md`, `docs/10-definition-of-done.md`, `docs/13-decision-log.md` (D-035), `.github/workflows/ci.yml`, `routes/web.php`, `phpunit.xml`, `CLAUDE.md`, `Universal CLAUDE.md`, `app/Providers/AppServiceProvider.php`, `app/DTOs/Auth/OtpChallenge.php`, `app/DTOs/Members/GivingSummary.php`, `app/Services/MemberSummaries.php`, the Resources, Requests and Controllers that needed a type or response annotation (`app/Http/Resources/` ×5, `app/Http/Requests/` ×4, `app/Http/Controllers/` ×3), `tests/Feature/Operations/ApiDocsTest.php`, `tests/Unit/Services/MemberSummariesTest.php`
- **Commit:** `8dc0dbd` (2026-10-28)

#### refactor(auth): replace role and permission strings with enums
- **What:** Replace role and permission strings with enums.
- **Why:** The agreement forbids magic strings, so roles and permissions became enums.
- **Files:** `CHANGELOG.md`, `app/Actions/Members/` (4 files), `app/Enums/StaffPermission.php`, `app/Enums/StaffRole.php`, `app/Http/Resources/MemberResource.php`, `app/Models/Member.php`, `app/Policies/DepartmentPolicy.php`, `app/Policies/FamilyPolicy.php`, `app/Policies/MemberPolicy.php`, `database/seeders/DatabaseSeeder.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `tests/Unit/Enums/StaffRoleTest.php` — 15 files in total
- **Commit:** `2d036a4` (2026-10-28)

#### test(members): add concurrency tests, inverse relationships and factory use
- **What:** Add concurrency tests, inverse relationships and factory use.
- **Why:** The agreement requires a concurrency test for contested slots, relationships defined on both sides, and factories for test data.
- **Files:** `CHANGELOG.md`, `app/Models/Department.php`, `app/Models/FundType.php`, `app/Models/Member.php`, `app/Models/Service.php`, `app/Models/User.php`, `phpunit.xml`, `tests/Concurrency/ContestedSlotsTest.php`, `tests/Feature/Database/RelationshipsTest.php`, `tests/Feature/Members/` (3 files), `tests/Pest.php` — 13 files in total
- **Commit:** `b3402cb` (2026-10-28)

#### docs(code): add a description docblock to every class and method
- **What:** Add a description docblock to every class and method.
- **Why:** The agreement requires a plain-language docblock on every class and method.
- **Files:** `app/Actions/` (22 files), `app/Console/` (2 files), `app/Contracts/SmsProvider.php`, `app/DTOs/` (27 files), `app/Events/` (4 files), `app/Exceptions/` (3 files), `app/Http/` (58 files), `app/Jobs/SendSmsJob.php`, `app/Listeners/RecordMemberAudit.php`, `app/Models/` (18 files), `app/Observers/UserObserver.php`, `app/Policies/` (3 files), `app/Providers/` (3 files), `app/Repositories/` (20 files), `app/Scopes/ChurchScope.php`, `app/Services/` (9 files), `app/Support/EnvironmentCheck.php`, `app/Traits/BelongsToChurch.php`, `app/ValueObjects/` (2 files) — 178 files in total
- **Commit:** `6ecd913` (2026-10-28)

#### feat(api): add labels, nested resources, collections and a view log
- **What:** Add labels, nested resources, collections and a view log.
- **Why:** The addendum requires a label beside every enum value, nested resources and collection classes, and the agreement requires personal-data access to be logged.
- **Files:** `CHANGELOG.md`, `app/Actions/Members/ShowMemberAction.php`, `app/Enums/AuditEvent.php`, `app/Events/MemberViewed.php`, `app/Http/` (16 files), `app/Listeners/RecordMemberAudit.php`, `app/ValueObjects/Money.php`, `docs/07-data-model.md`, `docs/08-api.md`, `docs/13-decision-log.md`, `tests/Feature/Members/MemberApiTest.php`, `tests/Unit/` (3 files) — 29 files in total
- **Commit:** `6b4e750` (2026-10-28)

#### refactor(auth): move sign-in and health behind Actions and Repositories
- **What:** Move sign-in and health behind Actions and Repositories.
- **Why:** The same layer rules apply to the Sprint 1 sign-in and health code, which still queried models directly.
- **Files:** `ARCHITECTURE.md`, `CHANGELOG.md`, `app/Actions/` (6 files), `app/Console/Commands/ResetOtpLockout.php`, `app/Contracts/` (9 files), `app/DTOs/` (8 files), `app/Http/` (10 files), `app/Jobs/SendSmsJob.php`, `app/Providers/RepositoryServiceProvider.php`, `app/Repositories/` (11 files), `app/Services/OtpService.php`, `tests/Feature/Auth/SendSmsJobTest.php`, `tests/Pest.php`, `tests/Unit/` (16 files) — 68 files in total
- **Commit:** `2ecac33` (2026-10-28)

#### refactor(members): adopt Actions, DTOs and Repositories
- **What:** Adopt Actions, DTOs and Repositories.
- **Why:** The Universal agreement and Laravel addendum require Controller, Form Request, DTO, Action, Service, Repository, with Actions owning the transaction and firing events. Nine empty interface stubs from the generator were committed here by mistake in app/Contracts/Repositories; they were removed in 2ecac33.
- **Files:** `ARCHITECTURE.md`, `CHANGELOG.md`, `CLAUDE.md`, `app/Actions/` (16 files), `app/Contracts/` (9 files), `app/DTOs/` (18 files), `app/Events/` (3 files), `app/Http/` (35 files), `app/Listeners/RecordMemberAudit.php`, `app/Models/Member.php`, `app/Policies/MemberPolicy.php`, `app/Providers/` (2 files), `app/Repositories/` (18 files), `app/Services/` (9 files), `bootstrap/providers.php`, `docs/09-engineering-principles.md`, `docs/13-decision-log.md`, `tests/Pest.php`, `tests/Unit/` (18 files) — 138 files in total
- **Commit:** `c33eb0d` (2026-10-28)

#### refactor(members): use single-action controllers
- **What:** Use single-action controllers.
- **Why:** The project rules say one public method per action, and the sign-in controllers already worked that way, so the member controllers were brought in line.
- **Files:** `CHANGELOG.md`, `app/Http/Controllers/` (19 files), `app/Http/Resources/` (2 files), `app/Services/DepartmentService.php`, `routes/api.php` — 24 files in total
- **Commit:** `1a28889` (2026-10-28)

#### feat(members): define youth by age, with a per-church limit
- **What:** Define youth by age, with a per-church limit.
- **Why:** The documents said the youth pastor sees "youth only" without saying what that means. The Product Owner chose an age limit that each church can set.
- **Files:** `CHANGELOG.md`, `app/Models/Church.php`, `app/Models/Member.php`, `config/members.php`, `docs/03-member-lifecycle.md`, `docs/08-api.md`, `docs/13-decision-log.md`, `tests/Feature/Members/MemberApiTest.php` — 8 files in total
- **Commit:** `c5fa465` (2026-10-28)

#### feat(members): add member photo upload
- **What:** Add member photo upload.
- **Why:** Each member can have a profile photo. It is stored privately and re-encoded so hidden location data in the picture is removed.
- **Files:** `.github/workflows/ci.yml`, `CHANGELOG.md`, `app/Http/Controllers/Api/V1/MemberController.php`, `app/Http/Requests/Members/UploadMemberPhotoRequest.php`, `app/Http/Resources/` (2 files), `app/Models/Member.php`, `app/Providers/AppServiceProvider.php`, `app/Services/MemberPhotoService.php`, `composer.json`, `composer.lock`, `deploy/Dockerfile`, `docs/08-api.md`, `docs/13-decision-log.md`, `docs/14-integrations.md`, `docs/16-deployment-and-ops.md`, `routes/api.php`, `tests/Feature/Members/MemberPhotoApiTest.php` — 18 files in total
- **Commit:** `12ba9c5` (2026-10-28)

#### feat(members): add member milestones
- **What:** Add member milestones.
- **Why:** Baptisms, confirmations and similar milestones are part of a member's record, and only some roles may see them.
- **Files:** `CHANGELOG.md`, `app/Http/Controllers/Api/V1/MemberController.php`, `app/Http/Requests/Members/StoreMilestoneRequest.php`, `app/Http/Resources/` (2 files), `app/Services/MemberService.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `docs/08-api.md`, `docs/13-decision-log.md`, `routes/api.php`, `tests/Feature/Members/MemberMilestoneApiTest.php` — 11 files in total
- **Commit:** `00ef836` (2026-10-28)

#### feat(members): add department endpoints
- **What:** Add department endpoints.
- **Why:** Members serve in ministries such as the choir, so departments and their members can be managed.
- **Files:** `CHANGELOG.md`, `app/Http/Controllers/Api/V1/DepartmentController.php`, `app/Http/Requests/` (5 files), `app/Http/Resources/DepartmentResource.php`, `app/Models/Department.php`, `app/Models/MemberDepartment.php`, `app/Policies/DepartmentPolicy.php`, `app/Services/DepartmentService.php`, `docs/08-api.md`, `routes/api.php`, `tests/Feature/Members/DepartmentApiTest.php` — 15 files in total
- **Commit:** `37f1062` (2026-10-28)

#### feat(members): add family endpoints
- **What:** Add family endpoints.
- **Why:** Churches think in households, so members can be grouped into families with one head.
- **Files:** `CHANGELOG.md`, `app/Http/Controllers/Api/V1/FamilyController.php`, `app/Http/Requests/` (3 files), `app/Http/Resources/FamilyResource.php`, `app/Models/Family.php`, `app/Policies/FamilyPolicy.php`, `app/Services/FamilyService.php`, `docs/08-api.md`, `routes/api.php`, `tests/Feature/Members/FamilyApiTest.php` — 12 files in total
- **Commit:** `6e9d29b` (2026-10-28)

#### feat(members): add the member directory API
- **What:** Add the member directory API.
- **Why:** Sprint 2 goal: the church can manage its people. This is the list, view, add, edit and status endpoints.
- **Files:** `CHANGELOG.md`, `app/Enums/EducationalLevel.php`, `app/Http/Controllers/` (2 files), `app/Http/Requests/` (5 files), `app/Http/Resources/` (2 files), `app/Models/Department.php`, `app/Models/Member.php`, `app/Models/MemberMilestone.php`, `app/Policies/MemberPolicy.php`, `app/Services/MemberNumberGenerator.php`, `app/Services/MemberService.php`, `app/Services/MemberSummaries.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `docs/08-api.md`, `docs/13-decision-log.md`, `routes/api.php`, `tests/Feature/Members/` (2 files) — 24 files in total
- **Commit:** `9b8c6f0` (2026-10-28)

#### feat(members): add member numbering, audit log and pastor edit rights
- **What:** Add member numbering, audit log and pastor edit rights.
- **Why:** The member documents require numbered members, a change log and pastor edit rights, but none of them existed yet.
- **Files:** `CHANGELOG.md`, `app/Enums/AuditEvent.php`, `app/Exceptions/MemberNumberPrefixMissingException.php`, `app/Models/AuditLog.php`, `app/Models/Church.php`, `app/Services/AuditLogger.php`, `app/Services/MemberNumberGenerator.php`, `database/factories/AuditLogFactory.php`, `database/factories/ChurchFactory.php`, `database/migrations/2026_10_28_101135_add_member_numbering_to_churches_table.php`, `database/migrations/2026_10_28_101136_create_audit_logs_table.php`, `database/seeders/DatabaseSeeder.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `docs/06-architecture.md`, `docs/07-data-model.md`, `docs/13-decision-log.md`, `tests/Feature/Members/` (3 files) — 19 files in total
- **Commit:** `9c761b2` (2026-10-28)

#### feat(deploy): add Dockerfile, role-based start scripts and Coolify docs
- **What:** Add Dockerfile, role-based start scripts and Coolify docs.
- **Why:** Staging and production run in Docker behind Coolify, with one image that can act as the web server, the queue worker or the scheduler.
- **Files:** `.dockerignore`, `.github/dependabot.yml`, `.github/workflows/ci.yml`, `CHANGELOG.md`, `config/logging.php`, `deploy/Dockerfile`, `deploy/bin/healthcheck`, `deploy/bin/start`, `deploy/supervisor/supervisord.conf`, `docs/13-decision-log.md`, `docs/16-deployment-and-ops.md` — 11 files in total
- **Commit:** `909159b` (2026-10-28)

#### fix(deploy): make dev-only packages optional and trust the proxy
- **What:** Make dev-only packages optional and trust the proxy.
- **Why:** The production image is built without development packages, so a development-only tool could not be required at start-up. The app also has to trust the proxy so it knows requests are HTTPS.
- **Files:** `.env.example`, `CHANGELOG.md`, `app/Providers/AppServiceProvider.php`, `app/Support/EnvironmentCheck.php`, `bootstrap/providers.php`, `composer.json`, `config/trustedproxy.php`, `docs/17-monitoring-and-alerting.md`, `tests/Feature/Operations/` (3 files) — 11 files in total
- **Commit:** `c7f4b07` (2026-10-28)

#### docs(integrations): record the verified R2 behaviour
- **What:** Record the verified R2 behaviour.
- **Why:** The R2 behaviour was tested against a real bucket; the verified facts are recorded so nobody relies on a guess.
- **Files:** `CHANGELOG.md`, `docs/14-integrations.md`
- **Commit:** `c47a76e` (2026-10-28)

#### docs(architecture): add ARCHITECTURE.md and record the layering decision
- **What:** Add ARCHITECTURE.md and record the layering decision.
- **Why:** The agreement requires the layers of the code base to be written down so they are not forgotten.
- **Files:** `ARCHITECTURE.md`, `CHANGELOG.md`, `docs/13-decision-log.md`
- **Commit:** `8d780ee` (2026-10-28)

#### chore(ci): add GitHub Actions workflow and Dependabot
- **What:** Add GitHub Actions workflow and Dependabot.
- **Why:** Every change must run the style, type, test and audit checks automatically, and dependencies must be kept up to date.
- **Files:** `.github/dependabot.yml`, `.github/workflows/ci.yml`, `CHANGELOG.md`, `docs/16-deployment-and-ops.md`
- **Commit:** `fb262f5` (2026-10-28)

#### docs(integrations): cite the real source for the separate backup bucket
- **What:** Cite the real source for the separate backup bucket.
- **Why:** A document cited the wrong source for the separate backup bucket; it now cites the real one.
- **Files:** `docs/14-integrations.md`
- **Commit:** `df22a2a` (2026-10-28)

#### chore(storage): add Cloudflare R2 disks and prune the AWS SDK
- **What:** Add Cloudflare R2 disks and prune the AWS SDK.
- **Why:** Member photos and exports live in Cloudflare R2. The AWS library it needs was 1 GB on disk, so only the S3 part is kept.
- **Files:** `.env.example`, `CHANGELOG.md`, `composer.json`, `composer.lock`, `config/filesystems.php`, `docs/14-integrations.md`, `docs/16-deployment-and-ops.md`, `tests/Feature/Storage/R2DiskTest.php` — 8 files in total
- **Commit:** `d6d3ec2` (2026-10-28)

#### chore(tooling): add Laravel Boost MCP server and skills
- **What:** Add Laravel Boost MCP server and skills.
- **Why:** Lets the AI assistant read the real Laravel documentation and database schema instead of guessing from memory.
- **Files:** `.claude/skills/` (34 files), `.mcp.json`, `CHANGELOG.md`, `CLAUDE.md`, `boost.json`, `composer.json`, `composer.lock` — 40 files in total
- **Commit:** `002556e` (2026-10-28)

#### feat(ops): add health endpoint, request ids, JSON logs and environment checks
- **What:** Add health endpoint, request ids, JSON logs and environment checks.
- **Why:** Problems must be found quickly and a mis-configured server must refuse to start: a health check, a request id on every request, JSON logs and an environment check.
- **Files:** `.env.example`, `CHANGELOG.md`, `app/Console/Commands/CheckEnvironment.php`, `app/Http/Controllers/HealthController.php`, `app/Http/Middleware/` (3 files), `app/Providers/AppServiceProvider.php`, `app/Services/HealthCheck.php`, `app/Support/EnvironmentCheck.php`, `bootstrap/app.php`, `config/health.php`, `config/logging.php`, `docs/16-deployment-and-ops.md`, `docs/17-monitoring-and-alerting.md`, `phpunit.xml`, `routes/health.php`, `tests/Feature/Operations/` (3 files) — 20 files in total
- **Commit:** `15afab4` (2026-10-28)

#### feat(auth): keep browser users signed in with Sanctum cookie sessions
- **What:** Keep browser users signed in with Sanctum cookie sessions.
- **Why:** A page refresh must not sign staff out (decision D-027), so the browser now holds a secure session cookie instead of a token.
- **Files:** `.env.example`, `CHANGELOG.md`, `app/Http/` (4 files), `app/Providers/AppServiceProvider.php`, `app/Services/` (2 files), `bootstrap/app.php`, `config/sanctum.php`, `docs/08-api.md`, `docs/13-decision-log.md`, `docs/15-security.md`, `docs/qa/QA-01-authentication.md`, `phpunit.xml`, `resources/js/` (14 files), `routes/api.php`, `tests/Feature/` (2 files) — 33 files in total
- **Commit:** `0856e95` (2026-10-28)

#### feat(frontend): add React SPA shell with OTP sign-in
- **What:** Add React SPA shell with OTP sign-in.
- **Why:** Staff need a screen to sign in and a shell to work in, built mobile-first for churches that use cheap phones.
- **Files:** `.prettierignore`, `.prettierrc.json`, `CHANGELOG.md`, `components.json`, `docs/06-architecture.md`, `docs/13-decision-log.md`, `eslint.config.js`, `package-lock.json`, `package.json`, `resources/css/app.css`, `resources/js/` (29 files), `resources/views/` (2 files), `routes/web.php`, `tsconfig.json`, `vite.config.js`, `vite.config.ts` — 45 files in total
- **Commit:** `165ae72` (2026-10-28)

#### fix(auth): align OTP with QA-01 and revoke tokens on deactivation
- **What:** Align OTP with QA-01 and revoke tokens on deactivation.
- **Why:** The first sign-in code did not match the QA-01 test cases, and a staff member who was deactivated could keep using an old token.
- **Files:** `CHANGELOG.md`, `app/Console/Commands/ResetOtpLockout.php`, `app/Exceptions/InvalidOtpException.php`, `app/Exceptions/TooManyOtpAttemptsException.php`, `app/Models/User.php`, `app/Observers/UserObserver.php`, `app/Services/OtpService.php`, `docs/06-architecture.md`, `docs/16-deployment-and-ops.md`, `docs/17-monitoring-and-alerting.md`, `docs/qa/QA-01-authentication.md`, `tests/Feature/Auth/` (3 files) — 14 files in total
- **Commit:** `755c58e` (2026-10-28)

#### feat(auth): add OTP sign-in with per-church codes and a critical SMS queue
- **What:** Add OTP sign-in with per-church codes and a critical SMS queue.
- **Why:** Staff sign in with a phone number and a code, not a password. The code is checked per church, and sign-in texts get their own queue so a slow job can never delay them.
- **Files:** `CHANGELOG.md`, `CLAUDE.md`, `README.md`, `app/Contracts/SmsProvider.php`, `app/DTOs/OutboundSms.php`, `app/Exceptions/` (2 files), `app/Http/` (8 files), `app/Jobs/SendSmsJob.php`, `app/Models/` (3 files), `app/Providers/` (2 files), `app/Services/` (3 files), `app/ValueObjects/PhoneNumber.php`, `bootstrap/app.php`, `bootstrap/providers.php`, `config/otp.php`, `config/permission.php`, `config/services.php`, `database/seeders/` (2 files), `docs/08-api.md`, `docs/13-decision-log.md`, `routes/api.php`, `tests/Feature/` (4 files), `tests/Unit/PhoneNumberTest.php` — 40 files in total
- **Commit:** `6db7eb7` (2026-10-28)

#### feat(tenancy): add enums, models, factories and tenant resolution
- **What:** Add enums, models, factories and tenant resolution.
- **Why:** Every church shares one database, so each row must belong to a church and every query must be limited to the current church. This keeps one church from ever seeing another's data.
- **Files:** `CHANGELOG.md`, `app/Enums/` (22 files), `app/Http/Middleware/ResolveTenant.php`, `app/Models/` (18 files), `app/Providers/AppServiceProvider.php`, `app/Scopes/ChurchScope.php`, `app/Traits/BelongsToChurch.php`, `bootstrap/app.php`, `config/tenancy.php`, `database/factories/` (17 files), `docs/08-api.md`, `docs/13-decision-log.md`, `phpstan.neon`, `routes/api.php`, `tests/Feature/` (3 files), `tests/Pest.php` — 72 files in total
- **Commit:** `7354d30` (2026-10-28)

#### chore(tooling): add Pest, Pint strict types and Larastan level 8
- **What:** Add Pest, Pint strict types and Larastan level 8.
- **Why:** Tests, a code formatter and static analysis are required on every project, so they had to exist before any feature code.
- **Files:** `CHANGELOG.md`, `app/Http/Controllers/Controller.php`, `app/Models/User.php`, `app/Providers/` (2 files), `bootstrap/app.php`, `bootstrap/providers.php`, `composer.json`, `composer.lock`, `config/app.php`, `config/auth.php`, `config/cache.php`, `config/database.php`, `config/filesystems.php`, `config/logging.php`, `config/mail.php`, `config/permission.php`, `config/queue.php`, `config/sanctum.php`, `config/services.php`, `config/session.php`, `config/telescope.php`, `database/factories/UserFactory.php`, `database/migrations/` (22 files), `database/seeders/DatabaseSeeder.php`, `phpstan.neon`, `phpunit.xml`, `pint.json`, `public/index.php`, `routes/console.php`, `routes/web.php`, `tests/Feature/` (2 files), `tests/Pest.php`, `tests/TestCase.php`, `tests/Unit/ExampleTest.php` — 57 files in total
- **Commit:** `269d265` (2026-10-28)

### Added
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
