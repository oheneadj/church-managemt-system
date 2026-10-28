<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Families;

use App\Actions\Families\CreateFamilyAction;
use App\DTOs\Families\CreateFamilyDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Families\StoreFamilyRequest;
use App\Http\Resources\FamilyResource;
use Illuminate\Http\JsonResponse;

/**
 * Creates a family, optionally with its first head (docs/08, Create Family).
 */
class StoreFamilyController extends Controller
{
    /**
     * Takes the create family action.
     */
    public function __construct(private readonly CreateFamilyAction $action) {}

    /**
     * Runs CreateFamilyAction for this request and returns the JsonResponse.
     */
    public function __invoke(StoreFamilyRequest $request): JsonResponse
    {
        $family = $this->action->execute(CreateFamilyDTO::fromRequest($request));

        return (new FamilyResource($family))->response()->setStatusCode(201);
    }
}
