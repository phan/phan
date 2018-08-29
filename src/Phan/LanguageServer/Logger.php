<?php
declare(strict_types = 1);

namespace Phan\LanguageServer;

use Phan\Config;

/**
 * A logger used by Phan for developing or debugging the language server.
 * Logs to stderr.
 */
class Logger
{
    /** @var resource|false */
    public static $file = false;

    public static function shouldLog() : bool
    {
        return Config::getValue('language_server_debug_level') === 'info';
    }

    /** @return void */
    public static function logRequest(array $headers, string $buffer)
    {
        if (!self::shouldLog()) {
            return;
        }
        self::logInfo(sprintf("Request:\n%s\nData:\n%s\n\n", json_encode($headers), $buffer));
    }

    /** @return void */
    public static function logResponse(array $headers, string $buffer)
    {
        if (!self::shouldLog()) {
            return;
        }
        self::logInfo(sprintf("Response:\n%s\nData:\n%s\n\n", json_encode($headers), $buffer));
    }

    /** @return void */
    public static function logInfo(string $msg)
    {
        if (!self::shouldLog()) {
            return;
        }
        $file = self::getLogFile();
        fwrite($file, $msg . "\n");
    }

    /**
     * @return resource
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
            fclose(self::$file);
            self::$file = false;
        }
        self::$file = $newFile;
    }
}
