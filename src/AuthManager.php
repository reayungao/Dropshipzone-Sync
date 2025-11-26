<?php

namespace Dropshipzone\Sync;

use Exception;

class AuthManager
{
    private $config;
    private $tokenFile;
    private $logger;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->tokenFile = __DIR__ . '/../../token_store.json';
    }

    public function getToken($forceRefresh = false, $suppressLog = false)
    {
        // 1. Check cached token
        if (!$forceRefresh) {
            $storedData = $this->readTokenFile();
            if ($storedData && $this->isTokenValid($storedData)) {
                if (!$suppressLog) {
                    $this->logger->info("Using cached token (Valid for " . round(($storedData['expires_at'] - time()) / 60) . " more mins).");
                }
                return $storedData['token'];
            }
        }

        // 2. Request new token
        $this->logger->info("Token expired, missing, or refresh forced. Requesting new one...");
        return $this->requestNewToken();
    }

    private function readTokenFile()
    {
        if (!file_exists($this->tokenFile)) return null;
        return json_decode(file_get_contents($this->tokenFile), true);
    }

    private function isTokenValid($data)
    {
        if (!isset($data['expires_at'])) return false;
        // Buffer: Refresh if it expires in less than 5 minutes (300s)
        return time() < ($data['expires_at'] - 300);
    }

    private function requestNewToken()
    {
        $url = $this->config['base_url'] . '/auth';
        $this->logger->info("POST to: $url");

        $ch = curl_init($url);
        $payload = json_encode([
            'email' => $this->config['email'],
            'password' => $this->config['password']
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: DSZ-Integrator/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception("[Error] Network Error: " . curl_error($ch));
        }
        // curl_close is not strictly necessary if we unset, but good practice to just let it go out of scope or unset.
        // We will unset to be safe and avoid deprecation warnings if any.
        unset($ch);

        $body = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = "Auth Failed (HTTP $httpCode). Response: $response";
            throw new Exception($errorMsg);
        }

        $token = $body['token'] ?? $body['access_token'] ?? null;

        if (!$token && is_string($body)) {
            $token = $body;
        }

        if (!$token) {
            if (substr(trim($response), 0, 1) !== '{') {
                $token = trim($response);
            } else {
                throw new Exception("Could not find token in response: $response");
            }
        }

        // Docs: "token will be expired in 8 hours"
        $expiresIn = 7 * 60 * 60; // 28800 seconds

        $saveData = [
            'token' => $token,
            'created_at' => time(),
            'expires_at' => time() + $expiresIn
        ];

        // Atomic Write
        $tempFile = $this->tokenFile . '.tmp';
        if (file_put_contents($tempFile, json_encode($saveData)) !== false) {
            // Try to set permissions, suppress errors on Windows if it fails
            @chmod($tempFile, 0600);
            rename($tempFile, $this->tokenFile);
            $this->logger->info("Success! Token saved.");
        } else {
            $this->logger->warning("Could not save token to file. Cache will not work.");
        }

        return $token;
    }
}
