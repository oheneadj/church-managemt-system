<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of fund types, each shaped by FundTypeResource, with the standard pagination meta and links.
 */
class FundTypeCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = FundTypeResource::class;
}
