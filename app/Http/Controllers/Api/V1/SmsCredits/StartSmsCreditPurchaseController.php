<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SmsCredits;

use App\Actions\SmsCredits\StartSmsCreditPurchaseAction;
use App\DTOs\SmsCredits\StartSmsCreditPurchaseDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\SmsCredits\StartSmsCreditPurchaseRequest;
use App\Http\Resources\SmsCreditPurchaseResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starts a purchase of SMS credits (docs/08, Start a Credit Purchase). The answer is 202: a worker asks the provider to send the
 * payment prompt. Needs an Idempotency-Key header, so a double click starts one purchase.
 */
class StartSmsCreditPurchaseController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly StartSmsCreditPurchaseAction $action) {}

    /**
     * Runs StartSmsCreditPurchaseAction for this request and returns the pending purchase.
     */
    public function __invoke(StartSmsCreditPurchaseRequest $request): JsonResponse
    {
        $purchase = $this->action->execute(StartSmsCreditPurchaseDTO::fromRequest($request));

        return (new SmsCreditPurchaseResource($purchase))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
