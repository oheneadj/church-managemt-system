<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\DTOs\Attendance\IssueQrCodeDTO;
use App\DTOs\Attendance\QrCode;
use App\Exceptions\CheckinClosedException;
use App\Services\QrTokenService;

/**
 * Makes a fresh code for the QR display at the venue. A service that is not open for check-in has nothing to scan.
 */
final class IssueQrCodeAction
{
    /**
     * Takes the QR token service.
     */
    public function __construct(private readonly QrTokenService $tokens) {}

    /**
     * Issues the code.
     *
     * @throws CheckinClosedException
     */
    public function execute(IssueQrCodeDTO $dto): QrCode
    {
        if (! $dto->service->is_open) {
            throw new CheckinClosedException;
        }

        return $this->tokens->issue($dto->church, $dto->service);
    }
}
