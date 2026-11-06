<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Actions\Reports\ShowGivingTrendAction;
use App\DTOs\Reports\GivingTrendDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\GivingTrendRequest;
use App\Http\Resources\GivingTrendResource;

/**
 * The giving trend, month by month and by fund (docs/08, Giving Trends).
 */
class ShowGivingTrendController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ShowGivingTrendAction $action) {}

    /**
     * Runs ShowGivingTrendAction for this request and returns the GivingTrendResource.
     */
    public function __invoke(GivingTrendRequest $request): GivingTrendResource
    {
        return new GivingTrendResource($this->action->execute(GivingTrendDTO::fromRequest($request)));
    }
}
