<?php

return [
    // Base URL of your Mealie instance, no trailing slash.
    // Example: https://mealie.yourdomain.com
    'base_url' => rtrim(env('MEALIE_BASE_URL', ''), '/'),

    // When true, every Mealie API request and response is logged at debug
    // level (method, URL, payload, status, body). Failures are always logged at
    // error level regardless of this flag. To see the debug lines, also set
    // LOG_LEVEL=debug in .env. Turn this off in normal production use.
    'debug' => (bool) env('MEALIE_DEBUG', false),
];
