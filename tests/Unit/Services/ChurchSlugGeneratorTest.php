<?php

declare(strict_types=1);

use App\Repositories\ChurchRepositoryInterface;
use App\Services\ChurchSlugGenerator;

beforeEach(function (): void {
    $this->taken = [];
    $this->churches = Mockery::mock(ChurchRepositoryInterface::class);
    $this->churches->shouldReceive('slugExists')->andReturnUsing(fn (string $slug): bool => in_array($slug, $this->taken, true));
    $this->slugs = new ChurchSlugGenerator($this->churches);
});

it('builds a slug from the church name', function (): void {
    expect($this->slugs->fromName('Grace Assembly International'))->toBe('grace-assembly-international');
});

it('removes accents and symbols', function (): void {
    expect($this->slugs->fromName("St. Mary's Church (Kumasi) #1"))->toBe('st-marys-church-kumasi-1');
});

it('numbers a name that is taken', function (): void {
    $this->taken = ['grace-assembly', 'grace-assembly-2'];

    expect($this->slugs->fromName('Grace Assembly'))->toBe('grace-assembly-3');
});

it('skips a name that is kept for the platform', function (): void {
    expect($this->slugs->fromName('Admin'))->toBe('admin-2');
});

it('cuts a long name to the allowed length without a trailing hyphen', function (): void {
    $slug = $this->slugs->fromName('The Church of the Latter Day Faithful Pentecostal Assemblies of Ghana');

    expect(strlen($slug))->toBeLessThanOrEqual(config('onboarding.slug_max_length'))->and($slug)->not->toEndWith('-');
});

it('keeps a numbered slug inside the length limit', function (): void {
    $name = str_repeat('a', 60);
    $this->taken = [str_repeat('a', config('onboarding.slug_max_length'))];

    expect(strlen($this->slugs->fromName($name)))->toBeLessThanOrEqual(config('onboarding.slug_max_length'));
});

it('gives a name that is too short a valid slug', function (): void {
    expect($this->slugs->fromName('CA'))->toBe('ca-church');
});

it('says a slug is available only when it is neither used nor reserved', function (): void {
    $this->taken = ['grace'];

    expect($this->slugs->isAvailable('grace'))->toBeFalse()
        ->and($this->slugs->isAvailable('www'))->toBeFalse()
        ->and($this->slugs->isAvailable('victory'))->toBeTrue();
});

it('suggests only free alternatives', function (): void {
    $this->taken = ['grace', 'grace-gh'];

    expect($this->slugs->suggestionsFor('grace'))->toBe(['grace-church', 'grace-2']);
});
