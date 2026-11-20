<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\FundTypes;

use App\Actions\FundTypes\CreateFundTypeAction;
use App\DTOs\FundTypes\CreateFundTypeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\FundTypes\StoreFundTypeRequest;
use App\Http\Resources\FundTypeResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a fund type (docs/08, Create Fund Type).
 */
class StoreFundTypeController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly CreateFundTypeAction $action) {}

    /**
     * Runs CreateFundTypeAction for this request and returns the JsonResponse.
     */
    public function __invoke(StoreFundTypeRequest $request): JsonResponse
    {
        $fundType = $this->action->execute(CreateFundTypeDTO::fromRequest($request));

        return (new FundTypeResource($fundType))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
