<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\DTOs\WhatsApp\CheckinCommand;

/**
 * Reads a member's message and says whether it is a CHECKIN, and for which service code. Capitals, spaces and
 * anything after the code do not matter: "checkin", " CHECKIN gra7f please" and "CHECKIN GRA7F" all work.
 */
class CheckinCommandParser
{
    /**
     * The command in the message, or null when the message is something else and should be ignored.
     */
    public function parse(string $text): ?CheckinCommand
    {
        $words = preg_split('/\s+/', strtoupper(trim($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (($words[0] ?? null) !== config('whatsapp.command')) {
            return null;
        }

        return new CheckinCommand($words[1] ?? null);
    }
}
