<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Church;

use App\Http\Controllers\Controller;
use App\Http\Requests\Church\ShowChurchSettingsRequest;
use App\Http\Resources\ChurchSettingsResource;
use LogicException;

/**
 * The signed-in church's settings (docs/08, Get Settings). It only reads the church the signed-in user belongs to, so there
 * is no layer below the request to call.
 */
class ShowChurchSettingsController extends Controller
{
    /**
     * Returns the settings of the church the signed-in staff member belongs to.
     */
    public function __invoke(ShowChurchSettingsRequest $request): ChurchSettingsResource
    {
        return new ChurchSettingsResource($request->user()->church ?? throw new LogicException('A staff member always belongs to a church.'));
    }
}
