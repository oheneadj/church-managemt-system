<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Families;

use App\Actions\Families\ShowFamilyAction;
use App\DTOs\Families\ShowFamilyDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Families\ShowFamilyRequest;
use App\Http\Resources\FamilyResource;

/**
 * One family with the members the caller may see (docs/08, Get Family). A family of another church is not found (404).
 */
class ShowFamilyController extends Controller
{
    /**
     * Takes the show family action.
     */
    public function __construct(private readonly ShowFamilyAction $action) {}

    /**
     * Runs ShowFamilyAction for this request and returns the FamilyResource.
     */
    public function __invoke(ShowFamilyRequest $request): FamilyResource
    {
        return new FamilyResource($this->action->execute(ShowFamilyDTO::fromRequest($request)));
    }
}
