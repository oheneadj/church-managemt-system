<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Giving;

use App\Actions\Giving\ShowGivingPageAction;
use App\DTOs\Giving\ShowGivingPageDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Giving\ShowGivingPageRequest;
use App\Http\Resources\GivingPageResource;

/**
 * What the public giving page shows (docs/08, The Public Giving Page). Public: no sign-in, the church is the one the address names.
 */
class ShowGivingPageController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ShowGivingPageAction $action) {}

    /**
     * Runs ShowGivingPageAction and returns the GivingPageResource.
     */
    public function __invoke(ShowGivingPageRequest $request): GivingPageResource
    {
        return new GivingPageResource($this->action->execute(ShowGivingPageDTO::fromRequest()));
    }
}
