<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Church;

use App\Http\Controllers\Controller;
use App\Http\Requests\Church\ShowChurchProfileRequest;
use App\Http\Resources\ChurchProfileResource;
use LogicException;

/**
 * The signed-in church's own details (docs/08, Get Church Profile). It only reads the church the signed-in user belongs to, so
 * there is no layer below the request to call.
 */
class ShowChurchProfileController extends Controller
{
    /**
     * Returns the details of the church the signed-in staff member belongs to.
     */
    public function __invoke(ShowChurchProfileRequest $request): ChurchProfileResource
    {
        return new ChurchProfileResource($request->user()->church ?? throw new LogicException('A staff member always belongs to a church.'));
    }
}
