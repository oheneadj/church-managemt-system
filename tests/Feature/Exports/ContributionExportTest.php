<?php

declare(strict_types=1);

use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Jobs\BuildExportJob;
use App\Jobs\SendEmailJob;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Export;
use App\Models\FundType;
use App\Models\Member;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

// Giving records as a CSV (docs/11 story 5.9, docs/08 Exports, QA-11 TC-11-11, QA-10, D-064): the same worker, private file, short
// link and limits as the member list, for staff with `giving.export`. Voided gifts are in it, flagged, for the audit. Today is 8 November 2026.

const EXPORT_CONTRIBUTIONS = 'http://grace.localhost/api/v1/exports/contributions';

/** The columns of the file, in order (QA-11 TC-11-11). */
const CONTRIBUTION_EXPORT_HEADER = ['id', 'date', 'member_name', 'fund_type', 'amount_ghs', 'payment_method', 'reference', 'is_voided', 'recorded_by'];

/** A staff member of the church with one role. */
function givingExportStaff(Church $church, string $role, array $attributes = []): User
{
    $user = User::factory()->for($church)->create($attributes);
    $user->assignRole($role);

    return $user;
}

/** Makes a giving export for the user and builds its file now, as a worker would. */
function buildContributionExport(User $user, array $filters = []): Export
{
    $export = Export::factory()->create(['church_id' => $user->church_id, 'requested_by' => $user->id, 'type' => ExportType::Contributions, 'filters' => $filters]);
    app()->call([new BuildExportJob($export->id), 'handle']);

    return $export->refresh();
}

/** The rows of a finished giving export, keyed by the header. */
function contributionExportRows(Export $export): array
{
    $rows = array_map('str_getcsv', array_filter(explode("\n", trim((string) Storage::get((string) $export->file_path)))));
    $header = array_shift($rows);

    return array_map(fn (array $row): array => array_combine($header, $row), $rows);
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-11-08 10:00:00');
    Storage::fake();
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'name' => 'Grace Assembly', 'timezone' => 'Africa/Accra']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->admin = givingExportStaff($this->church, 'church-admin', ['name' => 'Kofi Admin']);
    $this->tithe = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe']);
    Sanctum::actingAs($this->admin, ['*']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** A gift of this church. */
function exportGift(object $test, array $attributes = []): Contribution
{
    return Contribution::factory()->create([
        'church_id' => $test->church->id, 'fund_type_id' => $test->tithe->id, 'fund_name' => 'Tithe', 'recorded_by' => $test->admin->id, 'contributed_at' => '2026-10-15 09:00:00', ...$attributes,
    ]);
}

describe('asking for an export', function (): void {
    it('answers 202 at once, records the export and leaves the work to a worker', function (): void {
        $response = $this->postJson(EXPORT_CONTRIBUTIONS, ['from' => '2026-10-01', 'to' => '2026-10-31', 'payment_method' => 'cash'])->assertStatus(202);

        $export = Export::query()->sole();
        expect($response->json('export_id'))->toBe($export->id)->and($response->json('message'))->toBe('Export queued. You will receive a download link by email when it is ready.')
            ->and($export->type)->toBe(ExportType::Contributions)->and($export->status)->toBe(ExportStatus::Queued)->and($export->requested_by)->toBe($this->admin->id)
            ->and($export->filters)->toBe(['payment_method' => 'cash', 'from' => '2026-10-01', 'to' => '2026-10-31']);
        Queue::assertPushedOn('exports', BuildExportJob::class, fn (BuildExportJob $job): bool => $job->exportId === $export->id);
    });

    it('keeps only the filters of the gift list that make sense for a ledger, and always includes voided gifts', function (): void {
        $this->postJson(EXPORT_CONTRIBUTIONS, ['is_voided' => true, 'per_page' => 5, 'unknown' => 'x', 'fund_type_id' => $this->tithe->id])->assertStatus(202);

        expect(Export::query()->sole()->filters)->toBe(['fund_type_id' => $this->tithe->id]);
    });

    it('refuses filters that are not valid', function (): void {
        $this->postJson(EXPORT_CONTRIBUTIONS, ['from' => 'last week'])->assertStatus(422)->assertJsonValidationErrors('from');
        $this->postJson(EXPORT_CONTRIBUTIONS, ['from' => '2026-10-31', 'to' => '2026-10-01'])->assertStatus(422)->assertJsonValidationErrors('to');
        $this->postJson(EXPORT_CONTRIBUTIONS, ['fund_type_id' => 'tithe'])->assertStatus(422)->assertJsonValidationErrors('fund_type_id');
        $this->postJson(EXPORT_CONTRIBUTIONS, ['payment_method' => 'barter'])->assertStatus(422)->assertJsonValidationErrors('payment_method');
        expect(Export::query()->count())->toBe(0);
    });

    it('shares the hourly limit with the member list export, for each staff member', function (): void {
        foreach (range(1, 3) as $ignored) {
            $this->postJson('http://grace.localhost/api/v1/exports/members')->assertStatus(202);
        }
        foreach (range(1, 2) as $ignored) {
            $this->postJson(EXPORT_CONTRIBUTIONS)->assertStatus(202);
        }

        $this->postJson(EXPORT_CONTRIBUTIONS)->assertStatus(429)->assertJsonPath('message', 'You can export at most 5 times per hour.');
        $this->postJson('http://grace.localhost/api/v1/exports/members')->assertStatus(429);
    });
});

describe('who may ask', function (): void {
    it('refuses a visitor with no sign-in', function (): void {
        $this->app['auth']->forgetGuards();

        $this->postJson(EXPORT_CONTRIBUTIONS)->assertUnauthorized();
    });

    it('lets the church admin and the finance secretary', function (string $role): void {
        Sanctum::actingAs(givingExportStaff($this->church, $role), ['*']);

        $this->postJson(EXPORT_CONTRIBUTIONS)->assertStatus(202);
    })->with(['church-admin', 'finance-secretary']);

    it('refuses the roles without giving.export, the pastor included', function (string $role): void {
        Sanctum::actingAs(givingExportStaff($this->church, $role), ['*']);

        $this->postJson(EXPORT_CONTRIBUTIONS)->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
        expect(Export::query()->count())->toBe(0);
    })->with(['pastor', 'department-head', 'youth-pastor']);
});

describe('the file', function (): void {
    it('has the documented columns and one row for each gift, oldest first', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => 'Kofi', 'last_name' => 'Mensah']);
        $building = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Building Fund']);
        $later = exportGift($this, ['member_id' => $member->id, 'fund_type_id' => $building->id, 'fund_name' => 'Building Fund', 'amount' => '1200.50', 'contributed_at' => '2026-10-20 15:30:00', 'payperlez_reference' => 'PPZ-REF-1', 'payment_method' => 'momo']);
        $earlier = exportGift($this, ['member_id' => null, 'amount' => '50.00', 'contributed_at' => '2026-10-05 09:00:00']);

        $export = buildContributionExport($this->admin);

        $rows = contributionExportRows($export);
        expect(str_getcsv((string) strtok((string) Storage::get($export->file_path), "\n")))->toBe(CONTRIBUTION_EXPORT_HEADER)
            ->and(array_column($rows, 'id'))->toBe([$earlier->id, $later->id])
            ->and($rows[0])->toBe(['id' => $earlier->id, 'date' => '2026-10-05', 'member_name' => 'Anonymous', 'fund_type' => 'Tithe', 'amount_ghs' => '50.00', 'payment_method' => 'cash', 'reference' => '', 'is_voided' => 'false', 'recorded_by' => 'Kofi Admin'])
            ->and($rows[1])->toBe(['id' => $later->id, 'date' => '2026-10-20', 'member_name' => 'Kofi Mensah', 'fund_type' => 'Building Fund', 'amount_ghs' => '1200.50', 'payment_method' => 'momo', 'reference' => 'PPZ-REF-1', 'is_voided' => 'false', 'recorded_by' => 'Kofi Admin']);
    });

    it('is kept privately at the church\'s own path and the export is marked ready', function (): void {
        exportGift($this);
        exportGift($this);

        $export = buildContributionExport($this->admin);

        expect($export->file_path)->toBe("churches/{$this->church->id}/exports/{$export->id}/contributions-2026-11-08.csv")
            ->and($export->status)->toBe(ExportStatus::Completed)->and($export->row_count)->toBe(2);
    });

    it('includes voided gifts, flagged, so the file can be audited', function (): void {
        exportGift($this, ['amount' => '10.00']);
        exportGift($this, ['amount' => '99.00', 'is_voided' => true, 'voided_at' => now(), 'void_reason' => 'Entered in error', 'voided_by' => $this->admin->id]);

        $rows = contributionExportRows(buildContributionExport($this->admin));

        expect(array_column($rows, 'is_voided'))->toEqualCanonicalizing(['false', 'true'])->and(array_sum(array_column($rows, 'amount_ghs')))->toEqual(109);
    });

    it('keeps the fund name a gift was given under, even after the fund is renamed', function (): void {
        exportGift($this, ['fund_name' => 'Old Name']);
        $this->tithe->update(['name' => 'New Name']);

        expect(contributionExportRows(buildContributionExport($this->admin))[0]['fund_type'])->toBe('Old Name');
    });

    it('shows each date in the church\'s own time zone', function (): void {
        $this->church->update(['timezone' => 'Europe/Athens']);
        exportGift($this, ['contributed_at' => '2026-09-30 23:30:00']);

        expect(contributionExportRows(buildContributionExport($this->admin))[0]['date'])->toBe('2026-10-01');
    });

    it('follows the filters of the gift list', function (): void {
        $kofi = Member::factory()->create(['church_id' => $this->church->id]);
        $building = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Building Fund']);
        $service = Service::factory()->create(['church_id' => $this->church->id]);
        $inRange = exportGift($this, ['member_id' => $kofi->id, 'fund_type_id' => $building->id, 'fund_name' => 'Building Fund', 'payment_method' => 'momo', 'service_id' => $service->id, 'contributed_at' => '2026-10-10 09:00:00']);
        exportGift($this, ['contributed_at' => '2026-09-10 09:00:00']);
        exportGift($this, ['contributed_at' => '2026-11-02 09:00:00']);

        $ids = fn (array $filters): array => array_column(contributionExportRows(buildContributionExport($this->admin, $filters)), 'id');

        expect($ids(['from' => '2026-10-01', 'to' => '2026-10-31']))->toHaveCount(1)
            ->and($ids(['fund_type_id' => $building->id]))->toBe([$inRange->id])
            ->and($ids(['member_id' => $kofi->id]))->toBe([$inRange->id])
            ->and($ids(['payment_method' => 'momo']))->toBe([$inRange->id])
            ->and($ids(['service_id' => $service->id]))->toBe([$inRange->id])
            ->and($ids(['from' => '2026-11-01']))->toHaveCount(1);
    });

    it('holds only this church\'s gifts', function (): void {
        exportGift($this, ['amount' => '10.00']);
        $theirs = FundType::factory()->create(['church_id' => $this->other->id]);
        Contribution::factory()->create(['church_id' => $this->other->id, 'fund_type_id' => $theirs->id, 'fund_name' => 'Theirs', 'amount' => '9999.00']);

        $csv = (string) Storage::get((string) buildContributionExport($this->admin)->file_path);

        expect($csv)->not->toContain('9999.00')->not->toContain('Theirs');
    });

    it('never holds the notes, the void reason or a phone number', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id, 'phone' => '+233244777666']);
        exportGift($this, ['member_id' => $member->id, 'notes' => 'Gave in memory of Auntie Secret', 'is_voided' => true, 'voided_at' => now(), 'void_reason' => 'Secret reason', 'voided_by' => $this->admin->id]);

        $csv = (string) Storage::get((string) buildContributionExport($this->admin)->file_path);

        expect($csv)->not->toContain('Auntie Secret')->not->toContain('Secret reason')->not->toContain('+233244777666');
    });

    it('shows a member whose data was erased as deleted, not as anonymous, and keeps the gift', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => '[deleted]', 'last_name' => '[deleted]']);
        exportGift($this, ['member_id' => $member->id]);
        $member->delete();

        expect(contributionExportRows(buildContributionExport($this->admin))[0]['member_name'])->toBe('[deleted] [deleted]');
    });

    it('stops a spreadsheet from running a name that looks like a formula', function (): void {
        $member = Member::factory()->create(['church_id' => $this->church->id, 'first_name' => '=HYPERLINK("http://evil.example","x")', 'last_name' => 'Mensah']);
        exportGift($this, ['member_id' => $member->id, 'payperlez_reference' => '@cmd']);

        $row = contributionExportRows(buildContributionExport($this->admin))[0];

        expect($row['member_name'])->toBe('\'=HYPERLINK("http://evil.example","x") Mensah')->and($row['reference'])->toBe("'@cmd")->and($row['amount_ghs'])->toBe('200.00');
    });

    it('keeps every amount exact to the pesewa', function (): void {
        foreach (['0.10', '0.20', '1234567.89'] as $amount) {
            exportGift($this, ['amount' => $amount]);
        }

        expect(array_column(contributionExportRows(buildContributionExport($this->admin)), 'amount_ghs'))->toEqualCanonicalizing(['0.10', '0.20', '1234567.89']);
    });

    it('is only the header for a church with no gifts, and is still ready', function (): void {
        $export = buildContributionExport($this->admin);

        expect(array_filter(explode("\n", trim((string) Storage::get($export->file_path)))))->toHaveCount(1)->and($export->row_count)->toBe(0)->and($export->status)->toBe(ExportStatus::Completed);
    });

    it('is built in pieces, so a large ledger does not fill the memory', function (): void {
        config(['exports.chunk_size' => 2]);
        foreach (range(1, 7) as $ignored) {
            exportGift($this);
        }

        expect(contributionExportRows(buildContributionExport($this->admin)))->toHaveCount(7);
    });
});

describe('telling the staff member', function (): void {
    it('emails them a link, naming what it holds, and keeps the link out of the message log', function (): void {
        exportGift($this);

        buildContributionExport($this->admin);

        Queue::assertPushedOn('notifications', SendEmailJob::class, fn (SendEmailJob $job): bool => $job->email->email === $this->admin->email && $job->email->messageType === 'export_ready');
        $mail = Queue::pushed(SendEmailJob::class)->sole()->email->mailable;
        $mail->assertHasSubject('Your giving records export for Grace Assembly is ready');
        expect($mail->url)->toContain('contributions-2026-11-08.csv');
    });
});
