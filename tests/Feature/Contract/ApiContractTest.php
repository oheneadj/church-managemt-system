<?php

declare(strict_types=1);

use App\Jobs\SendSmsJob;
use App\Models\Church;
use App\Models\Contribution;
use App\Models\Department;
use App\Models\Family;
use App\Models\FundType;
use App\Models\Member;
use App\Models\MemberMilestone;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

// Agreement section 35, contract tests. The committed spec (docs/openapi.json) is what the React app is built
// against, so every endpoint it calls is called here for real and the answer is checked against the schema the spec
// promises for that status. If the code and the spec drift apart, one of these fails. The React side checks its own
// sample data against the same spec (resources/js/test/contract.test.ts).

const CHURCH_HOST = 'http://grace.localhost/api/v1';
const PLATFORM_HOST = 'http://localhost/api/v1';

/**
 * Fails unless the response has the given status and its body matches the schema the spec gives for that operation
 * and status. $path is the spec's own path, for example /v1/members/{member}.
 */
function assertMatchesSpec(TestResponse $response, string $method, string $path, int $status): void
{
    $response->assertStatus($status);

    $spec = json_decode((string) file_get_contents(base_path('docs/openapi.json')), false);
    $operation = $spec->paths->{$path}->{strtolower($method)} ?? null;
    expect($operation)->not->toBeNull("{$method} {$path} is not in the spec");

    $documented = $operation->responses->{(string) $status} ?? null;
    expect($documented)->not->toBeNull("{$method} {$path} answers {$status}, which the spec does not list");

    $schema = $documented->content->{'application/json'}->schema ?? null;
    if ($schema === null) {
        return; // the spec documents this status with no body (for example 204)
    }

    // Validate against the whole spec as the schema document, so "#/components/schemas/..." references resolve.
    $pointer = '#/paths/'.str_replace('/', '~1', $path).'/'.strtolower($method).'/responses/'.$status.'/content/application~1json/schema';
    $document = clone $spec;
    $document->{'$ref'} = $pointer;

    $result = (new Validator)->validate(json_decode($response->getContent()), $document);

    if (! $result->isValid()) {
        test()->fail("{$method} {$path} ({$status}) does not match the spec:\n".json_encode((new ErrorFormatter)->format($result->error()), JSON_PRETTY_PRINT));
    }
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace', 'member_number_prefix' => 'GRC']);
    $this->admin = User::factory()->for($this->church)->create();
    $this->admin->assignRole('church-admin');
    Sanctum::actingAs($this->admin, ['*']);
    $this->member = Member::factory()->create(['church_id' => $this->church->id, 'family_id' => null]);
});

describe('members', function (): void {
    it('list', fn () => assertMatchesSpec($this->getJson(CHURCH_HOST.'/members'), 'GET', '/v1/members', 200));

    it('show, with a family, a department and a milestone', function (): void {
        $family = Family::factory()->create(['church_id' => $this->church->id]);
        $this->member->update(['family_id' => $family->id, 'family_role' => 'head']);
        $department = Department::factory()->create(['church_id' => $this->church->id]);
        $this->member->departments()->attach($department->id, ['id' => (string) Str::ulid(), 'church_id' => $this->church->id, 'joined_at' => now()->toDateString()]);
        MemberMilestone::factory()->create(['church_id' => $this->church->id, 'member_id' => $this->member->id]);

        assertMatchesSpec($this->getJson(CHURCH_HOST.'/members/'.$this->member->id), 'GET', '/v1/members/{member}', 200);
    });

    it('create', fn () => assertMatchesSpec(
        $this->postJson(CHURCH_HOST.'/members', ['first_name' => 'Abena', 'last_name' => 'Asante', 'gender' => 'female', 'phone' => '0200987654']),
        'POST', '/v1/members', 201,
    ));

    it('update', fn () => assertMatchesSpec($this->putJson(CHURCH_HOST.'/members/'.$this->member->id, ['occupation' => 'Nurse']), 'PUT', '/v1/members/{member}', 200));

    it('status change', fn () => assertMatchesSpec(
        $this->patchJson(CHURCH_HOST.'/members/'.$this->member->id.'/status', ['status' => 'inactive', 'note' => 'Moved']),
        'PATCH', '/v1/members/{member}/status', 200,
    ));

    it('milestone', fn () => assertMatchesSpec(
        $this->postJson(CHURCH_HOST.'/members/'.$this->member->id.'/milestones', ['type' => 'baptism', 'occurred_at' => '2010-06-05']),
        'POST', '/v1/members/{member}/milestones', 201,
    ));

    it('photo upload', function (): void {
        Storage::fake();
        $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

        assertMatchesSpec($this->post(CHURCH_HOST.'/members/'.$this->member->id.'/photo', ['photo' => $file], ['Accept' => 'application/json']), 'POST', '/v1/members/{member}/photo', 200);
    });

    it('data export', fn () => assertMatchesSpec($this->getJson(CHURCH_HOST.'/members/'.$this->member->id.'/data-export'), 'GET', '/v1/members/{member}/data-export', 200));

    it('erasure answers with no body', fn () => assertMatchesSpec($this->postJson(CHURCH_HOST.'/members/'.$this->member->id.'/erasure', ['reason' => 'Member request']), 'POST', '/v1/members/{member}/erasure', 204));

    it('not found', function (): void {
        $this->getJson(CHURCH_HOST.'/members/01doesnotexist00000000000')->assertNotFound();
    });
});

describe('contributions', function (): void {
    it('record, list, show and void a cash gift', function (): void {
        $fund = FundType::factory()->create(['church_id' => $this->church->id]);
        $created = $this->postJson(CHURCH_HOST.'/contributions', ['fund_type_id' => $fund->id, 'amount' => '150.00', 'payment_method' => 'cash', 'member_id' => $this->member->id], ['Idempotency-Key' => 'contract-key-0001']);
        assertMatchesSpec($created, 'POST', '/v1/contributions', 201);
        $id = $created->json('data.id');

        assertMatchesSpec($this->getJson(CHURCH_HOST.'/contributions'), 'GET', '/v1/contributions', 200);
        assertMatchesSpec($this->getJson(CHURCH_HOST.'/contributions/'.$id), 'GET', '/v1/contributions/{contribution}', 200);
        assertMatchesSpec($this->patchJson(CHURCH_HOST.'/contributions/'.$id.'/void', ['reason' => 'Entered in error']), 'PATCH', '/v1/contributions/{contribution}/void', 200);
    });
});

describe('cancelling a service', function (): void {
    it('answers 204 with no body', function (): void {
        $service = Service::factory()->create(['church_id' => $this->church->id]);

        assertMatchesSpec($this->deleteJson(CHURCH_HOST.'/services/'.$service->id), 'DELETE', '/v1/services/{service}', 204);
    });
});

describe('offering report', function (): void {
    it('shows what a service received', function (): void {
        $service = Service::factory()->create(['church_id' => $this->church->id]);
        $fund = FundType::factory()->create(['church_id' => $this->church->id]);
        Contribution::factory()->create(['church_id' => $this->church->id, 'service_id' => $service->id, 'fund_type_id' => $fund->id, 'amount' => '120.50']);

        assertMatchesSpec($this->getJson(CHURCH_HOST.'/services/'.$service->id.'/offering-report'), 'GET', '/v1/services/{service}/offering-report', 200);
    });
});

describe('pledges', function (): void {
    it('make, list, show and change a pledge, and pay it with a gift', function (): void {
        $fund = FundType::factory()->create(['church_id' => $this->church->id]);
        $created = $this->postJson(CHURCH_HOST.'/pledges', ['member_id' => $this->member->id, 'fund_type_id' => $fund->id, 'description' => 'Building Fund 2026 Pledge', 'amount_pledged' => '1000.00', 'target_date' => now()->addMonth()->toDateString()], ['Idempotency-Key' => 'contract-pledge-0001']);
        assertMatchesSpec($created, 'POST', '/v1/pledges', 201);
        $id = $created->json('data.id');

        $this->postJson(CHURCH_HOST.'/contributions', ['member_id' => $this->member->id, 'fund_type_id' => $fund->id, 'pledge_id' => $id, 'amount' => '250.00', 'payment_method' => 'cash'], ['Idempotency-Key' => 'contract-pledge-gift-1'])->assertCreated();

        assertMatchesSpec($this->getJson(CHURCH_HOST.'/pledges'), 'GET', '/v1/pledges', 200);
        assertMatchesSpec($this->getJson(CHURCH_HOST.'/pledges/'.$id), 'GET', '/v1/pledges/{pledge}', 200);
        assertMatchesSpec($this->putJson(CHURCH_HOST.'/pledges/'.$id, ['description' => 'Roof Pledge']), 'PUT', '/v1/pledges/{pledge}', 200);
    });
});

describe('fund types', function (): void {
    it('list, create and update', function (): void {
        assertMatchesSpec($this->getJson(CHURCH_HOST.'/fund-types'), 'GET', '/v1/fund-types', 200);
        $created = $this->postJson(CHURCH_HOST.'/fund-types', ['name' => 'Harvest']);
        assertMatchesSpec($created, 'POST', '/v1/fund-types', 201);

        assertMatchesSpec($this->putJson(CHURCH_HOST.'/fund-types/'.$created->json('data.id'), ['name' => 'Harvest Offering']), 'PUT', '/v1/fund-types/{fund_type}', 200);
    });
});

describe('families and departments', function (): void {
    it('families list', fn () => assertMatchesSpec($this->getJson(CHURCH_HOST.'/families'), 'GET', '/v1/families', 200));

    it('family create, show and update', function (): void {
        $created = $this->postJson(CHURCH_HOST.'/families', ['name' => 'Asante Family']);
        assertMatchesSpec($created, 'POST', '/v1/families', 201);
        $id = $created->json('data.id');

        assertMatchesSpec($this->getJson(CHURCH_HOST.'/families/'.$id), 'GET', '/v1/families/{family}', 200);
        assertMatchesSpec($this->putJson(CHURCH_HOST.'/families/'.$id, ['name' => 'Asante-Mensah Family']), 'PUT', '/v1/families/{family}', 200);
    });

    it('departments list, create, update and members', function (): void {
        assertMatchesSpec($this->getJson(CHURCH_HOST.'/departments'), 'GET', '/v1/departments', 200);
        $created = $this->postJson(CHURCH_HOST.'/departments', ['name' => 'Choir']);
        assertMatchesSpec($created, 'POST', '/v1/departments', 201);
        $id = $created->json('data.id');

        assertMatchesSpec($this->putJson(CHURCH_HOST.'/departments/'.$id, ['name' => 'Main Choir']), 'PUT', '/v1/departments/{department}', 200);
        assertMatchesSpec($this->postJson(CHURCH_HOST.'/departments/'.$id.'/members', ['member_id' => $this->member->id]), 'POST', '/v1/departments/{department}/members', 201);
    });
});

describe('services and attendance', function (): void {
    it('services: list, create, show, update and check-in', function (): void {
        assertMatchesSpec($this->getJson(CHURCH_HOST.'/services'), 'GET', '/v1/services', 200);
        $created = $this->postJson(CHURCH_HOST.'/services', ['name' => 'Sunday Service', 'type' => 'sunday', 'service_date' => now()->toDateString(), 'starts_at' => '09:00']);
        assertMatchesSpec($created, 'POST', '/v1/services', 201);
        $id = $created->json('data.id');

        assertMatchesSpec($this->getJson(CHURCH_HOST.'/services/'.$id), 'GET', '/v1/services/{service}', 200);
        assertMatchesSpec($this->putJson(CHURCH_HOST.'/services/'.$id, ['name' => 'Early Service']), 'PUT', '/v1/services/{service}', 200);
        assertMatchesSpec($this->patchJson(CHURCH_HOST.'/services/'.$id.'/checkin', ['is_open' => true]), 'PATCH', '/v1/services/{service}/checkin', 200);
    });

    it('attendance: record, list, visitor, member report and absent members', function (): void {
        $service = Service::factory()->open()->create(['church_id' => $this->church->id]);
        $url = CHURCH_HOST.'/services/'.$service->id;

        assertMatchesSpec($this->postJson($url.'/attendance', ['member_ids' => [$this->member->id]]), 'POST', '/v1/services/{service}/attendance', 201);
        assertMatchesSpec($this->postJson($url.'/visitors', ['name' => 'Yaw Boateng', 'phone' => '0244999000']), 'POST', '/v1/services/{service}/visitors', 201);
        assertMatchesSpec($this->getJson($url.'/attendance'), 'GET', '/v1/services/{service}/attendance', 200);
        assertMatchesSpec($this->getJson(CHURCH_HOST.'/members/'.$this->member->id.'/attendance'), 'GET', '/v1/members/{member}/attendance', 200);
        assertMatchesSpec($this->getJson(CHURCH_HOST.'/attendance/absent'), 'GET', '/v1/attendance/absent', 200);
    });
});

describe('QR check-in', function (): void {
    it('code, scan, text and check-in', function (): void {
        Queue::fake();
        $service = Service::factory()->open()->create(['church_id' => $this->church->id]);
        $this->member->update(['phone' => '+233244123456']);

        $issued = $this->getJson(CHURCH_HOST.'/services/'.$service->id.'/qr');
        assertMatchesSpec($issued, 'GET', '/v1/services/{service}/qr', 200);
        $token = $issued->json('token');

        assertMatchesSpec($this->getJson(CHURCH_HOST.'/checkin/qr/'.$token), 'GET', '/v1/checkin/qr/{token}', 200);
        assertMatchesSpec($this->postJson(CHURCH_HOST.'/checkin/qr/otp', ['token' => $token, 'phone' => '0244123456']), 'POST', '/v1/checkin/qr/otp', 200);

        $code = '';
        Queue::assertPushed(SendSmsJob::class, function ($job) use (&$code): bool {
            preg_match('/\b(\d{6})\b/', $job->sms->message, $m);
            $code = $m[1];

            return true;
        });
        assertMatchesSpec($this->postJson(CHURCH_HOST.'/checkin/qr', ['token' => $token, 'phone' => '0244123456', 'otp' => $code]), 'POST', '/v1/checkin/qr', 200);
    });
});

describe('sign-in and who am I', function (): void {
    it('me', fn () => assertMatchesSpec($this->getJson(CHURCH_HOST.'/auth/me'), 'GET', '/v1/auth/me', 200));

    it('otp request', function (): void {
        Queue::fake();

        assertMatchesSpec($this->postJson(CHURCH_HOST.'/auth/otp/request', ['phone' => '+233244123456']), 'POST', '/v1/auth/otp/request', 200);
    });

    it('a validation error follows the documented 422 shape', function (): void {
        assertMatchesSpec($this->postJson(CHURCH_HOST.'/auth/otp/request', ['phone' => 'abc']), 'POST', '/v1/auth/otp/request', 422);
    });
});

describe('starting a church', function (): void {
    it('otp request on the platform host', function (): void {
        Queue::fake();

        assertMatchesSpec($this->postJson(PLATFORM_HOST.'/onboarding/otp', ['admin_phone' => '+233244300001']), 'POST', '/v1/onboarding/otp', 200);
    });

    it('a validation error follows the documented 422 shape', function (): void {
        assertMatchesSpec($this->postJson(PLATFORM_HOST.'/onboarding/churches', []), 'POST', '/v1/onboarding/churches', 422);
    });

    it('church created', function (): void {
        Queue::fake();
        $this->postJson(PLATFORM_HOST.'/onboarding/otp', ['admin_phone' => '+233244300002'])->assertOk();
        $code = '';
        Queue::assertPushed(SendSmsJob::class, function ($job) use (&$code): bool {
            preg_match('/\b(\d{6})\b/', $job->sms->message, $m);
            $code = $m[1];

            return true;
        });

        assertMatchesSpec($this->postJson(PLATFORM_HOST.'/onboarding/churches', [
            'church_name' => 'Victory Chapel', 'member_number_prefix' => 'VCI', 'admin_name' => 'Kofi Mensah', 'admin_phone' => '+233244300002', 'otp' => $code,
        ]), 'POST', '/v1/onboarding/churches', 201);
    });
});
