<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\ServiceSchedules;

use App\Actions\ServiceSchedules\ListServiceSchedulesAction;
use App\DTOs\ServiceSchedules\ListServiceSchedulesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceSchedules\ListServiceSchedulesRequest;
use App\Http\Resources\ServiceScheduleCollection;

/**
 * One page of weekly schedules (docs/08, List Service Schedules).
 */
class ListServiceSchedulesController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ListServiceSchedulesAction $action) {}

    /**
     * Runs ListServiceSchedulesAction for this request and returns the ServiceScheduleCollection.
     */
    public function __invoke(ListServiceSchedulesRequest $request): ServiceScheduleCollection
    {
        return new ServiceScheduleCollection($this->action->execute(ListServiceSchedulesDTO::fromRequest($request)));
    }
}
