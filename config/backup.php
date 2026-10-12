<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backups (HU-031)
    |--------------------------------------------------------------------------
    |
    | Archives are AES-256 encrypted zip files with a database dump and the
    | uploaded files. Point "disk" to an off-site disk (e.g. s3) in production.
    | BOOKS_ENCRYPTION_KEY is intentionally NOT included: store it separately.
    |
    */

    'disk' => env('BACKUP_DISK', 'backups'),

    'password' => env('BACKUP_PASSWORD'),

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),

    /*
    | Disk => directory pairs included in the archive.
    */
    'directories' => [
        'books' => '',
        'local' => 'receipts',
        'public' => 'covers',
    ],

    /*
    | Paths skipped inside those directories (regenerable caches).
    */
    'exclude' => [
        'books' => ['_cache'],
    ],

];
