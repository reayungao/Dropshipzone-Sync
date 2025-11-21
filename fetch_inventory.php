<?php

/**
 * Enterprise Fetch Script (Streaming Version)
 * Downloads Dropshipzone Inventory safely using pagination, retries, and streaming writes.
 * Memory Impact: LOW (Constant)
 * Rate Limit: Throttled to comply with 60 req/min (Target: ~40 req/min)
 */

// 1. Constants & Configuration
const BATCH_LIMIT = 200;
const TIMEOUT_SECONDS = 60;
const RATE_LIMIT_SLEEP_US = 6500000; // 6.5s (Target ~550 req/hr to stay under 600/hr limit)
const MAX_RETRIES = 5; // Increased retries for stability
const MEMORY_LIMIT = '256M';

// 2. Stability Settings
set_time_limit(0);
ini_set('memory_limit', MEMORY_LIMIT);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/auth_manager.php';

// 3. Logging Helper
function log_message($message, $level = 'INFO')
{
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";

    // Output to screen (for manual runs)
    echo $log_entry;

    // Append to log file (for cron jobs)
    file_put_contents(__DIR__ . '/sync.log', $log_entry, FILE_APPEND);
}

// 4. Config Validation
if (!file_exists(__DIR__ . '/config.php')) {
    log_message("config.php not found.", 'ERROR');
    exit(1);
}
$config = require __DIR__ . '/config.php';

if (empty($config['base_url']) || empty($config['email'])) {
    log_message("Invalid config.php. Missing base_url or email.", 'ERROR');
    exit(1);
}

// 5. File Paths
$LOCK_FILE = __DIR__ . '/sync.lock';
$TEMP_FILE = __DIR__ . '/temp_inventory.json';
$FINAL_FILE = __DIR__ . '/dropshipzone_inventory.json';

// 6. Concurrency Lock
if (file_exists($LOCK_FILE)) {
    // Self-Healing: If lock is > 2 hours old, assume crash and reset.
    if (time() - filemtime($LOCK_FILE) > 7200) {
        log_message("Stale lock found. Removing it.", 'WARNING');
        unlink($LOCK_FILE);
    } else {
        log_message("Script is already running. Exiting.", 'INFO');
        exit(0);
    }
}
touch($LOCK_FILE);

// 7. Robust Shutdown Handler (Ensures Lock File is ALWAYS removed)
register_shutdown_function(function () use ($LOCK_FILE, $TEMP_FILE) {
    // 1. Remove Lock File
    if (file_exists($LOCK_FILE)) {
        unlink($LOCK_FILE);
    }

    // 2. Remove Temp File (if it still exists, meaning the script didn't finish)
    if (file_exists($TEMP_FILE)) {
        unlink($TEMP_FILE);
    }

    // 3. Check for Fatal Errors
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_USER_ERROR)) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] [CRITICAL] Script crashed: " . $error['message'] . " in " . $error['file'] . ":" . $error['line'] . "\n";
    }
});

/**
 * API Request Helper
 */
function make_api_request($url, $token, $attempt = 1)
{
    $ch = curl_init($url);
    $headers = [];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, TIMEOUT_SECONDS);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: jwt " . $token,
        "User-Agent: DSZ-Integrator/1.0"
    ]);

    // Capture Headers to check for Retry-After
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$headers) {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) return $len;
        $headers[strtolower(trim($header[0]))] = trim($header[1]);
        return $len;
    });

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Network Error
    if ($curl_error) {
        log_message("cURL Error: $curl_error", 'ERROR');
        if ($attempt <= MAX_RETRIES) {
            sleep(2);
            return make_api_request($url, $token, $attempt + 1);
        }
        return null;
    }

    // Rate Limit (429) -> FAIL FAST
    if ($http_code === 429) {
        $retry_after = $headers['retry-after'] ?? 60;
        log_message("Rate Limit Hit (429). Server requires wait of {$retry_after}s.", 'ERROR');
        log_message("Aborting immediately to prevent IP ban extension.", 'ERROR');
        return null; // Do not retry
    }

    // Server Error (500+) -> RETRY
    if ($http_code >= 500) {
        log_message("Server Error $http_code. Retrying...", 'WARNING');
        if ($attempt <= MAX_RETRIES) {
            sleep(2);
            return make_api_request($url, $token, $attempt + 1);
        }
        return null;
    }

    // Other Errors
    if ($http_code !== 200) {
        log_message("API Failed with status $http_code: $response", 'ERROR');
        return null;
    }

    return json_decode($response, true);
}

try {
    // 8. Authentication
    $auth = new AuthManager();
    $token = $auth->get_token();

    // 9. Open Stream to Temp File
    $fp = fopen($TEMP_FILE, 'w');
    if (!$fp) {
        throw new Exception("Could not open temp file for writing. Check permissions.");
    }

    // Start the JSON Array manually
    fwrite($fp, "[\n");

    $page = 1;
    $total_count = 0;
    $has_more_pages = true;
    $is_first_item = true;

    log_message("Starting Streaming Download...");
    log_message("Target: " . $config['base_url'] . "/v2/products");

    // 10. Pagination Loop
    while ($has_more_pages) {
        $url = $config['base_url'] . "/v2/products?page_no=$page&limit=" . BATCH_LIMIT;

        $data = make_api_request($url, $token);

        if (!$data) {
            throw new Exception("Failed to download page $page. Aborting to prevent partial sync.");
        }

        // STREAMING WRITE
        if (isset($data['result']) && is_array($data['result'])) {
            $page_count = 0;
            foreach ($data['result'] as $item) {
                // 1. Prep Data
                $clean_item = [
                    'sku'   => $item['sku'],
                    'stock' => (int)$item['stock_qty']
                ];

                // 2. Write Comma (if not first item)
                if (!$is_first_item) {
                    fwrite($fp, ",\n");
                } else {
                    $is_first_item = false;
                }

                // 3. Write Object (Clean slashes)
                fwrite($fp, "  " . json_encode($clean_item, JSON_UNESCAPED_SLASHES));
                $page_count++;
            }
            $total_count += $page_count;
            log_message("Page $page streamed ($page_count items).");
        } else {
            $has_more_pages = false;
        }

        // Pagination Check
        $total_pages = isset($data['total_pages']) ? (int)$data['total_pages'] : 1;

        if ($page >= $total_pages) {
            $has_more_pages = false;
        } else {
            $page++;

            // --- RATE LIMIT ENFORCEMENT ---
            usleep(RATE_LIMIT_SLEEP_US);
        }

        // CRITICAL: Free memory immediately
        unset($data);
    }

    // End the JSON Array manually
    fwrite($fp, "\n]");

    fclose($fp);

    log_message("Download complete. Validating...");

    // 11. Validation
    if (filesize($TEMP_FILE) < 1024) {
        throw new Exception("Downloaded file is suspiciously small. Aborting.");
    }

    // 12. Atomic Swap
    if (!rename($TEMP_FILE, $FINAL_FILE)) {
        throw new Exception("Failed to move temp file to final location.");
    }

    log_message("Success! Inventory saved to $FINAL_FILE");
    log_message("Total Products Processed: $total_count");
} catch (Exception $e) {
    log_message($e->getMessage(), 'ERROR');
    // Cleanup is handled by register_shutdown_function
    exit(1);
}
