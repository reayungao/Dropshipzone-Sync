<?php
// config.php
return [
    'email'    => 'info@mobiassist.com.au',
    'password' => 'INSERT API PASSWORD HERE',
    'base_url' => 'https://api.dropshipzone.com.au',
    'logging'  => [
        'path'        => __DIR__ . '/logs/sync.log',
        'max_size'    => 5 * 1024 * 1024, // 5MB
        'max_backups' => 5,
    ],
    'sync' => [
        'batch_limit' => 200,
        'timeout'     => 60,
        'retries'     => 5,
        'rate_limit_sleep' => 6500000, // 6.5s
    ],
];
