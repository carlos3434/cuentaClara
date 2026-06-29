<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Receipts storage disk
     |--------------------------------------------------------------------------
     |
     | Where participant payment receipts are stored. Use a private S3 disk in
     | production (RECEIPTS_DISK=s3) and the local private disk in development.
     | Receipts are financial PII and must never live on a public disk.
     |
     */
    'receipts_disk' => env('RECEIPTS_DISK', 'local'),

    /*
     | Max receipt upload size in kilobytes.
     */
    'receipts_max_kb' => (int) env('RECEIPTS_MAX_KB', 8192),

    /*
     | Rate limits (requests per minute). The upload endpoint is public and
     | unauthenticated, so it is the most important to bound.
     */
    'rate_limits' => [
        'uploads' => (int) env('RATE_LIMIT_UPLOADS', 20),
        'login' => (int) env('RATE_LIMIT_LOGIN', 10),
    ],

    /*
     |--------------------------------------------------------------------------
     | AI receipt validation
     |--------------------------------------------------------------------------
     |
     | driver: 'fake' (deterministic, dev/test default) or 'anthropic' (real
     | Claude vision call). The vision model only *extracts*; the verdict is
     | decided by the deterministic ReceiptRuleEngine. See docs/06.
     |
     */
    'ai' => [
        'driver' => env('AI_DRIVER', 'fake'),
        'confidence_threshold' => (float) env('AI_CONFIDENCE_THRESHOLD', 0.85),

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('AI_MODEL', 'claude-opus-4-8'),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        ],
    ],

];
