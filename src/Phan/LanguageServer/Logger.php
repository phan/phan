<?php
declare(strict_types=1);

namespace Phan\LanguageServer;

use Phan\Config;
use Phan\Library\StringUtil;

use const STDERR;

/**
 * A logger used by Phan for developing or debugging the language server.
 * Logs to stderr by default.
 */
class Logger
{
    /** @var resource|false the log file handle */
    public static $file = false;

    /**
     * Should this verbosely log debug output?
     */
    public static function shouldLog() : bool
    {
        return Config::getValue('language_server_debug_level') === 'info';
    }

    /**
     * Logs a request received from the client
     * @param array<string,string> $headers
     */
    public static function logRequest(array $headers, string $buffer) : void
    {
        if (!self::shouldLog()) {
            return;
        }
        self::logInfo(\sprintf("Request:\n%s\nData:\n%s\n\n", StringUtil::jsonEncode($headers), $buffer));
    }

    /**
     * Logs a response this is about to send back to the client
     * @param array<string,mixed> $headers
     */
    public static function logResponse(array $headers, string $buffer) : void
    {
        if (!self::shouldLog()) {
            return;
        }
        self::logInfo(\sprintf("Response:\n%s\nData:\n%s\n\n", StringUtil::jsonEncode($headers), $buffer));
    }

    /**
     * Logs an info message to a configured file (defaults to STDERR),
     * if debugging is turned on.
     *
     * This is used by code related to the language server.
     * Phan is slower when verbose logging is enabled.
     */
    public static function logInfo(string $msg) : void
    {
        if (!self::shouldLog()) {
            return;
        }
        $file = self::getLogFile();
        \fwrite($file, $msg . "\n");
    }

    /**
     * Logs an error related to the language server protocol
     * to the configured log file (defaults to STDERR)
     */
    public static function logError(string $msg) : void
    {
        $file = self::getLogFile();
        \fwrite($file, $msg . "\n");
    }

    /**
     * @return resource the log file handle (defaults to STDERR)
     */
    private static function getLogFile()
    {
        $file = self::$file;
        if (!$file) {
            self::$file = $file = STDERR;
        }
        return $file;
    }

    /**
     * Overrides the log file to a different one
     * @param resource $new_file
     * @suppress PhanUnreferencedPublicMethod this is made available for debugging issues
     */
    public static function setLogFile($new_file) : void
    {
        if (!\is_resource($new_file)) {
            throw new \TypeError("Expected newFile to be a resource, got " . \gettype($new_file));
        }
        $old_file = self::$file;
        if (\is_resource($old_file)) {
            if ($old_file === $new_file) {
                return;
            }
            if ($old_file !== STDERR) {
                \fclose($old_file);
            }
        }
        self::$file = $new_file;
    }
}
