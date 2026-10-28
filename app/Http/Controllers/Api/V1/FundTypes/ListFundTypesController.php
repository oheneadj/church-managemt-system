<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\FundTypes;

use App\Actions\FundTypes\ListFundTypesAction;
use App\DTOs\FundTypes\ListFundTypesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FundTypes\ListFundTypesRequest;
use App\Http\Resources\FundTypeCollection;

/**
 * One page of fund types (docs/08, List Fund Types).
 */
class ListFundTypesController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ListFundTypesAction $action) {}

    /**
     * Runs ListFundTypesAction for this request and returns the FundTypeCollection.
     */
    public function __invoke(ListFundTypesRequest $request): FundTypeCollection
    {
        return new FundTypeCollection($this->action->execute(ListFundTypesDTO::fromRequest($request)));
    }
}
