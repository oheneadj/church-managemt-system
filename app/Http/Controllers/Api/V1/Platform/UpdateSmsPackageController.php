<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Actions\Platform\UpdateSmsPackageAction;
use App\DTOs\Platform\UpdateSmsPackageDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateSmsPackageRequest;
use App\Http\Resources\SmsPackageResource;

/**
 * Changes an SMS credit package, or switches it off (docs/08, Platform).
 */
class UpdateSmsPackageController extends Controller
{
    /**
     * Takes the update action.
     */
    public function __construct(private readonly UpdateSmsPackageAction $action) {}

    /**
     * Runs UpdateSmsPackageAction for this request and returns the package.
     */
    public function __invoke(UpdateSmsPackageRequest $request): SmsPackageResource
    {
        return new SmsPackageResource($this->action->execute(UpdateSmsPackageDTO::fromRequest($request)));
    }
}
