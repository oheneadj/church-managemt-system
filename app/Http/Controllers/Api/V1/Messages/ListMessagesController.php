<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Messages;

use App\Actions\Messages\ListMessagesAction;
use App\DTOs\Messages\ListMessagesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messages\ListMessagesRequest;
use App\Http\Resources\MessageLogCollection;

/**
 * One page of the church's message log (docs/08, Get Message Log).
 */
class ListMessagesController extends Controller
{
    /**
     * Takes the action this controller runs.
     */
    public function __construct(private readonly ListMessagesAction $action) {}

    /**
     * Runs ListMessagesAction for this request and returns the MessageLogCollection.
     */
    public function __invoke(ListMessagesRequest $request): MessageLogCollection
    {
        return new MessageLogCollection($this->action->execute(ListMessagesDTO::fromRequest($request)));
    }
}
