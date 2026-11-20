<?php

declare(strict_types=1);

namespace App\Actions\Messages;

use App\DTOs\Messages\ListMessagesDTO;
use App\Models\MessageLog;
use App\Repositories\MessageLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lists one page of the church's message log.
 */
final class ListMessagesAction
{
    /**
     * Takes the message log repository.
     */
    public function __construct(private readonly MessageLogRepositoryInterface $messages) {}

    /**
     * Lists one page of messages.
     *
     * @return LengthAwarePaginator<int, MessageLog>
     */
    public function execute(ListMessagesDTO $dto): LengthAwarePaginator
    {
        return $this->messages->paginate($dto);
    }
}
