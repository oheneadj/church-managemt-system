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
    public function __construct(private readonly ShowFamilyAction $action) {}

    public function __invoke(ShowFamilyRequest $request): FamilyResource
    {
        return new FamilyResource($this->action->execute(ShowFamilyDTO::fromRequest($request)));
    }
}
