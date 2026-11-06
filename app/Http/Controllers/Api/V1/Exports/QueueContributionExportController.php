<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Exports;

use App\Actions\Exports\QueueExportAction;
use App\DTOs\Exports\QueueExportDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exports\ExportContributionsRequest;
use App\Http\Resources\ExportQueuedResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starts an export of the giving records as a CSV (docs/08, Export Contributions). The answer is 202: a worker builds the file.
 */
class QueueContributionExportController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly QueueExportAction $action) {}

    /**
     * Runs QueueExportAction for this request and returns the JsonResponse.
     */
    public function __invoke(ExportContributionsRequest $request): JsonResponse
    {
        $export = $this->action->execute(QueueExportDTO::forContributions($request));

        return (new ExportQueuedResource($export))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
