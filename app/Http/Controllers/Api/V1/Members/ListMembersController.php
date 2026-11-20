<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Actions\Members\ListMembersAction;
use App\DTOs\Members\ListMembersDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\ListMembersRequest;
use App\Http\Resources\MemberCollection;

/**
 * One page of the member directory (docs/08, List Members).
 */
class ListMembersController extends Controller
{
    /**
     * Takes the list members action.
     */
    public function __construct(private readonly ListMembersAction $action) {}

    /**
     * Runs ListMembersAction for this request and returns the MemberCollection.
     */
    public function __invoke(ListMembersRequest $request): MemberCollection
    {
        return new MemberCollection($this->action->execute(ListMembersDTO::fromRequest($request)));
    }
}
