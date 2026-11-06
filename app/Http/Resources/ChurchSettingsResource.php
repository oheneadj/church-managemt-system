<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Church;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The church's settings (docs/08, Church Settings): every setting, with the church's own choice or the default.
 *
 * @mixin Church
 */
class ChurchSettingsResource extends JsonResource
{
    /**
     * Shapes the settings for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->settingsWithDefaults();
    }
}
