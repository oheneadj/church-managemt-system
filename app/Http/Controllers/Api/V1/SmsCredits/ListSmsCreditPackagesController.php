<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SmsCredits;

use App\Actions\SmsCredits\ListActiveSmsPackagesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SmsCredits\ListSmsCreditPackagesRequest;
use App\Http\Resources\SmsCreditPackageResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The SMS credit packages a church can buy (docs/08, SMS Credits).
 */
class ListSmsCreditPackagesController extends Controller
{
    /**
     * Takes the list action.
     */
    public function __construct(private readonly ListActiveSmsPackagesAction $action) {}

    /**
     * Runs ListActiveSmsPackagesAction and returns the packages.
     */
    public function __invoke(ListSmsCreditPackagesRequest $request): AnonymousResourceCollection
    {
        return SmsCreditPackageResource::collection($this->action->execute());
    }
}
