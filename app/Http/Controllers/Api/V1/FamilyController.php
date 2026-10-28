<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Families\ListFamiliesRequest;
use App\Http\Requests\Families\StoreFamilyRequest;
use App\Http\Requests\Families\UpdateFamilyRequest;
use App\Http\Resources\FamilyResource;
use App\Models\Family;
use App\Services\FamilyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The family endpoints (docs/08, Families). A family of another church is not found at all (404).
 */
class FamilyController extends Controller
{
    public function __construct(private readonly FamilyService $families) {}

    public function index(ListFamiliesRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Family::class);

        return FamilyResource::collection($this->families->paginate($this->staff($request), $request->validated()));
    }

    public function show(Request $request, Family $family): FamilyResource
    {
        Gate::authorize('view', $family);

        return new FamilyResource($this->families->withMembers($family, $this->staff($request)));
    }

    public function store(StoreFamilyRequest $request): JsonResponse
    {
        Gate::authorize('create', Family::class);

        $family = $this->families->create($this->staff($request), $request->validated());

        return (new FamilyResource($family))->response()->setStatusCode(201);
    }

    public function update(UpdateFamilyRequest $request, Family $family): FamilyResource
    {
        Gate::authorize('update', $family);

        return new FamilyResource($this->families->update($this->staff($request), $family, $request->validated()));
    }
}
