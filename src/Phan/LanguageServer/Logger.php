<?php
declare(strict_types = 1);

namespace Phan\LanguageServer;

use Phan\Config;
use Phan\LanguageServer\Protocol\Message;
use AdvancedJsonRpc\Message as MessageBody;
use Sabre\Event\Emitter;
use Sabre\Event\Loop;

/**
 * A logger used by Phan for developing or debugging the language server.
 * Logs to stderr.
 */
class Logger
{
    /** @var resource|false */
    public static $file;

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
        if (self::$file === null) {
            self::$file = STDERR;
        }
        return self::$file;
    }

    /**
     * @param resource $newFile
     * @return void
     */
    public static function setLogFile($newFile)
    {
        assert(is_resource($newFile));
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
