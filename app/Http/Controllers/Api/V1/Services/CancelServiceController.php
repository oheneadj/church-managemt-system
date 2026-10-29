<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Services;

use App\Actions\Services\CancelServiceAction;
use App\DTOs\Services\CancelServiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Services\CancelServiceRequest;
use Illuminate\Http\Response;

/**
 * Cancels a service nothing has been recorded at (docs/08, Cancel Service). A service of another church is not found (404).
 */
class CancelServiceController extends Controller
{
    /**
     * Takes the cancel service action.
     */
    public function __construct(private readonly CancelServiceAction $action) {}

    /**
     * Runs CancelServiceAction for this request and answers 204 with no body.
     */
    public function __invoke(CancelServiceRequest $request): Response
    {
        $this->action->execute(CancelServiceDTO::fromRequest($request));

        return response()->noContent();
    }
}
