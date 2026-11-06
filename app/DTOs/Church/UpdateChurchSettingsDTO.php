<?php

declare(strict_types=1);

namespace App\DTOs\Church;

use App\DTOs\Concerns\ResolvesActor;
use App\Http\Requests\Church\UpdateChurchSettingsRequest;
use App\Models\Church;
use App\Models\User;
use LogicException;

/**
 * A change to the church's settings: the church, the admin and the settings sent, as numbers and true or false.
 */
final readonly class UpdateChurchSettingsDTO
{
    use ResolvesActor;

    /**
     * Holds the admin, their church and the settings to set.
     *
     * @param  array<string, int|bool>  $settings
     */
    public function __construct(public User $actor, public Church $church, public array $settings) {}

    /**
     * Builds the DTO from the validated request.
     */
    public static function fromRequest(UpdateChurchSettingsRequest $request): self
    {
        $sent = $request->validated()['settings'];
        $settings = [];

        foreach (['youth_age_limit', 'absence_threshold'] as $number) {
            if (isset($sent[$number])) {
                $settings[$number] = (int) $sent[$number];
            }
        }

        if (isset($sent['receipt_on_cash'])) {
            $settings['receipt_on_cash'] = $request->boolean('settings.receipt_on_cash');
        }

        $actor = self::actorFrom($request);

        return new self($actor, $actor->church ?? throw new LogicException('A staff member always belongs to a church.'), $settings);
    }
}
