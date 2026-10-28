<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Actions\Attendance\CaptureVisitorAction;
use App\DTOs\Attendance\CaptureVisitorDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\CaptureVisitorRequest;
use App\Http\Resources\VisitorResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a first-time or returning visitor at a service (docs/08, Capture Visitor). It answers 201 for a new visitor and for one matched by phone; `visit_count` shows which.
 */
class CaptureVisitorController extends Controller
{
    /**
     * Takes the capture visitor action.
     */
    public function __construct(private readonly CaptureVisitorAction $action) {}

    /**
     * Runs CaptureVisitorAction for this request and returns the VisitorResource.
     */
    public function __invoke(CaptureVisitorRequest $request): JsonResponse
    {
        return (new VisitorResource($this->action->execute(CaptureVisitorDTO::fromRequest($request))))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
