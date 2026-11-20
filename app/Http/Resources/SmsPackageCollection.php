<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of SMS credit packages, each shaped by SmsPackageResource, with the standard pagination meta and links.
 */
class SmsPackageCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = SmsPackageResource::class;
}
