<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\MemberGrowth;
use App\DTOs\Reports\MemberGrowthDTO;
use App\Services\MemberGrowthBuilder;

/**
 * Builds the member growth report. It reads only, so it needs no transaction.
 */
final class ShowMemberGrowthAction
{
    /**
     * Takes the report builder.
     */
    public function __construct(private readonly MemberGrowthBuilder $builder) {}

    /**
     * Builds the report.
     */
    public function execute(MemberGrowthDTO $dto): MemberGrowth
    {
        return $this->builder->build($dto);
    }
}
