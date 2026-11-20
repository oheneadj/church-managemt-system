<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Church;

use App\Actions\Church\UpdateChurchSettingsAction;
use App\DTOs\Church\UpdateChurchSettingsDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Church\UpdateChurchSettingsRequest;
use App\Http\Resources\ChurchSettingsResource;

/**
 * Changes the church's settings (docs/08, Update Settings).
 */
class UpdateChurchSettingsController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly UpdateChurchSettingsAction $action) {}

    /**
     * Runs UpdateChurchSettingsAction for this request and returns the ChurchSettingsResource.
     */
    public function __invoke(UpdateChurchSettingsRequest $request): ChurchSettingsResource
    {
        return new ChurchSettingsResource($this->action->execute(UpdateChurchSettingsDTO::fromRequest($request)));
    }
}
