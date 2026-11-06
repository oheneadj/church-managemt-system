<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Reports (docs/08, Reports)
|--------------------------------------------------------------------------
| How far back a trend report may look. The default is what the chart shows (docs/02: "weekly attendance for the last 12
| weeks"); the most is a year, so one request never reads more than a church's services for a year.
*/

return [

    'attendance_weeks_default' => 12,
    'attendance_weeks_max' => 52,

    // How the attendance trend is grouped when the caller does not say (see App\Enums\ReportGrouping).
    'attendance_group_by_default' => 'week',

];
