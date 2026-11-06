<?php

declare(strict_types=1);

namespace App\Http\Requests\Messages;

use App\Enums\MessageChannel;
use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\MessageLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the message log's filters and page size.
 */
class ListMessagesRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may view any (message).
     */
    public function authorize(): bool
    {
        return $this->allowed('viewAny', MessageLog::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'channel' => ['sometimes', Rule::enum(MessageChannel::class)],
            'message_type' => ['sometimes', 'string', 'max:100'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('limits.per_page_max')],
        ];
    }
}
