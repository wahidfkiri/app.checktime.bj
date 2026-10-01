<?php

return [
    'api_key' => env('MAILTRAP_API_KEY'),
    'use_sandbox' => filter_var(env('MAILTRAP_USE_SANDBOX', true), FILTER_VALIDATE_BOOLEAN),
    'inbox_id' => env('MAILTRAP_INBOX_ID'), // Peut être string ou int
];