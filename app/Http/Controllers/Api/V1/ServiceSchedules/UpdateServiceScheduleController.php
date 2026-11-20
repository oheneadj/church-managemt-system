<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ServiceSchedules;

use App\Actions\ServiceSchedules\UpdateServiceScheduleAction;
use App\DTOs\ServiceSchedules\UpdateServiceScheduleDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceSchedules\UpdateServiceScheduleRequest;
use App\Http\Resources\ServiceScheduleResource;

/**
 * Changes a weekly schedule and its future services (docs/08, Update Service Schedule). A schedule of another church is not found (404).
 */
class UpdateServiceScheduleController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly UpdateServiceScheduleAction $action) {}

    /**
     * Runs UpdateServiceScheduleAction for this request and returns the ServiceScheduleResource.
     */
    public function __invoke(UpdateServiceScheduleRequest $request): ServiceScheduleResource
    {
        return new ServiceScheduleResource($this->action->execute(UpdateServiceScheduleDTO::fromRequest($request)));
    }
}
