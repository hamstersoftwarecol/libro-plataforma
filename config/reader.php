<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Book encryption key (HU-012)
    |--------------------------------------------------------------------------
    |
    | Base64 encoded 32 byte key used to encrypt book files at rest. Kept
    | separate from APP_KEY so it can be rotated and backed up on its own.
    |
    */

    'encryption_key' => env('BOOKS_ENCRYPTION_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Concurrent reading sessions (HU-029)
    |--------------------------------------------------------------------------
    */

    'max_concurrent_sessions' => (int) env('READER_MAX_SESSIONS', 1),

    'session_idle_minutes' => (int) env('READER_SESSION_IDLE_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | PDF rendering
    |--------------------------------------------------------------------------
    */

    'pdftoppm_path' => env('PDFTOPPM_PATH', 'pdftoppm'),

    'pdftocairo_path' => env('PDFTOCAIRO_PATH', 'pdftocairo'),

    'gs_path' => env('GS_PATH', 'gs'),

    'convert_path' => env('CONVERT_PATH', 'convert'),

    'pdfinfo_path' => env('PDFINFO_PATH', 'pdfinfo'),

    'pdftotext_path' => env('PDFTOTEXT_PATH', 'pdftotext'),

    'page_widths' => [800, 1200, 1600],

    'default_page_width' => 1200,

    'cache_minutes' => 60 * 24,

    /*
    |--------------------------------------------------------------------------
    | Watermark (HU-014)
    |--------------------------------------------------------------------------
    |
    | Optional TrueType font path. GD's built-in font is used when empty.
    |
    */

    'watermark_font' => env('READER_WATERMARK_FONT'),

];
