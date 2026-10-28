<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Attendance\QrCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The code to show as a QR code at the venue: the address to put in the QR code, and when it stops working.
 *
 * @property QrCode $resource
 */
class QrCodeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Shapes the code. The address is on the church's own host, so a member lands on their church's page.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->token,
            'url' => $request->getSchemeAndHttpHost().config('attendance.qr_page_path').'/'.$this->resource->token,
            'expires_at' => $this->resource->expiresAt->toIso8601String(),
            'expires_in' => (int) config('attendance.qr_ttl_seconds'),
            'refresh_in' => (int) config('attendance.qr_refresh_seconds'),
        ];
    }
}
