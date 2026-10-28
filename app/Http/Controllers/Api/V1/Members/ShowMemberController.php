<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Actions\Members\ShowMemberAction;
use App\DTOs\Members\ShowMemberDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\ShowMemberRequest;
use App\Http\Resources\MemberResource;

/**
 * The full record of one member (docs/08, Get Member). A member of another church is not found (404).
 */
class ShowMemberController extends Controller
{
    public function __construct(private readonly ShowMemberAction $action) {}

    public function __invoke(ShowMemberRequest $request): MemberResource
    {
        return new MemberResource($this->action->execute(ShowMemberDTO::fromRequest($request)));
    }
}
