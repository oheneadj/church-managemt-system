<?php

declare(strict_types=1);

use App\Models\Church;
use App\Models\PlatformAdmin;
use App\Models\SmsPackage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

// SMS credit packages (D-069, story 5.4): the bundles a church can buy, set by the platform owner on the platform side. A
// package has a name, a number of credits, a price in GHS, an active switch and a place in the list. There is no delete:
// a package no longer sold is switched off, so purchases that point at it keep their history.

const SMS_PACKAGES = 'http://localhost/api/v1/platform/sms-packages';

beforeEach(function (): void {
    $this->owner = PlatformAdmin::factory()->create();
    $this->actingAs($this->owner, 'platform');
});

describe('who may use it', function (): void {
    it('is refused to anyone who is not signed in to the platform side', function (string $method, string $path): void {
        $this->app['auth']->forgetGuards();
        $package = SmsPackage::factory()->create();

        $this->json($method, str_replace('{id}', $package->id, SMS_PACKAGES.$path))->assertUnauthorized();
    })->with([['GET', ''], ['POST', ''], ['PUT', '/{id}']]);

    it('is refused to a church\'s staff, even a church admin', function (): void {
        $this->app['auth']->forgetGuards();
        $this->seed(RolesAndPermissionsSeeder::class);
        $staff = User::factory()->for(Church::factory()->create(['slug' => 'grace']))->create();
        $staff->assignRole('church-admin');
        Sanctum::actingAs($staff, ['*']);

        $this->getJson(SMS_PACKAGES)->assertUnauthorized();
        $this->postJson(SMS_PACKAGES, ['name' => 'X', 'credits' => 10, 'price' => '5.00'])->assertUnauthorized();
        expect(SmsPackage::query()->count())->toBe(0);
    });

    it('does not exist on a church\'s address', function (): void {
        Church::factory()->create(['slug' => 'grace']);

        $this->getJson('http://grace.localhost/api/v1/platform/sms-packages')->assertNotFound();
    });

    it('has no delete: a package no longer sold is switched off', function (): void {
        $package = SmsPackage::factory()->create();

        $this->deleteJson(SMS_PACKAGES.'/'.$package->id)->assertStatus(405);
        expect(SmsPackage::query()->count())->toBe(1);
    });
});

describe('listing', function (): void {
    it('lists every package, switched off ones too, in the order they are shown to churches, paged', function (): void {
        SmsPackage::factory()->create(['name' => 'Large', 'credits' => 1000, 'price' => '80.00', 'sort_order' => 3]);
        SmsPackage::factory()->create(['name' => 'Small', 'credits' => 100, 'price' => '10.00', 'sort_order' => 1]);
        SmsPackage::factory()->create(['name' => 'Medium', 'credits' => 500, 'price' => '45.50', 'sort_order' => 2, 'is_active' => false]);

        $response = $this->getJson(SMS_PACKAGES)->assertOk()->assertJsonStructure(['data', 'meta' => ['total'], 'links']);

        expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Small', 'Medium', 'Large'])
            ->and($response->json('data.1'))->toMatchArray(['name' => 'Medium', 'credits' => 500, 'price' => '45.50', 'price_formatted' => 'GHS 45.50', 'is_active' => false, 'sort_order' => 2])
            ->and($response->json('meta.total'))->toBe(3);
    });

    it('is paged, and refuses a page size outside the allowed range', function (): void {
        SmsPackage::factory()->count(3)->create();

        $this->getJson(SMS_PACKAGES.'?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
        $this->getJson(SMS_PACKAGES.'?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    });

    it('shows no integer key, only the package\'s own id', function (): void {
        $package = SmsPackage::factory()->create();

        $row = $this->getJson(SMS_PACKAGES)->assertOk()->json('data.0');

        expect($row['id'])->toBe($package->id)->and(array_keys($row))->toBe(['id', 'name', 'credits', 'price', 'price_formatted', 'is_active', 'sort_order']);
    });
});

describe('creating', function (): void {
    it('creates a package, switched on and at the start of the list unless told otherwise', function (): void {
        $this->postJson(SMS_PACKAGES, ['name' => 'Starter 100', 'credits' => 100, 'price' => '12.50'])->assertCreated()
            ->assertJsonPath('data.name', 'Starter 100')->assertJsonPath('data.credits', 100)->assertJsonPath('data.price', '12.50')
            ->assertJsonPath('data.price_formatted', 'GHS 12.50')->assertJsonPath('data.is_active', true)->assertJsonPath('data.sort_order', 0);

        expect(SmsPackage::query()->sole()->price)->toBe('12.50');
    });

    it('keeps the price exact to the pesewa, with no rounding', function (string $sent, string $stored): void {
        $this->postJson(SMS_PACKAGES, ['name' => 'Exact', 'credits' => 10, 'price' => $sent])->assertCreated()->assertJsonPath('data.price', $stored);
    })->with([['0.10', '0.10'], ['7', '7.00'], ['1234.5', '1234.50'], ['99999.99', '99999.99']]);

    it('refuses what is not a good package', function (array $body, string $field): void {
        $this->postJson(SMS_PACKAGES, [...['name' => 'Good', 'credits' => 100, 'price' => '10.00'], ...$body])->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(SmsPackage::query()->count())->toBe(0);
    })->with([
        'no name' => [['name' => ''], 'name'],
        'a name that is too long' => [['name' => str_repeat('x', 101)], 'name'],
        'no credits' => [['credits' => null], 'credits'],
        'zero credits' => [['credits' => 0], 'credits'],
        'a fraction of a credit' => [['credits' => 1.5], 'credits'],
        'too many credits' => [['credits' => 1_000_001], 'credits'],
        'no price' => [['price' => null], 'price'],
        'a free package' => [['price' => '0.00'], 'price'],
        'a negative price' => [['price' => '-5.00'], 'price'],
        'a fraction of a pesewa' => [['price' => '10.001'], 'price'],
        'a price that is too large' => [['price' => '100000.00'], 'price'],
        'a price that is not a number' => [['price' => 'ten'], 'price'],
        'an order below zero' => [['sort_order' => -1], 'sort_order'],
        'an order that is too large' => [['sort_order' => 1001], 'sort_order'],
        'a switch that is not true or false' => [['is_active' => 'maybe'], 'is_active'],
    ]);

    it('refuses a name another package already has, whatever its capitals', function (): void {
        SmsPackage::factory()->create(['name' => 'Starter']);

        $this->postJson(SMS_PACKAGES, ['name' => 'STARTER', 'credits' => 10, 'price' => '5.00'])->assertUnprocessable()->assertJsonValidationErrors('name');
    });
});

describe('changing', function (): void {
    it('changes only what is sent, and keeps the rest', function (): void {
        $package = SmsPackage::factory()->create(['name' => 'Starter', 'credits' => 100, 'price' => '10.00', 'sort_order' => 2]);

        $this->putJson(SMS_PACKAGES.'/'.$package->id, ['price' => '12.00'])->assertOk()->assertJsonPath('data.price', '12.00')->assertJsonPath('data.name', 'Starter');

        expect($package->fresh()->only(['name', 'credits', 'sort_order']))->toBe(['name' => 'Starter', 'credits' => 100, 'sort_order' => 2]);
    });

    it('switches a package off and back on, which is how it stops being sold', function (): void {
        $package = SmsPackage::factory()->create();

        $this->putJson(SMS_PACKAGES.'/'.$package->id, ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->putJson(SMS_PACKAGES.'/'.$package->id, ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);
    });

    it('lets a package keep its own name, and refuses another package\'s', function (): void {
        $first = SmsPackage::factory()->create(['name' => 'Starter']);
        SmsPackage::factory()->create(['name' => 'Growth']);

        $this->putJson(SMS_PACKAGES.'/'.$first->id, ['name' => 'Starter', 'credits' => 200])->assertOk();
        $this->putJson(SMS_PACKAGES.'/'.$first->id, ['name' => 'growth'])->assertUnprocessable()->assertJsonValidationErrors('name');
    });

    it('refuses a bad value the same way as when creating', function (): void {
        $package = SmsPackage::factory()->create();

        $this->putJson(SMS_PACKAGES.'/'.$package->id, ['credits' => 0])->assertUnprocessable()->assertJsonValidationErrors('credits');
        $this->putJson(SMS_PACKAGES.'/'.$package->id, ['price' => '0.00'])->assertUnprocessable()->assertJsonValidationErrors('price');
    });

    it('answers 404 for a package that does not exist', function (): void {
        $this->putJson(SMS_PACKAGES.'/01HZZZZZZZZZZZZZZZZZZZZZZZ', ['name' => 'X'])->assertNotFound();
    });
});
