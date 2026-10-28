<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Actions\Attendance\ShowQrServiceAction;
use App\DTOs\Attendance\ShowQrServiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ShowQrServiceRequest;
use App\Http\Resources\QrServiceResource;

/**
 * Which service a scanned code is for (docs/08, Scan QR Code). Public; an expired or unknown code is a 404.
 */
class ShowQrServiceController extends Controller
{
    /**
     * Takes the show QR service action.
     */
    public function __construct(private readonly ShowQrServiceAction $action) {}

    /**
     * Runs ShowQrServiceAction for this request and returns the QrServiceResource.
     */
    public function __invoke(ShowQrServiceRequest $request): QrServiceResource
    {
        return new QrServiceResource($this->action->execute(ShowQrServiceDTO::fromRequest($request)));
    }
}
