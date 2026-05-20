<?php

declare(strict_types=1);

namespace MyAlert\Services;

/**
 * Appends structured log entries to channel-specific log files.
 *
 * Channels: app, worker, discord
 * Format: {ISO8601_TIMESTAMP} [{LEVEL}] {message}\n
 */
class LogService
{
    /** @var string Absolute path to the storage/logs directory */
    private string $logDirectory;

    /** @var array<string, string> Channel-to-filename mapping */
    private const CHANNELS = [
        'app' => 'app.log',
        'worker' => 'worker.log',
        'discord' => 'discord.log',
    ];

    public function __construct(?string $logDirectory = null)
    {
        $this->logDirectory = $logDirectory ?? dirname(__DIR__, 2) . '/storage/logs';
    }

    /**
     * Write an INFO level log entry.
     */
    public function info(string $channel, string $message): void
    {
        $this->write($channel, 'INFO', $message);
    }

    /**
     * Write a WARNING level log entry.
     */
    public function warning(string $channel, string $message): void
    {
        $this->write($channel, 'WARNING', $message);
    }

    /**
     * Write an ERROR level log entry.
     */
    public function error(string $channel, string $message): void
    {
        $this->write($channel, 'ERROR', $message);
    }

    /**
     * Format and append a log entry to the appropriate channel file.
     */
    private function write(string $channel, string $level, string $message): void
    {
        $filename = self::CHANNELS[$channel] ?? null;

        if ($filename === null) {
            return;
        }

        $timestamp = date('c'); // ISO 8601 format: YYYY-MM-DDTHH:MM:SS+TZ
        $entry = "{$timestamp} [{$level}] {$message}\n";

        $filepath = $this->logDirectory . '/' . $filename;

        file_put_contents($filepath, $entry, FILE_APPEND | LOCK_EX);
    }
}
