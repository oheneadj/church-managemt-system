<?php

declare(strict_types=1);

use App\Enums\EducationalLevel;

it('counts every level from creche to tertiary as youth', function (EducationalLevel $level): void {
    expect($level->isYouth())->toBeTrue();
})->with([EducationalLevel::Creche, EducationalLevel::Primary3, EducationalLevel::Jhs2, EducationalLevel::Shs3, EducationalLevel::Tertiary]);

it('counts a graduate as an adult', function (): void {
    expect(EducationalLevel::Graduate->isYouth())->toBeFalse();
});
