<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ServiceRepositoryInterface;
use RuntimeException;

/**
 * Makes a short random check-in code that no service uses yet (docs/07). It tries a few times, because a clash is
 * rare. The unique index in the database is still what guarantees uniqueness if two codes are made at once.
 */
class CheckinCodeGenerator
{
    /**
     * Takes the service repository.
     */
    public function __construct(private readonly ServiceRepositoryInterface $services) {}

    /**
     * A free check-in code of the configured length.
     */
    public function next(): string
    {
        for ($attempt = 0; $attempt < config('attendance.checkin_code_attempts'); $attempt++) {
            $code = $this->random();

            if (! $this->services->checkinCodeExists($code)) {
                return $code;
            }
        }

        throw new RuntimeException('Could not find a free check-in code.');
    }

    /**
     * One random code from the allowed letters and numbers.
     */
    private function random(): string
    {
        $alphabet = (string) config('attendance.checkin_code_alphabet');

        return collect(range(1, config('attendance.checkin_code_length')))
            ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->implode('');
    }
}
