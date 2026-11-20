<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Church;

use App\Http\Controllers\Controller;
use App\Http\Requests\Church\ShowPayoutAccountRequest;
use App\Http\Resources\PayoutAccountResource;
use LogicException;

/**
 * Where the signed-in church's gifts are paid out, and whether the provider has named the account (docs/08, Payout Account).
 * It only reads the church the signed-in user belongs to, so there is no layer below the request to call.
 */
class ShowPayoutAccountController extends Controller
{
    /**
     * Returns the payout account of the church the signed-in admin belongs to.
     */
    public function __invoke(ShowPayoutAccountRequest $request): PayoutAccountResource
    {
        return new PayoutAccountResource($request->user()->church ?? throw new LogicException('A staff member always belongs to a church.'));
    }
}
