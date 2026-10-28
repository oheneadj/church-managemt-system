<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ShowCurrentStaffAction;
use App\DTOs\Auth\ShowCurrentStaffDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentStaffResource;
use Illuminate\Http\Request;

/**
 * Returns the signed-in staff member and their church. The browser app calls this on load to
 * restore its state after a refresh, so it needs the church as well as the user.
 */
class MeController extends Controller
{
    public function __construct(private readonly ShowCurrentStaffAction $action) {}

    public function __invoke(Request $request): CurrentStaffResource
    {
        return new CurrentStaffResource($this->action->execute(ShowCurrentStaffDTO::fromRequest($request)));
    }
}
