<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a member who scanned the QR code is told about the service: its name, date and whether check-in is open.
 * Nothing about attendance or other people is included.
 *
 * @mixin Service
 */
class QrServiceResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Shapes the service for the public page.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'type_label' => $this->type->label(),
            'service_date' => $this->service_date->toDateString(),
            'is_open' => $this->is_open,
        ];
    }
}
