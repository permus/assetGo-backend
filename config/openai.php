<?php

return [
    'api_key'      => env('OPENAI_API_KEY', ''),
    'base_url'     => env('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions'),
    'model'        => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'max_tokens'   => (int) env('OPENAI_MAX_TOKENS', 1200),
    'temperature'  => (float) env('OPENAI_TEMPERATURE', 0.2),
    'timeout'      => (int) env('OPENAI_TIMEOUT', 25), // seconds
];
