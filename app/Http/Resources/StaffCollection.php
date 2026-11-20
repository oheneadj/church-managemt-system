<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of staff, each shaped by StaffResource, with the standard pagination meta and links.
 */
class StaffCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = StaffResource::class;
}
