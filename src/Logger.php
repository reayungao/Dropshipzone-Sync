<?php

namespace Dropshipzone\Sync;

class Logger
{
    private $logFile;
    private $maxSize;
    private $maxBackups;
    private $echoToConsole;

    public function __construct($logFile, $maxSize = 5242880, $maxBackups = 5, $echoToConsole = true)
    {
        $this->logFile = $logFile;
        $this->maxSize = $maxSize;
        $this->maxBackups = $maxBackups;
        $this->echoToConsole = $echoToConsole;

        $this->ensureDirectoryExists();
    }

    private function ensureDirectoryExists()
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;

        if ($this->echoToConsole) {
            echo $formattedMessage;
        }

        $this->rotateIfNeeded();
        file_put_contents($this->logFile, $formattedMessage, FILE_APPEND);
    }

    public function info($message)
    {
        $this->log($message, 'INFO');
    }

    public function error($message)
    {
        $this->log($message, 'ERROR');
    }

    public function warning($message)
    {
        $this->log($message, 'WARNING');
    }

    private function rotateIfNeeded()
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        if (filesize($this->logFile) > $this->maxSize) {
            $this->rotate();
        }
    }

    private function rotate()
    {
        // Remove oldest
        $oldest = $this->logFile . '.' . $this->maxBackups;
        if (file_exists($oldest)) {
            unlink($oldest);
        }

        // Shift
        for ($i = $this->maxBackups - 1; $i >= 1; $i--) {
            $current = $this->logFile . '.' . $i;
            $next = $this->logFile . '.' . ($i + 1);
            if (file_exists($current)) {
                rename($current, $next);
            }
        }

        // Rename current
        rename($this->logFile, $this->logFile . '.1');
    }
}
