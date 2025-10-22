<?php

/**
 * CORS Configuration for FlexiAPI
 * Generated during setup
 */

return [
    'origins' => ['*'],
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-API-Key', 'Auth-x'],
    'credentials' => false,
    'max_age' => 86400
];