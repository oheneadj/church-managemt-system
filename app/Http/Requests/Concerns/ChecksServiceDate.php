<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * The window a service's date must fall in (D-049): a set number of days back, so a missed Sunday can be added, and
 * a set number of days ahead, so a wrong year is caught. The numbers are in config/attendance.php.
 */
trait ChecksServiceDate
{
    /**
     * The rules for the service date. When the date is not being changed (an old service saved again with the same
     * date), only its shape is checked, so an old service can still be renamed.
     *
     * @return list<string>
     */
    protected function serviceDateRules(?string $unchangedDate = null): array
    {
        if ($unchangedDate !== null && $this->input('service_date') === $unchangedDate) {
            return ['date_format:Y-m-d'];
        }

        return [
            'date_format:Y-m-d',
            'after_or_equal:'.now()->subDays(config('attendance.service_days_back'))->toDateString(),
            'before_or_equal:'.now()->addDays(config('attendance.service_days_ahead'))->toDateString(),
        ];
    }

    /**
     * Plain messages for the date window.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_date.after_or_equal' => 'A service cannot be dated more than '.config('attendance.service_days_back').' days in the past.',
            'service_date.before_or_equal' => 'A service cannot be dated more than '.config('attendance.service_days_ahead').' days ahead.',
        ];
    }
}
