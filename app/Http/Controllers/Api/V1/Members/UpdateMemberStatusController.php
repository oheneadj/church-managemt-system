<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\UpdateMemberStatusRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Support\Facades\Gate;

/**
 * Moves a member to another status, with a note (docs/08, Update Member Status).
 */
class UpdateMemberStatusController extends Controller
{
    public function __construct(
        private readonly MemberService $members,
    ) {}

    public function __invoke(UpdateMemberStatusRequest $request, Member $member): MemberResource
    {
        Gate::authorize('changeStatus', [$member, $request->status()]);

        return new MemberResource($this->members->changeStatus(
            $this->staff($request),
            $member,
            $request->status(),
            $request->validated('note'),
        ));
    }
}
