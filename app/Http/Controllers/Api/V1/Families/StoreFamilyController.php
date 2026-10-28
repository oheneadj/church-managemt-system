<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Families;

use App\Http\Controllers\Controller;
use App\Http\Requests\Families\StoreFamilyRequest;
use App\Http\Resources\FamilyResource;
use App\Models\Family;
use App\Services\FamilyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Creates a family, optionally with its first head (docs/08, Create Family).
 */
class StoreFamilyController extends Controller
{
    public function __construct(
        private readonly FamilyService $families,
    ) {}

    public function __invoke(StoreFamilyRequest $request): JsonResponse
    {
        Gate::authorize('create', Family::class);

        $family = $this->families->create($this->staff($request), $request->validated());

        return (new FamilyResource($family))->response()->setStatusCode(201);
    }
}
