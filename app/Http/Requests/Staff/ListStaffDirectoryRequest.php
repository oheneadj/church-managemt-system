<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Models\User;

/**
 * Validates a request for the staff directory. It takes the same page size as the staff list, so it builds the same DTO, but
 * it is opened by a different permission: `members.edit` rather than `staff.manage` (UserPolicy::viewDirectory).
 */
class ListStaffDirectoryRequest extends ListStaffRequest
{
    /**
     * Asks the policy whether the caller may see the staff directory.
     */
    public function authorize(): bool
    {
        return $this->allowed('viewDirectory', User::class);
    }
}
