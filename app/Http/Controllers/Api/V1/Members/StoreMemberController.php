<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Members\StoreMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Adds a member (docs/08, Create Member).
 */
class StoreMemberController extends Controller
{
    public function __construct(
        private readonly MemberService $members,
    ) {}

    public function __invoke(StoreMemberRequest $request): JsonResponse
    {
        Gate::authorize('create', Member::class);

        $member = $this->members->create($this->staff($request), $request->validated());

        return (new MemberResource($member))->response()->setStatusCode(201);
    }
}
