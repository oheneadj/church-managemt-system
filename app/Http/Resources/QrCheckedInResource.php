<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Attendance\QrCheckedIn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The confirmation a member sees after checking in by QR code: only their first name and the service, nothing else.
 *
 * @property QrCheckedIn $resource
 */
class QrCheckedInResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Shapes the confirmation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'checked_in' => true,
            'already_checked_in' => $this->resource->alreadyCheckedIn,
            'service_name' => $this->resource->service->name,
            'first_name' => $this->resource->member->preferred_name ?? $this->resource->member->first_name,
        ];
    }
}
