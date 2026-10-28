<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contributions;

use App\Actions\Contributions\RecordCashContributionAction;
use App\DTOs\Contributions\RecordCashContributionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contributions\StoreContributionRequest;
use App\Http\Resources\ContributionResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a cash gift (docs/08, Record Cash Contribution). Needs an Idempotency-Key header, so a double click records one gift.
 */
class StoreContributionController extends Controller
{
    /**
     * Takes the record action.
     */
    public function __construct(private readonly RecordCashContributionAction $action) {}

    /**
     * Runs the action for this request and returns the JsonResponse.
     */
    public function __invoke(StoreContributionRequest $request): JsonResponse
    {
        $contribution = $this->action->execute(RecordCashContributionDTO::fromRequest($request));

        return (new ContributionResource($contribution))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
