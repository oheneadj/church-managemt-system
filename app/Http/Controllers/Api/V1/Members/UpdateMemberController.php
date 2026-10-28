<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Support\Facades\Gate;

/**
 * Changes the fields sent on a member (docs/08, Update Member).
 */
class UpdateMemberController extends Controller
{
    public function __construct(
        private readonly MemberService $members,
    ) {}

    public function __invoke(UpdateMemberRequest $request, Member $member): MemberResource
    {
        Gate::authorize('update', $member);

        return new MemberResource($this->members->update($this->staff($request), $member, $request->validated()));
    }
}
