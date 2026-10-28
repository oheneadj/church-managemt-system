<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Actions\Members\ChangeMemberStatusAction;
use App\DTOs\Members\ChangeMemberStatusDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\UpdateMemberStatusRequest;
use App\Http\Resources\MemberResource;

/**
 * Moves a member to another status, with a note (docs/08, Update Member Status).
 */
class UpdateMemberStatusController extends Controller
{
    public function __construct(private readonly ChangeMemberStatusAction $action) {}

    public function __invoke(UpdateMemberStatusRequest $request): MemberResource
    {
        return new MemberResource($this->action->execute(ChangeMemberStatusDTO::fromRequest($request)));
    }
}
