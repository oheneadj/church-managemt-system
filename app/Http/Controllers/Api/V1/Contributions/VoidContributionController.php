<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contributions;

use App\Actions\Contributions\VoidContributionAction;
use App\DTOs\Contributions\VoidContributionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contributions\VoidContributionRequest;
use App\Http\Resources\ContributionResource;

/**
 * Voids a gift with a reason (docs/08, Void Contribution). A gift of another church is not found (404).
 */
class VoidContributionController extends Controller
{
    /**
     * Takes the void action.
     */
    public function __construct(private readonly VoidContributionAction $action) {}

    /**
     * Runs the action for this request and returns the ContributionResource.
     */
    public function __invoke(VoidContributionRequest $request): ContributionResource
    {
        return new ContributionResource($this->action->execute(VoidContributionDTO::fromRequest($request)));
    }
}
