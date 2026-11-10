<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Giving;

use App\Actions\Giving\StartPublicGiftAction;
use App\DTOs\Giving\StartPublicGiftDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Giving\StartPublicGiftRequest;
use App\Http\Resources\PublicGiftResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Starts a mobile money gift for anyone on the church's public giving page (docs/08, The Public Giving Page). Public: no sign-in.
 * The answer is 202: a worker asks the provider to send the payment prompt. Needs an Idempotency-Key header, so a double tap starts
 * one payment.
 */
class StartPublicGiftController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly StartPublicGiftAction $action) {}

    /**
     * Runs StartPublicGiftAction for this request and returns the started gift.
     */
    public function __invoke(StartPublicGiftRequest $request): JsonResponse
    {
        $gift = $this->action->execute(StartPublicGiftDTO::fromRequest($request));

        return (new PublicGiftResource($gift))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
