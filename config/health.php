<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum free disk space
    |--------------------------------------------------------------------------
    | The health check fails when the disk holding storage/ has less than this
    | percentage free (docs/17-monitoring-and-alerting.md alerts at 85% used).
    */
    'min_free_disk_percent' => (float) env('HEALTH_MIN_FREE_DISK_PERCENT', 10),

];
