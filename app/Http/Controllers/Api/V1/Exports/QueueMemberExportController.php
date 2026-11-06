<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Exports;

use App\Actions\Exports\QueueExportAction;
use App\DTOs\Exports\QueueExportDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exports\ExportMembersRequest;
use App\Http\Resources\ExportQueuedResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starts an export of the member list as a CSV (docs/08, Export Members). The answer is 202: the file is built by a worker.
 */
class QueueMemberExportController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly QueueExportAction $action) {}

    /**
     * Runs QueueExportAction for this request and returns the JsonResponse.
     */
    public function __invoke(ExportMembersRequest $request): JsonResponse
    {
        $export = $this->action->execute(QueueExportDTO::forMembers($request));

        return (new ExportQueuedResource($export))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
