<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Church;

use App\Actions\Church\UpdateChurchProfileAction;
use App\DTOs\Church\UpdateChurchProfileDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Church\UpdateChurchProfileRequest;
use App\Http\Resources\ChurchProfileResource;

/**
 * Changes the church's own details (docs/08, Update Church Profile).
 */
class UpdateChurchProfileController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly UpdateChurchProfileAction $action) {}

    /**
     * Runs UpdateChurchProfileAction for this request and returns the ChurchProfileResource.
     */
    public function __invoke(UpdateChurchProfileRequest $request): ChurchProfileResource
    {
        return new ChurchProfileResource($this->action->execute(UpdateChurchProfileDTO::fromRequest($request)));
    }
}
