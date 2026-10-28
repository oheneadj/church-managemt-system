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

    /*
    |--------------------------------------------------------------------------
    | After a church cancels (docs/05, "Data Export")
    |--------------------------------------------------------------------------
    | The church keeps working for this many days so it can export its data. Then its personal data is purged:
    | members are erased, staff and visitor details removed, messages deleted and the history masked. Money
    | records stay for 7 years with no personal details left in them.
    */
    'cancelled_church_grace_days' => 90,

];
