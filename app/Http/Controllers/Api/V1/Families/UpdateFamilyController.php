<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Families;

use App\Actions\Families\UpdateFamilyAction;
use App\DTOs\Families\UpdateFamilyDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Families\UpdateFamilyRequest;
use App\Http\Resources\FamilyResource;

/**
 * Renames a family or edits its notes (docs/08, Update Family).
 */
class UpdateFamilyController extends Controller
{
    /**
     * Takes the update family action.
     */
    public function __construct(private readonly UpdateFamilyAction $action) {}

    /**
     * Runs UpdateFamilyAction for this request and returns the FamilyResource.
     */
    public function __invoke(UpdateFamilyRequest $request): FamilyResource
    {
        return new FamilyResource($this->action->execute(UpdateFamilyDTO::fromRequest($request)));
    }
}
