<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SmsCredits;

use App\Http\Controllers\Controller;
use App\Http\Requests\SmsCredits\ShowSmsCreditPurchaseRequest;
use App\Http\Resources\SmsCreditPurchaseResource;

/**
 * Reads one purchase of SMS credits so a screen can follow how the payment went (docs/08, Get a Credit Purchase). Route binding
 * has already found it, so there is no layer below the request to call.
 */
class ShowSmsCreditPurchaseController extends Controller
{
    /**
     * Returns the purchase.
     */
    public function __invoke(ShowSmsCreditPurchaseRequest $request): SmsCreditPurchaseResource
    {
        return new SmsCreditPurchaseResource($request->purchase());
    }
}
