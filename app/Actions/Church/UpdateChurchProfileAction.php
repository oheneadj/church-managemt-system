<?php

declare(strict_types=1);

namespace App\Actions\Church;

use App\DTOs\Church\UpdateChurchProfileDTO;
use App\Events\ChurchUpdated;
use App\Models\Church;
use App\Repositories\ChurchRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Changes the church's own details (docs/08, Update Church Profile). Only the details sent are changed. What really changed is
 * written to the history with its old and new values, and nothing is saved or written when nothing changed.
 */
final class UpdateChurchProfileAction
{
    /**
     * Takes the church repository.
     */
    public function __construct(private readonly ChurchRepositoryInterface $churches) {}

    /**
     * Applies the change and returns the church.
     */
    public function execute(UpdateChurchProfileDTO $dto): Church
    {
        return DB::transaction(function () use ($dto): Church {
            $church = $dto->church;
            $old = [];
            $new = [];

            foreach ($dto->details as $field => $value) {
                if ($church->{$field} !== $value) {
                    $old[$field] = $church->{$field};
                    $new[$field] = $value;
                }
            }

            if ($new === []) {
                return $church;
            }

            $church->fill($new);
            $this->churches->save($church);
            ChurchUpdated::dispatch($church, $dto->actor, $old, $new);

            return $church;
        });
    }
}
