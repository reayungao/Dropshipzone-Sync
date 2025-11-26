<?php

/**
 * Class AuthManager
 * Handles login and token storage based on Dropshipzone API 1.0.0
 */
class AuthManager
{
    private $config;
    private $token_file = __DIR__ . '/token_store.json';

    public function __construct()
    {
        $this->config = require __DIR__ . '/config.php';
    }

    public function get_token($force_refresh = false)
    {
        // 1. Check if we have a valid cached token (unless forced)
        if (!$force_refresh) {
            $stored_data = $this->read_token_file();
            if ($stored_data && $this->is_token_valid($stored_data)) {
                if (php_sapi_name() == 'cli') echo "[Auth] Using cached token (Valid for " . round(($stored_data['expires_at'] - time()) / 60) . " more mins).\n";
                return $stored_data['token'];
            }
        }

        // 2. Request a new one if missing, expired, or forced
        if (php_sapi_name() == 'cli') echo "[Auth] Token expired, missing, or refresh forced. Requesting new one...\n";
        return $this->request_new_token();
    }

    private function read_token_file()
    {
        if (!file_exists($this->token_file)) return null;
        return json_decode(file_get_contents($this->token_file), true);
    }

    private function is_token_valid($data)
    {
        if (!isset($data['expires_at'])) return false;
        // Buffer: Refresh if it expires in less than 5 minutes (300s)
        return time() < ($data['expires_at'] - 300);
    }

    private function request_new_token()
    {
        // TARGET: https://api.dropshipzone.com.au/auth
        $url = $this->config['base_url'] . '/auth';

        if (php_sapi_name() == 'cli') echo "[Debug] POST to: $url\n";

        $ch = curl_init($url);
        $payload = json_encode([
            'email' => $this->config['email'],
            'password' => $this->config['password']
        ]);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json', // Required by docs
            'User-Agent: DSZ-Integrator/1.0'  // Good practice to identify yourself
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            throw new Exception("[Error] Network Error: " . curl_error($ch));
        }
        unset($ch);

        $body = json_decode($response, true);

        // Check for success (HTTP 200)
        if ($http_code !== 200) {
            $error_msg = "\n[Error] Auth Failed (HTTP $http_code)\n";
            $error_msg .= "Response: " . $response . "\n";
            $error_msg .= "Check your Email/Password in config.php\n";
            throw new Exception($error_msg);
        }

        // The docs say the token comes back in the response body.
        // Usually it's just a raw string or JSON like {"token": "..."}
        // Based on standard JWT, we expect a JSON field.
        $token = $body['token'] ?? $body['access_token'] ?? null;

        // If the API returns the token as a raw string (not JSON), handle that:
        if (!$token && is_string($body)) {
            $token = $body;
        }

        if (!$token) {
            // Fallback: Maybe the response IS the token string?
            if (substr(trim($response), 0, 1) !== '{') {
                $token = trim($response);
            } else {
                throw new Exception("[Error] Could not find token in response: $response");
            }
        }

        // Docs: "token will be expired in 8 hours"
        $expires_in = 7 * 60 * 60; // 28800 seconds

        $save_data = [
            'token' => $token,
            'created_at' => time(),
            'expires_at' => time() + $expires_in
        ];

        // Atomic Write with Secure Permissions
        $temp_file = $this->token_file . '.tmp';
        if (file_put_contents($temp_file, json_encode($save_data)) !== false) {
            chmod($temp_file, 0600); // Secure: Owner read/write only
            rename($temp_file, $this->token_file);
            if (php_sapi_name() == 'cli') echo "[Auth] Success! Token saved.\n";
        } else {
            if (php_sapi_name() == 'cli') echo "[Auth] Warning: Could not save token to file. Cache will not work.\n";
        }

        return $token;
    }
}

// TEST RUNNER
if (php_sapi_name() == 'cli' && realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME'])) {
    $auth = new AuthManager();
    try {
        $token = $auth->get_token();
        // Show first 10 chars of token to prove it worked
        echo "Token: " . substr($token, 0, 15) . "...\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
