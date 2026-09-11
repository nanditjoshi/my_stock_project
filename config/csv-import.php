<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Source upload directory
    |--------------------------------------------------------------------------
    |
    | When configured, the matching source file in this local directory is
    | deleted after a successful CSV import. Keep this limited to a directory
    | controlled by the local user running the application.
    |
    */
    'source_directory' => env('CSV_IMPORT_SOURCE_DIRECTORY'),
];
