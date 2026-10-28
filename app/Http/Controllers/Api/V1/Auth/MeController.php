<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChurchResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * Returns the signed-in staff member and their church. The browser app calls this on load to
 * restore its state after a refresh, so it needs the church as well as the user.
 */
class MeController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        return (new UserResource($request->user()))
            ->additional(['church' => new ChurchResource(app('current.church'))]);
    }
}
