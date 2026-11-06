<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Actions\Staff\InviteStaffAction;
use App\DTOs\Staff\InviteStaffDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\InviteStaffRequest;
use App\Http\Resources\StaffResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invites a staff member (docs/08, Staff). Needs an Idempotency-Key header, so a double click sends one invitation.
 */
class InviteStaffController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly InviteStaffAction $action) {}

    /**
     * Runs InviteStaffAction for this request and returns the JsonResponse.
     */
    public function __invoke(InviteStaffRequest $request): JsonResponse
    {
        $staff = $this->action->execute(InviteStaffDTO::fromRequest($request));

        return (new StaffResource($staff))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
