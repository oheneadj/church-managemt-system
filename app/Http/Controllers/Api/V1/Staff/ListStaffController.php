<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Staff\ListStaffAction;
use App\DTOs\Staff\ListStaffDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ListStaffRequest;
use App\Http\Resources\StaffCollection;

/**
 * One page of the church's staff (docs/08, Staff).
 */
class ListStaffController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ListStaffAction $action) {}

    /**
     * Runs ListStaffAction for this request and returns the StaffCollection.
     */
    public function __invoke(ListStaffRequest $request): StaffCollection
    {
        return new StaffCollection($this->action->execute(ListStaffDTO::fromRequest($request)));
    }
}
