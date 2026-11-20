<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The answer to "start an export": its id and what happens next (docs/08, Exports).
 *
 * @mixin Export
 */
class ExportQueuedResource extends JsonResource
{
    /** The answer is flat, not wrapped in `data`. */
    public static $wrap = null;

    /**
     * Shapes the answer for the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'export_id' => $this->id,
            'message' => 'Export queued. You will receive a download link by email when it is ready.',
        ];
    }
}
