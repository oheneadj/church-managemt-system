<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pledges;

use App\Actions\Pledges\CreatePledgeAction;
use App\DTOs\Pledges\CreatePledgeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pledges\StorePledgeRequest;
use App\Http\Resources\PledgeResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a pledge (docs/08, Create Pledge). Needs an Idempotency-Key header, so a double click makes one pledge.
 */
class StorePledgeController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly CreatePledgeAction $action) {}

    /**
     * Runs CreatePledgeAction for this request and returns the JsonResponse.
     */
    public function __invoke(StorePledgeRequest $request): JsonResponse
    {
        $pledge = $this->action->execute(CreatePledgeDTO::fromRequest($request));

        return (new PledgeResource($pledge))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
