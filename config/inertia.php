<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Our Vue pages live in `resources/js/Pages` (capital P, matching the
    | `./Pages/**` glob in resources/js/app.js). Inertia's default points at the
    | lowercase `js/pages`, which only resolves on case-insensitive filesystems
    | (macOS) and breaks the `assertInertia()->component()` check on Linux CI.
    | Pin the path explicitly so it works everywhere.
    |
    */

    'pages' => [
        'ensure_pages_exist' => false, // runtime enforcement off; testing check below stays on
        'paths' => [
            resource_path('js/Pages'),
        ],
        'extensions' => [
            'js', 'jsx', 'svelte', 'ts', 'tsx', 'vue',
        ],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],

];
