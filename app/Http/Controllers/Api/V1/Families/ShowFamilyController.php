<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Families;

use App\Http\Controllers\Controller;
use App\Http\Resources\FamilyResource;
use App\Models\Family;
use App\Services\FamilyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * One family with the members the caller may see (docs/08, Get Family). A family of another church is not found (404).
 */
class ShowFamilyController extends Controller
{
    public function __construct(
        private readonly FamilyService $families,
    ) {}

    public function __invoke(Request $request, Family $family): FamilyResource
    {
        Gate::authorize('view', $family);

        return new FamilyResource($this->families->withMembers($family, $this->staff($request)));
    }
}
