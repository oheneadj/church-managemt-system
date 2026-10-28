<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Member;
use App\Services\MemberPhotoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The reply to a photo upload: just the new signed link, not wrapped in "data" (docs/08, Upload Member Photo).
 *
 * @mixin Member
 */
class MemberPhotoResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Shapes the member photo for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string|null */
            'photo_url' => app(MemberPhotoService::class)->url($this->photo_url),
        ];
    }
}
