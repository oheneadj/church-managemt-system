<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pledges;

use App\Actions\Pledges\ShowPledgeAction;
use App\DTOs\Pledges\ShowPledgeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pledges\ShowPledgeRequest;
use App\Http\Resources\PledgeResource;

/**
 * One pledge with the gifts that pay it (docs/08, Get Pledge). A pledge of another church is not found (404).
 */
class ShowPledgeController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ShowPledgeAction $action) {}

    /**
     * Runs ShowPledgeAction for this request and returns the PledgeResource.
     */
    public function __invoke(ShowPledgeRequest $request): PledgeResource
    {
        return new PledgeResource($this->action->execute(ShowPledgeDTO::fromRequest($request)));
    }
}
