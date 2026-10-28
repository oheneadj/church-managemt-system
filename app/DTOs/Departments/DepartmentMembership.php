<?php

declare(strict_types=1);

namespace App\DTOs\Departments;

use App\Models\MemberDepartment;

/**
 * The result of adding a member to a department: the link, and whether it is new (false when only the role changed).
 */
final readonly class DepartmentMembership
{
    /**
     * Holds the link and whether it is new.
     */
    public function __construct(public MemberDepartment $link, public bool $created) {}
}
