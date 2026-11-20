<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when the web address a new church asked for is already used. It carries a few free alternatives so
 * the message can offer them (QA-03 TC-03-02).
 */
class ChurchSlugTakenException extends Exception
{
    /**
     * Keeps the free alternatives to suggest.
     *
     * @param  list<string>  $suggestions
     */
    public function __construct(public readonly array $suggestions = [])
    {
        parent::__construct('That web address is taken.');
    }

    /**
     * Answers 422 on the slug field, in the same shape as other validation errors.
     */
    public function render(): JsonResponse
    {
        $message = 'This web address is already taken.';

        if ($this->suggestions !== []) {
            $message .= ' Try '.implode(' or ', $this->suggestions).'.';
        }

        return response()->json(['message' => $message, 'errors' => ['slug' => [$message]]], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
