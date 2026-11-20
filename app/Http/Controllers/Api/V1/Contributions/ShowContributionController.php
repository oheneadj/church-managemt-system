<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contributions;

use App\Actions\Contributions\ShowContributionAction;
use App\DTOs\Contributions\ShowContributionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contributions\ShowContributionRequest;
use App\Http\Resources\ContributionResource;

/**
 * One gift (docs/08, Get Contribution). A gift of another church is not found (404).
 */
class ShowContributionController extends Controller
{
    /**
     * Takes the show action.
     */
    public function __construct(private readonly ShowContributionAction $action) {}

    /**
     * Runs ShowContributionAction for this request and returns the ContributionResource.
     */
    public function __invoke(ShowContributionRequest $request): ContributionResource
    {
        return new ContributionResource($this->action->execute(ShowContributionDTO::fromRequest($request)));
    }
}
