<?php

namespace Dropshipzone\Sync;

use Exception;

class InventoryFetcher
{
    private $config;
    private $logger;
    private $auth;

    private $lockFile;
    private $tempFile;
    private $finalFile;

    public function __construct(array $config, Logger $logger, AuthManager $auth)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->auth = $auth;

        $this->lockFile = __DIR__ . '/../../sync.lock';
        $this->tempFile = __DIR__ . '/../../temp_inventory.json';
        $this->finalFile = __DIR__ . '/../../dropshipzone_inventory.json';
    }

    public function fetch()
    {
        $startTime = microtime(true);

        // 1. Acquire Lock
        $lockFp = fopen($this->lockFile, 'c+');
        if (!$lockFp) {
            $this->logger->error("Could not open lock file permissions.");
            throw new Exception("Lock file error");
        }

        if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
            $this->logger->info("Script is already running (Locked). Exiting.");
            fclose($lockFp);
            return; // Exit gracefully
        }

        // Write PID
        ftruncate($lockFp, 0);
        fwrite($lockFp, (string)getmypid());

        // Register shutdown to release lock
        register_shutdown_function(function () use ($lockFp) {
            if ($lockFp) {
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
            }
            if (file_exists($this->lockFile)) {
                unlink($this->lockFile);
            }
            if (file_exists($this->tempFile)) {
                unlink($this->tempFile);
            }
        });

        try {
            $this->runSync($startTime);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw $e;
        }
    }

    private function runSync($startTime)
    {
        // Open Stream
        $fp = fopen($this->tempFile, 'w');
        if (!$fp) {
            throw new Exception("Could not open temp file for writing.");
        }

        fwrite($fp, "[\n");

        $page = 1;
        $totalCount = 0;
        $hasMorePages = true;
        $isFirstItem = true;

        $this->logger->info("Starting Streaming Download...");
        $this->logger->info("Target: " . $this->config['base_url'] . "/v2/products");

        // Pre-fetch token to log status once (and ensure we have one before starting)
        $this->auth->getToken();

        while ($hasMorePages) {
            $url = $this->config['base_url'] . "/v2/products?page_no=$page&limit=" . $this->config['sync']['batch_limit'];

            $data = $this->makeApiRequest($url);

            if (!$data) {
                throw new Exception("Failed to download page $page. Aborting.");
            }

            if (isset($data['result']) && is_array($data['result'])) {
                $pageCount = 0;
                foreach ($data['result'] as $item) {
                    $cleanItem = [
                        'sku'   => $item['sku'] ?? 'UNKNOWN_SKU',
                        'stock' => (int)($item['stock_qty'] ?? 0),
                        'price' => isset($item['price']) ? number_format((float)$item['price'], 2, '.', '') : "0.00"
                    ];

                    if (!$isFirstItem) {
                        fwrite($fp, ",\n");
                    } else {
                        $isFirstItem = false;
                    }

                    fwrite($fp, "  " . json_encode($cleanItem, JSON_UNESCAPED_SLASHES));
                    $pageCount++;
                }
                $totalCount += $pageCount;
                $this->logger->info("Page $page streamed ($pageCount items).");
            } else {
                $hasMorePages = false;
            }

            $totalPages = isset($data['total_pages']) ? (int)$data['total_pages'] : 1;

            if ($page >= $totalPages) {
                $hasMorePages = false;
            } else {
                $page++;
                usleep($this->config['sync']['rate_limit_sleep']);
            }

            unset($data);
        }

        fwrite($fp, "\n]");
        fclose($fp);

        $this->logger->info("Download complete. Validating...");

        if (filesize($this->tempFile) < 1024) {
            throw new Exception("Downloaded file is suspiciously small.");
        }

        if (!rename($this->tempFile, $this->finalFile)) {
            throw new Exception("Failed to move temp file to final location.");
        }

        $this->logger->info("Success! Inventory saved to " . $this->finalFile);
        $this->logger->info("Total Products Processed: $totalCount");

        $this->logSummary($startTime, $totalCount);
    }

    private function makeApiRequest($url, $attempt = 1, $forceRefresh = false)
    {
        $token = $this->auth->getToken($forceRefresh, true);

        $ch = curl_init($url);
        $headers = [];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['sync']['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: jwt " . $token,
            "User-Agent: DSZ-Integrator/1.0"
        ]);

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$headers) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) return $len;
            $headers[strtolower(trim($header[0]))] = trim($header[1]);
            return $len;
        });

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        unset($ch);

        if ($curlError) {
            $this->logger->error("cURL Error: $curlError");
            if ($attempt <= $this->config['sync']['retries']) {
                sleep(2);
                return $this->makeApiRequest($url, $attempt + 1, $forceRefresh);
            }
            return null;
        }

        if ($httpCode === 429) {
            $retryAfter = $headers['retry-after'] ?? 60;
            $this->logger->warning("Rate Limit Hit (429). Waiting {$retryAfter}s.");
            sleep($retryAfter);
            return $this->makeApiRequest($url, $attempt + 1, $forceRefresh);
        }

        if ($httpCode === 401) {
            if (!$forceRefresh) {
                $this->logger->warning("Token expired (401). Refreshing token...");
                return $this->makeApiRequest($url, 1, true);
            } else {
                $this->logger->error("Token refresh failed. Still getting 401.");
                return null;
            }
        }

        if ($httpCode >= 500) {
            $this->logger->warning("Server Error $httpCode. Retrying...");
            if ($attempt <= $this->config['sync']['retries']) {
                sleep(2);
                return $this->makeApiRequest($url, $attempt + 1, $forceRefresh);
            }
            return null;
        }

        if ($httpCode !== 200) {
            $this->logger->error("API Failed with status $httpCode: $response");
            return null;
        }

        return json_decode($response, true);
    }

    private function logSummary($startTime, $totalCount)
    {
        $duration = microtime(true) - $startTime;
        $durationStr = ($duration < 60) ? number_format($duration, 2) . "s" : floor($duration / 60) . "m " . ($duration % 60) . "s";

        // We can use the main logger or a separate file. The requirement was a separate file.
        // Let's create a simple append here or use a new Logger instance if we want to be strict.
        // For simplicity and "enterprise" cleanliness, we should probably have a separate logger or just append.
        // I'll just append manually to keep it simple as per original logic, but cleaner.

        $summaryFile = dirname($this->config['logging']['path']) . '/sync_summary.log';
        $entry = "[" . date('Y-m-d H:i:s') . "] SUCCESS | Duration: $durationStr | Products: $totalCount" . PHP_EOL;

        file_put_contents($summaryFile, $entry, FILE_APPEND);
    }
}
