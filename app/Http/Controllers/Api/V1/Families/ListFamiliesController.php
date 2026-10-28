<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Families;

use App\Actions\Families\ListFamiliesAction;
use App\DTOs\Families\ListFamiliesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Families\ListFamiliesRequest;
use App\Http\Resources\FamilyResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * One page of families (docs/08, List Families).
 */
class ListFamiliesController extends Controller
{
    public function __construct(private readonly ListFamiliesAction $action) {}

    public function __invoke(ListFamiliesRequest $request): AnonymousResourceCollection
    {
        return FamilyResource::collection($this->action->execute(ListFamiliesDTO::fromRequest($request)));
    }
}
