<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Database backups (docs/16, "Backup Strategy"; docs/05)
    |--------------------------------------------------------------------------
    | A daily encrypted dump goes to the separate backup bucket and the ones older than `retention_days` are removed.
    | The dump is encrypted with the PUBLIC key of `gpg_recipient`, so the server can write backups but cannot read
    | them; the private key is kept offline (1Password), as the restore runbook says.
    */
    'disk' => 'r2-backup',
    'folder' => 'backups/database',
    'retention_days' => 30,
    'gpg_recipient' => env('BACKUP_GPG_RECIPIENT'),

    // Where the dump is written while it is being made. It is deleted straight after, whether or not it worked.
    'work_directory' => storage_path('app/backup'),

    // Programs to run. Change only if they are not on the PATH.
    'pg_dump' => 'pg_dump',
    'gpg' => 'gpg',

];
