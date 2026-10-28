<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Actions\Members\AddMemberMilestoneAction;
use App\DTOs\Members\AddMemberMilestoneDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\StoreMilestoneRequest;
use App\Http\Resources\MemberMilestoneResource;
use Illuminate\Http\JsonResponse;

/**
 * Records a milestone such as a baptism on a member (docs/08, Add Member Milestone).
 */
class StoreMemberMilestoneController extends Controller
{
    /**
     * Takes the add member milestone action.
     */
    public function __construct(private readonly AddMemberMilestoneAction $action) {}

    /**
     * Runs AddMemberMilestoneAction for this request and returns the JsonResponse.
     */
    public function __invoke(StoreMilestoneRequest $request): JsonResponse
    {
        $milestone = $this->action->execute(AddMemberMilestoneDTO::fromRequest($request));

        return (new MemberMilestoneResource($milestone))->response()->setStatusCode(201);
    }
}
