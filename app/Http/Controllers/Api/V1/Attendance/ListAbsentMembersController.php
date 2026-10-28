<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Actions\Attendance\ListAbsentMembersAction;
use App\DTOs\Attendance\ListAbsentMembersDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ListAbsentMembersRequest;
use App\Http\Resources\AbsentMemberCollection;

/**
 * Active members who missed the last few services in a row (docs/08, List Absent Members).
 */
class ListAbsentMembersController extends Controller
{
    /**
     * Takes the list absent members action.
     */
    public function __construct(private readonly ListAbsentMembersAction $action) {}

    /**
     * Runs ListAbsentMembersAction for this request and returns the AbsentMemberCollection.
     */
    public function __invoke(ListAbsentMembersRequest $request): AbsentMemberCollection
    {
        return new AbsentMemberCollection($this->action->execute(ListAbsentMembersDTO::fromRequest($request)));
    }
}
