<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | How long operational records are kept (docs/05-data-safety.md)
    |--------------------------------------------------------------------------
    | A daily job (model:prune, run by the scheduler) deletes records older than this. Money records are not
    | pruned here: contributions are kept for 7 years and audit logs for at least 2.
    */
    'message_logs_months' => 12,
    'webhook_logs_days' => 90,

];
