<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Cloudflare R2 (S3-compatible). Files are private: the bucket is never made public and the app hands out
        // short-lived signed URLs (Storage::temporaryUrl). R2 does not implement ACLs, so privacy comes from the
        // bucket settings, not from per-object visibility; 'visibility' => 'private' is the safe value if that ever changes.
        // Paths are tenant-namespaced: churches/{church_id}/... (docs/14-integrations.md).
        'r2' => [
            'driver' => 's3',
            'key' => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
            'secret' => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
            'region' => 'auto', // required by the SDK, ignored by R2
            'bucket' => env('CLOUDFLARE_R2_BUCKET'),
            'endpoint' => env('CLOUDFLARE_R2_URL'), // https://<account id>.r2.cloudflarestorage.com
            'use_path_style_endpoint' => (bool) env('CLOUDFLARE_R2_USE_PATH_STYLE', false),
            'visibility' => 'private',
            'throw' => true, // a failed upload must raise, not return false and be ignored
            'report' => false,
        ],

        // Database backups go to a separate bucket with its own credentials, so one leaked key cannot
        // reach both the live files and the backups (docs/05-data-safety.md).
        'r2-backup' => [
            'driver' => 's3',
            'key' => env('CLOUDFLARE_R2_BACKUP_ACCESS_KEY_ID'),
            'secret' => env('CLOUDFLARE_R2_BACKUP_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('CLOUDFLARE_R2_BACKUP_BUCKET'),
            'endpoint' => env('CLOUDFLARE_R2_URL'),
            'use_path_style_endpoint' => (bool) env('CLOUDFLARE_R2_USE_PATH_STYLE', false),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
