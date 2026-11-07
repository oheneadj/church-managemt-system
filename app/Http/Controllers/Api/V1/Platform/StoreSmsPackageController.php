<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Actions\Platform\CreateSmsPackageAction;
use App\DTOs\Platform\CreateSmsPackageDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSmsPackageRequest;
use App\Http\Resources\SmsPackageResource;
use Illuminate\Http\JsonResponse;

/**
 * Adds an SMS credit package (docs/08, Platform).
 */
class StoreSmsPackageController extends Controller
{
    /**
     * Takes the create action.
     */
    public function __construct(private readonly CreateSmsPackageAction $action) {}

    /**
     * Runs CreateSmsPackageAction for this request and returns the new package with 201.
     */
    public function __invoke(StoreSmsPackageRequest $request): JsonResponse
    {
        return (new SmsPackageResource($this->action->execute(CreateSmsPackageDTO::fromRequest($request))))->response()->setStatusCode(201);
    }
}
