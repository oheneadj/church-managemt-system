<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\ListMembersRequest;
use App\Http\Resources\MemberSummaryResource;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * One page of the member directory (docs/08, List Members).
 */
class ListMembersController extends Controller
{
    public function __construct(
        private readonly MemberService $members,
    ) {}

    public function __invoke(ListMembersRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Member::class);

        return MemberSummaryResource::collection($this->members->paginate($this->staff($request), $request->validated()));
    }
}
