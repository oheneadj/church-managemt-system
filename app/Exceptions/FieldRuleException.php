<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base for a business rule that one request field breaks. It answers 422 in the same shape as a failed
 * validation (message plus errors keyed by field), so the web app handles both the same way.
 */
abstract class FieldRuleException extends Exception
{
    /**
     * Takes the request field that breaks the rule and the sentence to show for it.
     */
    public function __construct(private readonly string $field, string $message)
    {
        parent::__construct($message);
    }

    /**
     * Answers 422 with the message and the field it belongs to.
     */
    public function render(): JsonResponse
    {
        return response()->json(
            ['message' => $this->getMessage(), 'errors' => [$this->field => [$this->getMessage()]]],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
