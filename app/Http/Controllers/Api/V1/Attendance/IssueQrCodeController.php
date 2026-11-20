<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Actions\Attendance\IssueQrCodeAction;
use App\DTOs\Attendance\IssueQrCodeDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\IssueQrCodeRequest;
use App\Http\Resources\QrCodeResource;

/**
 * A fresh QR code for the screen at the venue (docs/08, Get QR Code). Only while check-in is open.
 */
class IssueQrCodeController extends Controller
{
    /**
     * Takes the issue QR code action.
     */
    public function __construct(private readonly IssueQrCodeAction $action) {}

    /**
     * Runs IssueQrCodeAction for this request and returns the QrCodeResource.
     */
    public function __invoke(IssueQrCodeRequest $request): QrCodeResource
    {
        return new QrCodeResource($this->action->execute(IssueQrCodeDTO::fromRequest($request)));
    }
}
