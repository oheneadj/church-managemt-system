<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pledges;

use App\Actions\Pledges\UpdatePledgeAction;
use App\DTOs\Pledges\UpdatePledgeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pledges\UpdatePledgeRequest;
use App\Http\Resources\PledgeResource;

/**
 * Changes a pledge's description, amount or target date (docs/08, Update Pledge). A pledge of another church is not found (404).
 */
class UpdatePledgeController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly UpdatePledgeAction $action) {}

    /**
     * Runs UpdatePledgeAction for this request and returns the PledgeResource.
     */
    public function __invoke(UpdatePledgeRequest $request): PledgeResource
    {
        return new PledgeResource($this->action->execute(UpdatePledgeDTO::fromRequest($request)));
    }
}
