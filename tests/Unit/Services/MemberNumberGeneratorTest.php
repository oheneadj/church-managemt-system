<?php

declare(strict_types=1);

use App\Exceptions\MemberNumberPrefixMissingException;
use App\Models\Church;
use App\Repositories\ChurchRepositoryInterface;
use App\Services\MemberNumberGenerator;

function churchesCounting(Church $church): ChurchRepositoryInterface
{
    $churches = Mockery::mock(ChurchRepositoryInterface::class);
    $churches->shouldReceive('lock')->once()->with('01churchunit')->andReturn($church);
    $churches->shouldReceive('incrementMemberNumberSequence')->andReturnUsing(function (Church $c): Church {
        $c->member_number_seq++;

        return $c;
    });

    return $churches;
}

it('locks the church, moves the counter and pads the number to four digits', function (): void {
    expect((new MemberNumberGenerator(churchesCounting(unitChurch('GRC', 41))))->next('01churchunit'))->toBe('GRC-0042');
});

it('grows past four digits instead of wrapping', function (): void {
    expect((new MemberNumberGenerator(churchesCounting(unitChurch('GRC', 9999))))->next('01churchunit'))->toBe('GRC-10000');
});

it('refuses, without moving the counter, when the church has no prefix', function (): void {
    $churches = Mockery::mock(ChurchRepositoryInterface::class);
    $churches->shouldReceive('lock')->once()->andReturn(unitChurch(null, 0));
    $churches->shouldReceive('incrementMemberNumberSequence')->never();

    expect(fn () => (new MemberNumberGenerator($churches))->next('01churchunit'))->toThrow(MemberNumberPrefixMissingException::class);
});
