<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of departments, each shaped by DepartmentResource, with the standard pagination meta and links.
 */
class DepartmentCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = DepartmentResource::class;
}
