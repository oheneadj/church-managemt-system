<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Actions\Platform\ListSmsPackagesAction;
use App\DTOs\Platform\ListSmsPackagesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ListSmsPackagesRequest;
use App\Http\Resources\SmsPackageCollection;

/**
 * One page of the SMS credit packages for the platform admin (docs/08, Platform).
 */
class ListSmsPackagesController extends Controller
{
    /**
     * Takes the list action.
     */
    public function __construct(private readonly ListSmsPackagesAction $action) {}

    /**
     * Runs ListSmsPackagesAction for this request and returns the SmsPackageCollection.
     */
    public function __invoke(ListSmsPackagesRequest $request): SmsPackageCollection
    {
        return new SmsPackageCollection($this->action->execute(ListSmsPackagesDTO::fromRequest($request)));
    }
}
