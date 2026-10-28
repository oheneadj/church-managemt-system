<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\FundType;
use App\Models\User;
use App\Services\DefaultFundTypes;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// Fund types (docs/11 story 4.2, docs/08 Fund Types, docs/04 Fund Types, QA-10): the categories every gift is filed under.

const FUND_TYPES = 'http://grace.localhost/api/v1/fund-types';

/** A staff member of the church with one role. */
function fundStaff(Church $church, string $role): User
{
    $user = User::factory()->for($church)->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->church = Church::factory()->create(['slug' => 'grace']);
    $this->admin = fundStaff($this->church, 'church-admin');
    $this->other = Church::factory()->create(['slug' => 'other']);
    Sanctum::actingAs($this->admin, ['*']);
});

describe('the starting list', function (): void {
    it('gives a church the default fund types in the configured order', function (): void {
        app(DefaultFundTypes::class)->createFor($this->church);

        $names = collect($this->getJson(FUND_TYPES)->assertOk()->json('data'))->pluck('name')->all();

        expect($names)->toBe(collect(config('giving.default_fund_types'))->pluck('name')->all())
            ->and($names)->toContain('Tithe', 'Sunday Offering', 'Building Fund', 'Other');
    });

    it('keeps each church\'s list to itself (isolation)', function (): void {
        app(DefaultFundTypes::class)->createFor($this->church);
        app(DefaultFundTypes::class)->createFor($this->other);

        expect($this->getJson(FUND_TYPES)->assertOk()->json('meta.total'))->toBe(count(config('giving.default_fund_types')));
    });
});

describe('listing', function (): void {
    it('lists by sort order then name, in pages, with the external id', function (): void {
        FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Zeta', 'sort_order' => 1]);
        FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Alpha', 'sort_order' => 1]);
        $first = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Mid', 'sort_order' => 0]);

        $response = $this->getJson(FUND_TYPES.'?per_page=2')->assertOk();

        expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Mid', 'Alpha'])
            ->and($response->json('data.0.id'))->toBe($first->id)
            ->and($response->json('meta.total'))->toBe(3)
            ->and($response->json('data.0'))->toHaveKeys(['id', 'name', 'description', 'is_active', 'sort_order'])
            ->and($response->json('data.0'))->not->toHaveKey('church_id');
    });

    it('filters by active', function (): void {
        FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'On', 'is_active' => true]);
        FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Off', 'is_active' => false]);

        expect(collect($this->getJson(FUND_TYPES.'?is_active=1')->json('data'))->pluck('name')->all())->toBe(['On'])
            ->and(collect($this->getJson(FUND_TYPES.'?is_active=0')->json('data'))->pluck('name')->all())->toBe(['Off']);
    });

    it('rejects a bad page size', function (): void {
        $this->getJson(FUND_TYPES.'?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    });
});

describe('adding', function (): void {
    it('adds a fund type to this church only', function (): void {
        $this->postJson(FUND_TYPES, ['name' => 'Harvest', 'description' => 'Yearly harvest', 'sort_order' => 4])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Harvest')->assertJsonPath('data.is_active', true)->assertJsonPath('data.sort_order', 4);

        expect(FundType::query()->withoutGlobalScopes()->where('name', 'Harvest')->sole()->church_id)->toBe($this->church->id);
    });

    it('refuses a name this church already uses, whatever its capitals', function (): void {
        FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe']);

        $this->postJson(FUND_TYPES, ['name' => 'TITHE'])->assertUnprocessable()->assertJsonValidationErrors('name');
    });

    it('allows a name another church uses', function (): void {
        FundType::factory()->create(['church_id' => $this->other->id, 'name' => 'Tithe']);

        $this->postJson(FUND_TYPES, ['name' => 'Tithe'])->assertCreated();
    });

    it('lets the database refuse a duplicate name when two requests race', function (): void {
        FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe']);

        expect(fn () => DB::transaction(fn () => FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'tithe'])))
            ->toThrow(QueryException::class);
    });

    it('rejects bad values', function (array $body, string $field): void {
        $this->postJson(FUND_TYPES, $body)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'no name' => [[], 'name'],
        'empty name' => [['name' => ''], 'name'],
        'name too long' => [['name' => str_repeat('a', 101)], 'name'],
        'negative order' => [['name' => 'X', 'sort_order' => -1], 'sort_order'],
        'order too big' => [['name' => 'X', 'sort_order' => 1001], 'sort_order'],
        'is_active not a boolean' => [['name' => 'X', 'is_active' => 'maybe'], 'is_active'],
    ]);
});

describe('changing', function (): void {
    beforeEach(fn () => $this->fund = FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Tithe']));

    it('renames, with PUT or PATCH', function (): void {
        $this->putJson(FUND_TYPES.'/'.$this->fund->id, ['name' => 'Tithes'])->assertOk()->assertJsonPath('data.name', 'Tithes');
        $this->patchJson(FUND_TYPES.'/'.$this->fund->id, ['description' => 'Ten percent'])->assertOk()->assertJsonPath('data.description', 'Ten percent');
    });

    it('switches off and on again, keeping the record', function (): void {
        $this->patchJson(FUND_TYPES.'/'.$this->fund->id, ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->patchJson(FUND_TYPES.'/'.$this->fund->id, ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);

        expect(FundType::query()->find($this->fund->id))->not->toBeNull();
    });

    it('lets a fund keep its own name when it is saved again', function (): void {
        $this->putJson(FUND_TYPES.'/'.$this->fund->id, ['name' => 'Tithe', 'sort_order' => 3])->assertOk();
    });

    it('refuses a name another of this church\'s funds uses', function (): void {
        FundType::factory()->create(['church_id' => $this->church->id, 'name' => 'Missions']);

        $this->putJson(FUND_TYPES.'/'.$this->fund->id, ['name' => 'missions'])->assertUnprocessable()->assertJsonValidationErrors('name');
    });

    it('has no way to delete a fund type', function (): void {
        $this->deleteJson(FUND_TYPES.'/'.$this->fund->id)->assertStatus(405);
    });
});

describe('authentication and roles', function (): void {
    beforeEach(fn () => $this->fund = FundType::factory()->create(['church_id' => $this->church->id]));

    it('rejects every endpoint without a token', function (string $method, string $path): void {
        $this->app['auth']->forgetGuards();

        $this->json($method, str_replace('{id}', $this->fund->id, FUND_TYPES.$path))->assertUnauthorized();
    })->with([['GET', ''], ['POST', ''], ['PUT', '/{id}'], ['PATCH', '/{id}']]);

    it('lets every role read the list (QA-10)', function (string $role): void {
        Sanctum::actingAs(fundStaff($this->church, $role), ['*']);

        $this->getJson(FUND_TYPES)->assertOk();
    })->with(['church-admin', 'pastor', 'finance-secretary', 'department-head', 'youth-pastor']);

    it('lets only the church admin add or change a fund type', function (string $role, bool $allowed): void {
        Sanctum::actingAs(fundStaff($this->church, $role), ['*']);

        $this->postJson(FUND_TYPES, ['name' => 'New '.$role])->assertStatus($allowed ? 201 : 403);
        $this->putJson(FUND_TYPES.'/'.$this->fund->id, ['name' => 'Renamed '.$role])->assertStatus($allowed ? 200 : 403);
        $this->patchJson(FUND_TYPES.'/'.$this->fund->id, ['is_active' => false])->assertStatus($allowed ? 200 : 403);
    })->with([['church-admin', true], ['pastor', false], ['finance-secretary', false], ['department-head', false], ['youth-pastor', false]]);
});

describe('another church', function (): void {
    it('is a 404 to change a fund type of another church, and nothing changes (isolation)', function (): void {
        $stranger = FundType::factory()->create(['church_id' => $this->other->id, 'name' => 'Theirs']);

        $this->putJson(FUND_TYPES.'/'.$stranger->id, ['name' => 'Mine now'])->assertNotFound();
        $this->patchJson(FUND_TYPES.'/'.$stranger->id, ['is_active' => false])->assertNotFound();

        expect($stranger->fresh())->name->toBe('Theirs')->is_active->toBeTrue();
    });
});
