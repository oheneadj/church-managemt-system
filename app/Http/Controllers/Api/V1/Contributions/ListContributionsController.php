<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contributions;

use App\Actions\Contributions\ListContributionsAction;
use App\DTOs\Contributions\ListContributionsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contributions\ListContributionsRequest;
use App\Http\Resources\ContributionCollection;

/**
 * One page of gifts (docs/08, List Contributions). Voided gifts are left out unless `is_voided=1`.
 */
class ListContributionsController extends Controller
{
    /**
     * Takes the list action.
     */
    public function __construct(private readonly ListContributionsAction $action) {}

    /**
     * Runs the action for this request and returns the ContributionCollection.
     */
    public function __invoke(ListContributionsRequest $request): ContributionCollection
    {
        return new ContributionCollection($this->action->execute(ListContributionsDTO::fromRequest($request)));
    }
}
