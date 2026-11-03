<?php

return [
  'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],
  'allowed_methods' => ['*'],
  'allowed_origins' => [
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'https://intranettb.com',
    'https://www.intranettb.com',
  ],
  'allowed_origins_patterns' => [],
  'allowed_headers' => ['*'],
  'exposed_headers' => [],
  'max_age' => 0,
  'supports_credentials' => true,
];
