<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of services, each shaped by ServiceResource, with the standard pagination meta and links.
 */
class ServiceCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = ServiceResource::class;
}
