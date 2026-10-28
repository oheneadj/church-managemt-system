<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Actions\Members\ListMembersAction;
use App\DTOs\Members\ListMembersDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\ListMembersRequest;
use App\Http\Resources\MemberSummaryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * One page of the member directory (docs/08, List Members).
 */
class ListMembersController extends Controller
{
    public function __construct(private readonly ListMembersAction $action) {}

    public function __invoke(ListMembersRequest $request): AnonymousResourceCollection
    {
        return MemberSummaryResource::collection($this->action->execute(ListMembersDTO::fromRequest($request)));
    }
}
