<?php

declare(strict_types=1);

namespace App\DTOs\Messages;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Messages\SendBroadcastRequest;
use App\Models\Church;
use App\Models\User;
use LogicException;

/**
 * A broadcast to send: who sends it, the church, the text, who it is for and the filter as sent (kept on the record).
 */
final readonly class SendBroadcastDTO
{
    use ResolvesActor;

    /**
     * Holds the sender, the church, the text, the audience and the filter as sent.
     *
     * @param  array<string, string>  $filter
     */
    public function __construct(
        public User $actor,
        public Church $church,
        public string $message,
        public BroadcastAudience $audience,
        public array $filter,
    ) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(SendBroadcastRequest $request): self
    {
        $v = $request->validated();
        $actor = self::actorFrom($request);
        $filter = $v['recipient_filter'] ?? [];

        return new self($actor, $actor->church ?? throw new LogicException('A staff member always belongs to a church.'), $v['message'], BroadcastAudience::fromFilter($filter), $filter);
    }
}
