<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\ListActivityAction;
use App\DTOs\Activity\ListActivityDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\ListActivityRequest;
use App\Http\Resources\ActivityResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * One page of the recent activity feed (docs/08, Recent Activity).
 */
class ListActivityController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ListActivityAction $action) {}

    /**
     * Runs ListActivityAction for this request and returns the lines.
     */
    public function __invoke(ListActivityRequest $request): AnonymousResourceCollection
    {
        return ActivityResource::collection($this->action->execute(ListActivityDTO::fromRequest($request)));
    }
}
