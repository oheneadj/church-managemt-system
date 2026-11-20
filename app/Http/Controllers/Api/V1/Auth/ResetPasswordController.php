<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\DTOs\Auth\ResetPasswordDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\Response;

/**
 * Sets a new password with an emailed code. It answers 204: nobody is signed in by it.
 */
class ResetPasswordController extends Controller
{
    /**
     * Takes the reset password action.
     */
    public function __construct(private readonly ResetPasswordAction $action) {}

    /**
     * Runs ResetPasswordAction for this request and returns an empty 204 response.
     */
    public function __invoke(ResetPasswordRequest $request): Response
    {
        $this->action->execute(ResetPasswordDTO::fromRequest($request));

        return response()->noContent();
    }
}
