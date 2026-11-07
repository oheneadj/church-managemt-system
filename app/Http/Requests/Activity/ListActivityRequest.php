<?php

declare(strict_types=1);

namespace App\Http\Requests\Activity;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use App\Models\AuditLog;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a request for the recent activity feed: the page size.
 */
class ListActivityRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may read the activity feed.
     */
    public function authorize(): bool
    {
        return $this->allowed('viewAny', AuditLog::class);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('limits.per_page_max')],
        ];
    }
}
