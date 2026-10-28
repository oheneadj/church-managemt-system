<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\FundTypes;

use App\Actions\FundTypes\UpdateFundTypeAction;
use App\DTOs\FundTypes\UpdateFundTypeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FundTypes\UpdateFundTypeRequest;
use App\Http\Resources\FundTypeResource;

/**
 * Edits or switches off a fund type (docs/08, Update Fund Type). A fund type of another church is not found (404).
 */
class UpdateFundTypeController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly UpdateFundTypeAction $action) {}

    /**
     * Runs UpdateFundTypeAction for this request and returns the FundTypeResource.
     */
    public function __invoke(UpdateFundTypeRequest $request): FundTypeResource
    {
        return new FundTypeResource($this->action->execute(UpdateFundTypeDTO::fromRequest($request)));
    }
}
