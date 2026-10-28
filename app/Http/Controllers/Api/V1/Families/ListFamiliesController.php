<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Families;

use App\Http\Controllers\Controller;
use App\Http\Requests\Families\ListFamiliesRequest;
use App\Http\Resources\FamilyResource;
use App\Models\Family;
use App\Services\FamilyService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * One page of families (docs/08, List Families).
 */
class ListFamiliesController extends Controller
{
    public function __construct(
        private readonly FamilyService $families,
    ) {}

    public function __invoke(ListFamiliesRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Family::class);

        return FamilyResource::collection($this->families->paginate($this->staff($request), $request->validated()));
    }
}
