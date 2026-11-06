<?php

declare(strict_types=1);

namespace App\Actions\Messages;

use App\DTOs\Messages\BroadcastEstimate;
use App\DTOs\Messages\EstimateBroadcastDTO;
use App\Services\BroadcastEstimator;

/**
 * Answers "who would this broadcast reach and what would it cost?". It reads only, so it needs no transaction.
 */
final class EstimateBroadcastAction
{
    /**
     * Takes the estimator.
     */
    public function __construct(private readonly BroadcastEstimator $estimator) {}

    /**
     * Works out the estimate.
     */
    public function execute(EstimateBroadcastDTO $dto): BroadcastEstimate
    {
        return $this->estimator->estimate($dto->church, $dto->message, $dto->audience);
    }
}
