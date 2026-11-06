<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A page of messages, each shaped by MessageLogResource, with the standard pagination meta and links.
 */
class MessageLogCollection extends ResourceCollection
{
    /** @var class-string */
    public $collects = MessageLogResource::class;
}
