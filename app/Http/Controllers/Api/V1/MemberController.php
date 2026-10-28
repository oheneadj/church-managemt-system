<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\ListMembersRequest;
use App\Http\Requests\Members\StoreMemberRequest;
use App\Http\Requests\Members\StoreMilestoneRequest;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Http\Requests\Members\UpdateMemberStatusRequest;
use App\Http\Resources\MemberMilestoneResource;
use App\Http\Resources\MemberResource;
use App\Http\Resources\MemberSummaryResource;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The member directory endpoints (docs/08, Members). Thin: authorise, hand to the service, shape the reply.
 * A member of another church is not found at all (404), because the church scope hides it.
 */
class MemberController extends Controller
{
    public function __construct(private readonly MemberService $members) {}

    public function index(ListMembersRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Member::class);

        return MemberSummaryResource::collection($this->members->paginate($this->staff($request), $request->validated()));
    }

    public function show(Request $request, Member $member): MemberResource
    {
        Gate::authorize('view', $member);

        return new MemberResource($this->members->detail($member, $this->staff($request)));
    }

    public function store(StoreMemberRequest $request): JsonResponse
    {
        Gate::authorize('create', Member::class);

        $member = $this->members->create($this->staff($request), $request->validated());

        return (new MemberResource($member))->response()->setStatusCode(201);
    }

    public function update(UpdateMemberRequest $request, Member $member): MemberResource
    {
        Gate::authorize('update', $member);

        return new MemberResource($this->members->update($this->staff($request), $member, $request->validated()));
    }

    public function updateStatus(UpdateMemberStatusRequest $request, Member $member): MemberResource
    {
        Gate::authorize('changeStatus', [$member, $request->status()]);

        return new MemberResource($this->members->changeStatus(
            $this->staff($request),
            $member,
            $request->status(),
            $request->validated('note'),
        ));
    }

    public function storeMilestone(StoreMilestoneRequest $request, Member $member): JsonResponse
    {
        Gate::authorize('update', $member);

        $milestone = $this->members->addMilestone($this->staff($request), $member, $request->validated());

        return (new MemberMilestoneResource($milestone))->response()->setStatusCode(201);
    }
}
