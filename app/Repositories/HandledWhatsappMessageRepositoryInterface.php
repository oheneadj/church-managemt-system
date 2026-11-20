<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Every query on the list of WhatsApp messages already handled lives behind this contract.
 */
interface HandledWhatsappMessageRepositoryInterface
{
    /**
     * Marks Meta's message as handled. Returns false when it was already marked, so a repeat delivery is ignored.
     * It is one insert that ignores a clash, so two deliveries at once cannot both win.
     */
    public function claim(string $messageId): bool;
}
