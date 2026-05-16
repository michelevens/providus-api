<?php

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

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Cloudflare R2 — S3-compatible object storage. Used for denial
        // appeal attachments (Phase 5, 2026-05-15). Region must be
        // 'auto'; path-style endpoint required. Env vars are set on
        // Railway by the operator (Phase 5C). Falls back to 's3'-style
        // env names so we share one set of creds if/when we move other
        // file uploads here too.
        'r2' => [
            'driver' => 's3',
            'key'      => env('R2_ACCESS_KEY_ID',     env('AWS_ACCESS_KEY_ID')),
            'secret'   => env('R2_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region'   => env('R2_REGION', 'auto'),
            'bucket'   => env('R2_BUCKET',   env('AWS_BUCKET')),
            'endpoint' => env('R2_ENDPOINT', env('AWS_ENDPOINT')),
            // R2 requires path-style addressing.
            'use_path_style_endpoint' => true,
            // Public URL prefix (set when bucket is served via a
            // public-domain custom hostname). Empty → use temporary
            // signed URLs only (preferred for PHI-adjacent content).
            'url'      => env('R2_PUBLIC_URL'),
            'throw'  => false,
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
