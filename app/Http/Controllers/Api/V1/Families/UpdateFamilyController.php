<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Families;

use App\Http\Controllers\Controller;
use App\Http\Requests\Families\UpdateFamilyRequest;
use App\Http\Resources\FamilyResource;
use App\Models\Family;
use App\Services\FamilyService;
use Illuminate\Support\Facades\Gate;

/**
 * Renames a family or edits its notes (docs/08, Update Family).
 */
class UpdateFamilyController extends Controller
{
    public function __construct(
        private readonly FamilyService $families,
    ) {}

    public function __invoke(UpdateFamilyRequest $request, Family $family): FamilyResource
    {
        Gate::authorize('update', $family);

        return new FamilyResource($this->families->update($this->staff($request), $family, $request->validated()));
    }
}
