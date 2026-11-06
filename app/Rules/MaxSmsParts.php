<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\SmsSegmenter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails when a text would need more SMS parts than allowed. A part is a credit for each person, so the limit stops a very
 * long text from costing a church a great deal by accident. A letter outside the plain alphabet (such as ɔ or ɛ) makes the
 * parts shorter, and the count here is the same one the sender uses.
 */
class MaxSmsParts implements ValidationRule
{
    /**
     * Takes the most parts a text may use.
     */
    public function __construct(private readonly int $maxParts) {}

    /**
     * Fails the field when the text needs too many parts.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && app(SmsSegmenter::class)->parts($value) > $this->maxParts) {
            $fail("This message is too long: it can use at most {$this->maxParts} text message parts. Shorten it, or write it without letters outside the plain alphabet.");
        }
    }
}
