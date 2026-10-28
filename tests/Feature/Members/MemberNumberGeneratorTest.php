<?php

declare(strict_types=1);

use App\Exceptions\MemberNumberPrefixMissingException;
use App\Models\Church;
use App\Models\Member;
use App\Services\MemberNumberGenerator;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->church = Church::factory()->create(['member_number_prefix' => 'GRC']);
    $this->generator = app(MemberNumberGenerator::class);
});

it('hands out zero-padded numbers in order', function (): void {
    $numbers = DB::transaction(fn () => [
        $this->generator->next($this->church->id),
        $this->generator->next($this->church->id),
        $this->generator->next($this->church->id),
    ]);

    expect($numbers)->toBe(['GRC-0001', 'GRC-0002', 'GRC-0003']);
});

it('grows past four digits instead of wrapping', function (): void {
    $this->church->update(['member_number_seq' => 9999]);

    expect(DB::transaction(fn () => $this->generator->next($this->church->id)))->toBe('GRC-10000');
});

it('counts each church on its own', function (): void {
    $other = Church::factory()->create(['member_number_prefix' => 'ABC']);

    DB::transaction(fn () => $this->generator->next($this->church->id));

    expect(DB::transaction(fn () => $this->generator->next($other->id)))->toBe('ABC-0001');
});

it('never reuses a number after the member is deleted', function (): void {
    $first = DB::transaction(fn () => $this->generator->next($this->church->id));
    $member = Member::factory()->create(['church_id' => $this->church->id, 'member_number' => $first]);
    $member->delete();

    expect(DB::transaction(fn () => $this->generator->next($this->church->id)))->toBe('GRC-0002');
});

it('locks the church row while it counts', function (): void {
    DB::enableQueryLog();

    DB::transaction(fn () => $this->generator->next($this->church->id));

    $selects = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $q) => str_contains($q, 'from "churches"'));
    expect($selects->contains(fn (string $q) => str_contains($q, 'for update')))->toBeTrue();
});

it('refuses when the church has no prefix yet', function (): void {
    $church = Church::factory()->create(['member_number_prefix' => null]);

    expect(fn () => DB::transaction(fn () => $this->generator->next($church->id)))
        ->toThrow(MemberNumberPrefixMissingException::class);

    expect($church->fresh()->member_number_seq)->toBe(0);
});
