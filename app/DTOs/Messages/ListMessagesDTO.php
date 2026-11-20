<?php

declare(strict_types=1);

namespace App\DTOs\Messages;

use App\Http\Requests\Messages\ListMessagesRequest;

/**
 * What the message log is asked for: the filters, the page size, and the message types the church's log leaves out.
 */
final readonly class ListMessagesDTO
{
    /**
     * Holds the page size, the filters and the types to leave out.
     *
     * @param  list<string>  $hiddenTypes
     */
    public function __construct(
        public int $perPage,
        public array $hiddenTypes,
        public ?string $channel = null,
        public ?string $messageType = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(ListMessagesRequest $request): self
    {
        $v = $request->validated();

        return new self(
            (int) ($v['per_page'] ?? config('limits.per_page_default')),
            array_values(config('messages.hidden_types')),
            $v['channel'] ?? null,
            $v['message_type'] ?? null,
            $v['from'] ?? null,
            $v['to'] ?? null,
        );
    }
}
