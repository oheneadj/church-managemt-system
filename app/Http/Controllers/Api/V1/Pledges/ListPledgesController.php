<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pledges;

use App\Actions\Pledges\ListPledgesAction;
use App\DTOs\Pledges\ListPledgesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pledges\ListPledgesRequest;
use App\Http\Resources\PledgeCollection;

/**
 * One page of pledges (docs/08, List Pledges).
 */
class ListPledgesController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ListPledgesAction $action) {}

    /**
     * Runs ListPledgesAction for this request and returns the PledgeCollection.
     */
    public function __invoke(ListPledgesRequest $request): PledgeCollection
    {
        return new PledgeCollection($this->action->execute(ListPledgesDTO::fromRequest($request)));
    }
}
