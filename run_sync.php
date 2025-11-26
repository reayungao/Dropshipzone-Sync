<?php

require_once __DIR__ . '/src/autoload.php';

use Dropshipzone\Sync\Logger;
use Dropshipzone\Sync\AuthManager;
use Dropshipzone\Sync\InventoryFetcher;

// Load Config
$config = require __DIR__ . '/config.php';

// Initialize Logger
$logger = new Logger(
    $config['logging']['path'],
    $config['logging']['max_size'],
    $config['logging']['max_backups']
);

try {
    // Initialize Auth
    $auth = new AuthManager($config, $logger);

    // Run Sync
    $fetcher = new InventoryFetcher($config, $logger, $auth);
    $fetcher->fetch();
} catch (Exception $e) {
    $logger->error("Critical Error: " . $e->getMessage());
    exit(1);
}
