<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of weekly schedules, each shaped by ServiceScheduleResource, with the standard pagination meta and links.
 */
class ServiceScheduleCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = ServiceScheduleResource::class;
}
