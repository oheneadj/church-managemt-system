<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Members\MemberDataExport;
use App\Models\Member;
use App\Repositories\MemberRepositoryInterface;

/**
 * Gathers everything held about one member for a subject access request (docs/05, "Download my data").
 */
class MemberDataExporter
{
    /**
     * Takes the member repository.
     */
    public function __construct(private readonly MemberRepositoryInterface $members) {}

    /**
     * Loads the member with their family, departments, milestones and history, stamped with the time of export.
     */
    public function gather(Member $member): MemberDataExport
    {
        return new MemberDataExport($this->members->loadForExport($member), now()->toImmutable());
    }
}
