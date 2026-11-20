<?php

declare(strict_types=1);

namespace App\Actions\Church;

use App\DTOs\Church\UpdateChurchSettingsDTO;
use App\Events\ChurchUpdated;
use App\Models\Church;
use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Changes the church's settings (docs/08, Update Settings). Only the settings sent are changed; the others, including a
 * setting a newer version of the app may have saved, are kept. What really changed (against the value in force before, the
 * church's own or the default) is written to the history, and nothing is saved or written when nothing changed.
 */
final class UpdateChurchSettingsAction
{
    /**
     * Takes the church repository.
     */
    public function __construct(private readonly ChurchRepositoryInterface $churches) {}

    /**
     * Applies the change and returns the church.
     */
    public function execute(UpdateChurchSettingsDTO $dto): Church
    {
        return DB::transaction(function () use ($dto): Church {
            $church = $dto->church;
            $before = $church->settingsWithDefaults();
            $old = [];
            $new = [];

            foreach ($dto->settings as $name => $value) {
                if ($before[$name] !== $value) {
                    $old[$name] = $before[$name];
                    $new[$name] = $value;
                }
            }

            if ($new === []) {
                return $church;
            }

            $church->settings = [...($church->settings ?? []), ...$new];
            $this->churches->save($church);
            ChurchUpdated::dispatch($church, $dto->actor, $old, $new);

            return $church;
        });
    }
}
