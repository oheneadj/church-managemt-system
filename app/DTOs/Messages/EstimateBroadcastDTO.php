<?php

declare(strict_types=1);

namespace App\DTOs\Messages;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Messages\EstimateBroadcastRequest;
use App\Models\Church;
use LogicException;

/**
 * A question about a broadcast: the church (for its credits), the text and who it is for.
 */
final readonly class EstimateBroadcastDTO
{
    use ResolvesActor;

    /**
     * Holds the church, the text and the audience.
     */
    public function __construct(public Church $church, public string $message, public BroadcastAudience $audience) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(EstimateBroadcastRequest $request): self
    {
        $v = $request->validated();

        return new self(
            self::actorFrom($request)->church ?? throw new LogicException('A staff member always belongs to a church.'),
            $v['message'],
            BroadcastAudience::fromFilter($v['recipient_filter'] ?? []),
        );
    }
}
