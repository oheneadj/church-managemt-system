<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of families, each shaped by FamilyResource, with the standard pagination meta and links.
 */
class FamilyCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = FamilyResource::class;
}
