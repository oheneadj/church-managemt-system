<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contributions;

use App\Actions\Contributions\InitiateContributionAction;
use App\DTOs\Contributions\InitiateContributionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contributions\InitiateContributionRequest;
use App\Http\Resources\InitiatedContributionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Starts a mobile money gift (docs/08, Initiate MoMo / Card Payment). The answer is 202: a worker asks the provider to send the
 * payment prompt. Needs an Idempotency-Key header, so a double click starts one payment.
 */
class InitiateContributionController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly InitiateContributionAction $action) {}

    /**
     * Runs InitiateContributionAction for this request and returns the pending gift.
     */
    public function __invoke(InitiateContributionRequest $request): JsonResponse
    {
        $gift = $this->action->execute(InitiateContributionDTO::fromRequest($request));

        return (new InitiatedContributionResource($gift))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
