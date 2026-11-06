<?php

declare(strict_types=1);

namespace App\Http\Requests\Church;

use App\Http\Requests\Concerns\DelegatesToPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a change to the church's settings. Any of the settings may be sent, but at least one, and a name that is not a
 * setting is refused, so a typo is never silently ignored. Each setting keeps to its range (config/members.php and
 * config/attendance.php).
 */
class UpdateChurchSettingsRequest extends FormRequest
{
    use DelegatesToPolicy;

    /**
     * Asks the policy whether the caller may change their church's settings.
     */
    public function authorize(): bool
    {
        $church = $this->user()?->church;

        return $church !== null && $this->allowed('update', $church);
    }

    /**
     * The rules that check this request's input.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array:youth_age_limit,absence_threshold,receipt_on_cash'],
            'settings.youth_age_limit' => ['integer', 'min:'.config('members.youth_age_limit_min'), 'max:'.config('members.youth_age_limit_max')],
            'settings.absence_threshold' => ['integer', 'min:'.config('attendance.absence_threshold_min'), 'max:'.config('attendance.absence_threshold_max')],
            'settings.receipt_on_cash' => ['boolean'],
        ];
    }
}
