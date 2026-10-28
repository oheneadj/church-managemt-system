<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of members, each shaped by MemberSummaryResource, with the standard pagination meta and links.
 */
class MemberCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = MemberSummaryResource::class;
}
