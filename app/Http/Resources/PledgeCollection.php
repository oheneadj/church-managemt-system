<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of pledges, each shaped by PledgeResource, with the standard pagination meta and links.
 */
class PledgeCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = PledgeResource::class;
}
