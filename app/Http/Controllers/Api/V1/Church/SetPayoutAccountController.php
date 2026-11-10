<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Church;

use App\Actions\Church\SetPayoutAccountAction;
use App\DTOs\Church\SetPayoutAccountDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Church\SetPayoutAccountRequest;
use App\Http\Resources\PayoutAccountResource;
use Illuminate\Http\JsonResponse;

/**
 * Saves the church's payout account and queues the provider's check of it (docs/08, Payout Account).
 */
class SetPayoutAccountController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly SetPayoutAccountAction $action) {}

    /**
     * Runs SetPayoutAccountAction for this request and returns the PayoutAccountResource, `202` because the check is still to come.
     */
    public function __invoke(SetPayoutAccountRequest $request): JsonResponse
    {
        $church = $this->action->execute(SetPayoutAccountDTO::fromRequest($request));

        return (new PayoutAccountResource($church))->response()->setStatusCode(202);
    }
}
