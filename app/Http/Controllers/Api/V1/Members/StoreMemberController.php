<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Actions\Members\CreateMemberAction;
use App\DTOs\Members\CreateMemberDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\StoreMemberRequest;
use App\Http\Resources\MemberResource;
use Illuminate\Http\JsonResponse;

/**
 * Adds a member (docs/08, Create Member).
 */
class StoreMemberController extends Controller
{
    public function __construct(private readonly CreateMemberAction $action) {}

    public function __invoke(StoreMemberRequest $request): JsonResponse
    {
        $member = $this->action->execute(CreateMemberDTO::fromRequest($request));

        return (new MemberResource($member))->response()->setStatusCode(201);
    }
}
