<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The full record of one member (docs/08, Get Member). A member of another church is not found (404).
 */
class ShowMemberController extends Controller
{
    public function __construct(
        private readonly MemberService $members,
    ) {}

    public function __invoke(Request $request, Member $member): MemberResource
    {
        Gate::authorize('view', $member);

        return new MemberResource($this->members->detail($member, $this->staff($request)));
    }
}
