<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\StoreMilestoneRequest;
use App\Http\Resources\MemberMilestoneResource;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Records a milestone such as a baptism on a member (docs/08, Add Member Milestone).
 */
class StoreMemberMilestoneController extends Controller
{
    public function __construct(
        private readonly MemberService $members,
    ) {}

    public function __invoke(StoreMilestoneRequest $request, Member $member): JsonResponse
    {
        Gate::authorize('update', $member);

        $milestone = $this->members->addMilestone($this->staff($request), $member, $request->validated());

        return (new MemberMilestoneResource($milestone))->response()->setStatusCode(201);
    }
}
