<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\DTOs\Attendance\ShowQrServiceDTO;
use App\Exceptions\InvalidQrCodeException;
use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;
use App\Services\QrTokenService;

/**
 * Tells a member who has just scanned the screen which service the code is for, so the page can say it.
 */
final class ShowQrServiceAction
{
    /**
     * Takes the QR token service and the service repository.
     */
    public function __construct(private readonly QrTokenService $tokens, private readonly ServiceRepositoryInterface $services) {}

    /**
     * Finds the service for the code.
     *
     * @throws InvalidQrCodeException
     */
    public function execute(ShowQrServiceDTO $dto): Service
    {
        $serviceId = $this->tokens->serviceIdFor($dto->church, $dto->token);

        return ($serviceId === null ? null : $this->services->find($serviceId)) ?? throw new InvalidQrCodeException;
    }
}
