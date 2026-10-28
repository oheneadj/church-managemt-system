<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of gifts, each shaped by ContributionResource, with the standard pagination meta and links.
 */
class ContributionCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = ContributionResource::class;
}
