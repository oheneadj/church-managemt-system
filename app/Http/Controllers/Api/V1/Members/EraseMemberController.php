<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Members;

use App\Actions\Members\EraseMemberAction;
use App\DTOs\Members\EraseMemberDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\EraseMemberRequest;
use Illuminate\Http\Response;

/**
 * Erases a member's personal data at their request (docs/08, Erase Member). It cannot be undone.
 */
class EraseMemberController extends Controller
{
    /**
     * Takes the erase member action.
     */
    public function __construct(private readonly EraseMemberAction $action) {}

    /**
     * Runs EraseMemberAction for this request and answers 204.
     */
    public function __invoke(EraseMemberRequest $request): Response
    {
        $this->action->execute(EraseMemberDTO::fromRequest($request));

        return response()->noContent();
    }
}
