<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    // `realpath` returns false when the directory has not been materialized yet.
    // On a fresh Render container that makes `php artisan view:clear` fail before
    // the application can finish its startup tasks.
    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),
];
