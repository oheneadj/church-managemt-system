<?php

declare(strict_types=1);

use App\Enums\ExportStatus;
use App\Jobs\BuildExportJob;
use App\Jobs\SendEmailJob;
use App\Models\Church;
use App\Models\Department;
use App\Models\Export;
use App\Models\Family;
use App\Models\Member;
use App\Models\MemberDepartment;
use App\Models\MessageLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

// The member list as a CSV (docs/11 story 5.8, docs/08 Exports, QA-11 TC-11-09, 10, 12, 13, 14, QA-10, D-064). The file is built by
// a worker, kept privately, and the staff member who asked is emailed a link that works for an hour. Today is 8 November 2026.

const EXPORT_MEMBERS = 'http://grace.localhost/api/v1/exports/members';

/** The columns of the file, in order (QA-11 TC-11-10). */
const EXPORT_HEADER = ['id', 'member_number', 'first_name', 'last_name', 'phone', 'email', 'status', 'gender', 'date_of_birth', 'department', 'family', 'joined_date', 'educational_level'];

/** A staff member of the church with one role. */
function exportStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

/** Runs the job for an export now, as a worker would. */
function runExportJob(string $exportId): void
{
    app()->call([new BuildExportJob($exportId), 'handle']);
}

/** Makes an export for the user and builds its file now, as a worker would. */
function buildExport(User $user, array $filters = []): Export
{
    $export = Export::factory()->create(['church_id' => $user->church_id, 'requested_by' => $user->id, 'filters' => $filters]);
    runExportJob($export->id);

    return $export->refresh();
}

/** The file of a finished export as a list of rows keyed by the header. */
function exportRows(Export $export): array
{
    $lines = array_filter(explode("\n", trim((string) Storage::get((string) $export->file_path))));
    $rows = array_map('str_getcsv', $lines);
    $header = array_shift($rows);

    return array_map(fn (array $row): array => array_combine($header, $row), $rows);
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-11-08 10:00:00');
    Storage::fake();
    Queue::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'name' => 'Grace Assembly']);
    $this->other = Church::factory()->create(['slug' => 'other']);
    $this->admin = exportStaff($this->church, 'church-admin');
    Sanctum::actingAs($this->admin, ['*']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('asking for an export', function (): void {
    it('answers 202 at once with the export\'s id, records it, and leaves the work to a worker', function (): void {
        $response = $this->postJson(EXPORT_MEMBERS, ['status' => 'active', 'search' => 'Mensah'])->assertStatus(202);

        $export = Export::query()->sole();
        expect($response->json('export_id'))->toBe($export->id)
            ->and($response->json('message'))->toBe('Export queued. You will receive a download link by email when it is ready.')
            ->and($export->requested_by)->toBe($this->admin->id)->and($export->status)->toBe(ExportStatus::Queued)
            ->and($export->type->value)->toBe('members')->and($export->filters)->toBe(['status' => 'active', 'search' => 'Mensah']);
        Queue::assertPushedOn('exports', BuildExportJob::class, fn (BuildExportJob $job): bool => $job->exportId === $export->id);
        expect(Storage::allFiles())->toBe([]);
    });

    it('keeps only the filters the member list understands', function (): void {
        $this->postJson(EXPORT_MEMBERS, ['gender' => 'female', 'sort' => 'last_name', 'per_page' => 5, 'unknown' => 'x'])->assertStatus(202);

        expect(Export::query()->sole()->filters)->toBe(['gender' => 'female']);
    });

    it('refuses a filter that is not valid', function (): void {
        $this->postJson(EXPORT_MEMBERS, ['status' => 'retired'])->assertStatus(422)->assertJsonValidationErrors('status');
        $this->postJson(EXPORT_MEMBERS, ['department_id' => 'not-an-id'])->assertStatus(422)->assertJsonValidationErrors('department_id');
        expect(Export::query()->count())->toBe(0);
    });

    it('lets one staff member start five an hour, and says so on the sixth', function (): void {
        foreach (range(1, 5) as $ignored) {
            $this->postJson(EXPORT_MEMBERS)->assertStatus(202);
        }

        $this->postJson(EXPORT_MEMBERS)->assertStatus(429)->assertJsonPath('message', 'You can export at most 5 times per hour.')->assertHeader('Retry-After');
        expect(Export::query()->count())->toBe(5);

        Sanctum::actingAs(exportStaff($this->church, 'finance-secretary'), ['*']);
        $this->postJson(EXPORT_MEMBERS)->assertStatus(202);
    });

    it('starts again after the hour', function (): void {
        foreach (range(1, 5) as $ignored) {
            $this->postJson(EXPORT_MEMBERS)->assertStatus(202);
        }
        $this->travel(61)->minutes();

        $this->postJson(EXPORT_MEMBERS)->assertStatus(202);
    });
});

describe('who may ask', function (): void {
    it('refuses a visitor with no sign-in', function (): void {
        $this->app['auth']->forgetGuards();

        $this->postJson(EXPORT_MEMBERS)->assertUnauthorized();
    });

    it('lets the church admin and the finance secretary', function (string $role): void {
        Sanctum::actingAs(exportStaff($this->church, $role), ['*']);

        $this->postJson(EXPORT_MEMBERS)->assertStatus(202);
    })->with(['church-admin', 'finance-secretary']);

    it('refuses the roles without members.export', function (string $role): void {
        Sanctum::actingAs(exportStaff($this->church, $role), ['*']);

        $this->postJson(EXPORT_MEMBERS)->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
        expect(Export::query()->count())->toBe(0);
    })->with(['pastor', 'department-head', 'youth-pastor']);
});

describe('the file', function (): void {
    it('is a CSV with the documented columns, one row for each member, in name order', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id, 'name' => 'Mensah Family']);
        $choir = Department::factory()->create(['church_id' => $this->church->id, 'name' => 'Choir']);
        $ushers = Department::factory()->create(['church_id' => $this->church->id, 'name' => 'Ushers']);
        $kofi = Member::factory()->create([
            'church_id' => $this->church->id, 'first_name' => 'Kofi', 'last_name' => 'Mensah', 'member_number' => 'GRC-0001', 'phone' => '+233244123456', 'email' => 'kofi@example.com',
            'gender' => 'male', 'date_of_birth' => '1990-05-17', 'membership_date' => '2020-01-05', 'educational_level' => 'graduate', 'family_id' => $family->id,
        ]);
        foreach ([$choir, $ushers] as $department) {
            MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $kofi->id, 'department_id' => $department->id]);
        }
        Member::factory()->create(['church_id' => $this->church->id, 'first_name' => 'Abena', 'last_name' => 'Asante', 'member_number' => 'GRC-0002', 'phone' => '+233200000001']);

        $export = buildExport($this->admin);

        $rows = exportRows($export);
        expect(Storage::exists($export->file_path))->toBeTrue()
            ->and(str_getcsv((string) strtok((string) Storage::get($export->file_path), "\n")))->toBe(EXPORT_HEADER)
            ->and(array_column($rows, 'last_name'))->toBe(['Asante', 'Mensah'])
            ->and($rows[1])->toBe([
                'id' => $kofi->id, 'member_number' => 'GRC-0001', 'first_name' => 'Kofi', 'last_name' => 'Mensah', 'phone' => '+233244123456', 'email' => 'kofi@example.com',
                'status' => 'active', 'gender' => 'male', 'date_of_birth' => '1990-05-17', 'department' => 'Choir; Ushers', 'family' => 'Mensah Family',
                'joined_date' => '2020-01-05', 'educational_level' => 'graduate',
            ])
            ->and($rows[0]['department'])->toBe('')->and($rows[0]['date_of_birth'])->toBe('')->and($rows[0]['family'])->toBe('');
    });

    it('is kept privately at the church\'s own path and the export is marked ready', function (): void {
        Member::factory()->count(3)->create(['church_id' => $this->church->id]);

        $export = buildExport($this->admin);

        expect($export->file_path)->toBe("churches/{$this->church->id}/exports/{$export->id}/members-2026-11-08.csv")
            ->and($export->status)->toBe(ExportStatus::Completed)->and($export->row_count)->toBe(3)
            ->and($export->completed_at?->toDateTimeString())->toBe('2026-11-08 10:00:00');
    });

    it('lists prospects and active members, and the others only when asked for', function (): void {
        Member::factory()->create(['church_id' => $this->church->id, 'last_name' => 'Active', 'status' => 'active']);
        Member::factory()->create(['church_id' => $this->church->id, 'last_name' => 'Prospect', 'status' => 'prospect']);
        Member::factory()->create(['church_id' => $this->church->id, 'last_name' => 'Inactive', 'status' => 'inactive']);

        expect(array_column(exportRows(buildExport($this->admin)), 'last_name'))->toBe(['Active', 'Prospect'])
            ->and(array_column(exportRows(buildExport($this->admin, ['status' => 'inactive'])), 'last_name'))->toBe(['Inactive']);
    });

    it('follows the same filters as the member list', function (): void {
        $choir = Department::factory()->create(['church_id' => $this->church->id]);
        $singer = Member::factory()->create(['church_id' => $this->church->id, 'last_name' => 'Singer', 'gender' => 'female']);
        MemberDepartment::factory()->create(['church_id' => $this->church->id, 'member_id' => $singer->id, 'department_id' => $choir->id]);
        Member::factory()->create(['church_id' => $this->church->id, 'last_name' => 'Other', 'gender' => 'female']);
        Member::factory()->create(['church_id' => $this->church->id, 'last_name' => 'Man', 'gender' => 'male']);

        expect(array_column(exportRows(buildExport($this->admin, ['department_id' => $choir->id])), 'last_name'))->toBe(['Singer'])
            ->and(array_column(exportRows(buildExport($this->admin, ['gender' => 'male'])), 'last_name'))->toBe(['Man'])
            ->and(array_column(exportRows(buildExport($this->admin, ['search' => 'Sing'])), 'last_name'))->toBe(['Singer']);
    });

    it('never holds the national ID, the address or the pastoral notes', function (): void {
        Member::factory()->create([
            'church_id' => $this->church->id, 'national_id_number' => 'GHA-123456789-0', 'national_id_type' => 'ghana_card', 'address' => '12 Ring Road Secret Street', 'nearest_landmark' => 'Near the Secret Landmark', 'digital_address' => 'GA-123-4567',
        ]);

        $csv = (string) Storage::get((string) buildExport($this->admin)->file_path);

        expect($csv)->not->toContain('GHA-123456789-0')->not->toContain('Secret Street')->not->toContain('Secret Landmark')->not->toContain('GA-123-4567')->not->toContain('national')->not->toContain('address');
    });

    it('stops a spreadsheet from running a name that looks like a formula', function (): void {
        Member::factory()->create(['church_id' => $this->church->id, 'first_name' => '=HYPERLINK("http://evil.example","x")', 'last_name' => '@SUM(A1)', 'email' => '-cmd@example.com', 'phone' => '+233244999888']);

        $row = exportRows(buildExport($this->admin))[0];

        expect($row['first_name'])->toBe('\'=HYPERLINK("http://evil.example","x")')->and($row['last_name'])->toBe("'@SUM(A1)")
            ->and($row['email'])->toBe("'-cmd@example.com")->and($row['phone'])->toBe('+233244999888');
    });

    it('holds only this church\'s members, and not one who was removed', function (): void {
        Member::factory()->create(['church_id' => $this->church->id, 'last_name' => 'Mine']);
        Member::factory()->create(['church_id' => $this->other->id, 'last_name' => 'Theirs']);
        Member::factory()->create(['church_id' => $this->church->id, 'last_name' => 'Gone'])->delete();

        expect(array_column(exportRows(buildExport($this->admin)), 'last_name'))->toBe(['Mine']);
    });

    it('is only the header for a church with no members, and is still ready', function (): void {
        $export = buildExport($this->admin);

        expect(array_filter(explode("\n", trim((string) Storage::get($export->file_path)))))->toHaveCount(1)
            ->and($export->status)->toBe(ExportStatus::Completed)->and($export->row_count)->toBe(0);
    });

    it('is built in pieces, so a large church does not fill the memory', function (): void {
        config(['exports.chunk_size' => 2]);
        Member::factory()->count(7)->create(['church_id' => $this->church->id]);

        expect(exportRows(buildExport($this->admin)))->toHaveCount(7);
    });
});

describe('telling the staff member', function (): void {
    it('emails them a link that works for an hour, and keeps the link out of the message log', function (): void {
        Member::factory()->create(['church_id' => $this->church->id]);

        $export = buildExport($this->admin);

        Queue::assertPushedOn('notifications', SendEmailJob::class, fn (SendEmailJob $job): bool => $job->email->email === $this->admin->email && $job->email->messageType === 'export_ready');
        $mail = Queue::pushed(SendEmailJob::class)->sole()->email->mailable;
        $mail->assertHasSubject('Your member list export for Grace Assembly is ready');
        $mail->assertSeeInText('1 hour');
        expect($mail->url)->toContain('members-2026-11-08.csv')->and($mail->url)->toContain((string) CarbonImmutable::now()->addMinutes(60)->timestamp);
        $log = MessageLog::query()->where('church_id', $this->church->id)->sole();
        expect($log->recipient_email)->toBe($this->admin->email)->and($log->content)->not->toContain('members-2026')->not->toContain('http');
    });

    it('does nothing twice if the job runs again', function (): void {
        $export = buildExport($this->admin);
        Storage::put($export->file_path, 'changed');

        runExportJob($export->id);

        expect(Storage::get($export->file_path))->toBe('changed');
        Queue::assertPushed(SendEmailJob::class, 1);
    });

    it('marks the export failed when the job gives up, with nothing sent', function (): void {
        $export = Export::factory()->create(['church_id' => $this->church->id, 'requested_by' => $this->admin->id]);

        (new BuildExportJob($export->id))->failed(new RuntimeException('the disk is full'));

        expect($export->refresh()->status)->toBe(ExportStatus::Failed)->and($export->file_path)->toBeNull();
        Queue::assertNotPushed(SendEmailJob::class);
    });

    it('quietly does nothing for an export that no longer exists', function (): void {
        runExportJob('01HZZZZZZZZZZZZZZZZZZZZZZZ');

        Queue::assertNothingPushed();
    });
});

describe('clearing old files', function (): void {
    it('deletes the file after the days set, keeps the record, and leaves newer ones', function (): void {
        $old = buildExport($this->admin);
        $this->travel(config('exports.file_days') + 1)->days();
        $recent = buildExport($this->admin);

        $this->artisan('exports:prune-files')->assertSuccessful();

        expect(Storage::exists($old->file_path))->toBeFalse()->and(Storage::exists($recent->file_path))->toBeTrue()
            ->and($old->refresh()->file_deleted_at)->not->toBeNull()->and($old->file_path)->toBeNull()->and($old->status)->toBe(ExportStatus::Completed)
            ->and($recent->refresh()->file_deleted_at)->toBeNull();
    });

    it('is run every day', function (): void {
        $scheduled = collect(app(Schedule::class)->events())->contains(fn ($event): bool => str_contains((string) $event->command, 'exports:prune-files'));

        expect($scheduled)->toBeTrue();
    });
});
