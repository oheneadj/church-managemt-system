<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Actions\Members\UpdateMemberAction;
use App\DTOs\Members\UpdateMemberDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Http\Resources\MemberResource;

/**
 * Changes the fields sent on a member (docs/08, Update Member).
 */
class UpdateMemberController extends Controller
{
    public function __construct(private readonly UpdateMemberAction $action) {}

    public function __invoke(UpdateMemberRequest $request): MemberResource
    {
        return new MemberResource($this->action->execute(UpdateMemberDTO::fromRequest($request)));
    }
}
