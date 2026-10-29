<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ServiceSchedules;

use App\Actions\ServiceSchedules\CreateServiceScheduleAction;
use App\DTOs\ServiceSchedules\CreateServiceScheduleDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceSchedules\StoreServiceScheduleRequest;
use App\Http\Resources\ServiceScheduleResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets up a weekly schedule and makes its first weeks of services (docs/08, Create Service Schedule). Needs an Idempotency-Key header.
 */
class StoreServiceScheduleController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly CreateServiceScheduleAction $action) {}

    /**
     * Runs CreateServiceScheduleAction for this request and returns the JsonResponse.
     */
    public function __invoke(StoreServiceScheduleRequest $request): JsonResponse
    {
        $schedule = $this->action->execute(CreateServiceScheduleDTO::fromRequest($request));

        return (new ServiceScheduleResource($schedule))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
