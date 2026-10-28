<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of members who missed the last few services in a row, with the standard pagination meta and links.
 */
class AbsentMemberCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = AbsentMemberResource::class;
}
