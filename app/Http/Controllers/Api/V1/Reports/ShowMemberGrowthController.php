<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Actions\Reports\ShowMemberGrowthAction;
use App\DTOs\Reports\MemberGrowthDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\MemberGrowthRequest;
use App\Http\Resources\MemberGrowthResource;

/**
 * The member growth report, month by month (docs/08, Member Growth).
 */
class ShowMemberGrowthController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ShowMemberGrowthAction $action) {}

    /**
     * Runs ShowMemberGrowthAction for this request and returns the MemberGrowthResource.
     */
    public function __invoke(MemberGrowthRequest $request): MemberGrowthResource
    {
        return new MemberGrowthResource($this->action->execute(MemberGrowthDTO::fromRequest($request)));
    }
}
