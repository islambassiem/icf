<?php

declare(strict_types=1);

return [
    // Which gateway driver to use. "fake" is the built-in test gateway (never allowed in production).
    // When a real provider is chosen, add its driver class and a new key here.
    'driver' => env('PAYMENT_GATEWAY', 'fake'),

    // A pending payment older than this is not reused when the user starts checkout again.
    'pending_ttl_minutes' => 30,

    'fake' => [
        'secret' => env('PAYMENT_FAKE_SECRET', config('app.key')),
    ],
];
