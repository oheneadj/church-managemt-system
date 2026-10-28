<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Actions\Attendance\QrCheckInAction;
use App\DTOs\Attendance\QrCheckInDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\QrCheckInRequest;
use App\Http\Resources\QrCheckedInResource;

/**
 * Checks a member in by QR code once their texted code is right (docs/08, QR Check-in).
 */
class QrCheckInController extends Controller
{
    /**
     * Takes the QR check-in action.
     */
    public function __construct(private readonly QrCheckInAction $action) {}

    /**
     * Runs QrCheckInAction for this request and returns the QrCheckedInResource.
     */
    public function __invoke(QrCheckInRequest $request): QrCheckedInResource
    {
        return new QrCheckedInResource($this->action->execute(QrCheckInDTO::fromRequest($request)));
    }
}
