<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Actions\Onboarding\RegisterChurchAction;
use App\DTOs\Onboarding\RegisterChurchDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\RegisterChurchRequest;
use App\Http\Resources\ChurchRegisteredResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starts a church with its first admin, once the admin's phone is proven with the texted code.
 */
class RegisterChurchController extends Controller
{
    /**
     * Takes the register church action.
     */
    public function __construct(private readonly RegisterChurchAction $action) {}

    /**
     * Runs RegisterChurchAction for this request and returns the ChurchRegisteredResource with 201.
     */
    public function __invoke(RegisterChurchRequest $request): JsonResponse
    {
        $registered = $this->action->execute(RegisterChurchDTO::fromRequest($request));

        return (new ChurchRegisteredResource($registered))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
