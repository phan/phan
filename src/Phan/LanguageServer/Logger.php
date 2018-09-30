<?php
declare(strict_types = 1);

namespace Phan\LanguageServer;

use Phan\Config;

/**
 * A logger used by Phan for developing or debugging the language server.
 * Logs to stderr by default.
 */
class Logger
{
    /** @var resource|false the log file handle */
    public static $file = false;

    public static function shouldLog() : bool
    {
        return Config::getValue('language_server_debug_level') === 'info';
    }

    /**
     * Logs a request received from the client
     * @return void
     */
    public static function logRequest(array $headers, string $buffer)
    {
        if (!self::shouldLog()) {
            return;
        }
        self::logInfo(sprintf("Request:\n%s\nData:\n%s\n\n", json_encode($headers), $buffer));
    }

    /**
     * Logs a response this is about to send back to the client
     * @return void
     */
    public static function logResponse(array $headers, string $buffer)
    {
        if (!self::shouldLog()) {
            return;
        }
        self::logInfo(sprintf("Response:\n%s\nData:\n%s\n\n", json_encode($headers), $buffer));
    }

    /**
     * Logs an info message to a configured file (defaults to STDERR),
     * if debugging is turned on.
     *
     * This is used by code related to the language server.
     * Phan is slower when verbose logging is enabled.
     *
     * @return void
     */
    public static function logInfo(string $msg)
    {
        if (!self::shouldLog()) {
            return;
        }
        $file = self::getLogFile();
        fwrite($file, $msg . "\n");
    }

    /**
     * Logs an error related to the language server protocol
     * to the configured log file (defaults to STDERR)
     * @return void
     */
    public static function logError(string $msg)
    {
        $file = self::getLogFile();
        fwrite($file, $msg . "\n");
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
     * @param resource $newFile
     * @return void
     * @suppress PhanUnreferencedPublicMethod this is made available for debugging issues
     */
    public static function setLogFile($newFile)
    {
        if (!is_resource($newFile)) {
            throw new \TypeError("Expected newFile to be a resource, got " . gettype($newFile));
        }
        if (is_resource(self::$file)) {
            if (self::$file === $newFile) {
                return;
            }
            if (self::$file !== STDERR) {
                fclose(self::$file);
            }
        }
        self::$file = $newFile;
    }
}
