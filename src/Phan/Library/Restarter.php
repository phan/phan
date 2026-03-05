<?php

declare(strict_types=1);

namespace Phan\Library;

use AssertionError;
use Composer\XdebugHandler\XdebugHandler;

use function array_filter;
use function extension_loaded;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function preg_replace;

/**
 * Restarts without any disabled extensions
 *
 * Source: https://github.com/vimeo/psalm/blob/3.4.9/src/Psalm/Internal/Fork/PsalmRestarter.php
 * @internal
 */
class Restarter extends XdebugHandler
{
    /**
     * @var bool
     */
    private $required = false;

    /**
     * @var list<string>
     */
    private $disabledExtensions = [];

    /**
     * Mark this extension as disabled
     */
    public function disableExtension(string $disabledExtension): void
    {
        $this->disabledExtensions[] = $disabledExtension;
    }

    /**
     * No type hint to allow xdebug-handler v2 usage
     * @param bool $default
     * @override
     */
    protected function requiresRestart($default): bool
    {
        $excluded_extensions = array_filter(
            $this->disabledExtensions,
            static function (string $extension): bool {
                return extension_loaded($extension);
            }
        );
        $this->required = (bool) $excluded_extensions;
        if ($this->required) {
            // @phan-suppress-next-line PhanPluginRemoveDebugCall
            \fprintf(
                \STDERR,
                "[debug] Because %s %s installed, Phan will restart." . \PHP_EOL,
                implode(' and ', $excluded_extensions),
                \count($excluded_extensions) > 1 ? 'were' : 'was'
            );
        }

        return $default || $this->required;
    }

    /**
     * No type hint to allow xdebug-handler v2 usage
     * @param list<string> $command
     * @override
     */
    protected function restart($command): never
    {
        // @phan-suppress-next-line PhanSuspiciousTruthyString
        if ($this->required && $this->tmpIni) {
            $regex = '/^\s*(extension\s*=.*(' . implode('|', $this->disabledExtensions) . ').*)$/mi';
            $content = file_get_contents($this->tmpIni);
            if (!\is_string($content)) {
                throw new AssertionError("Could not restart: could not read $this->tmpIni");
            }

            $content = preg_replace($regex, ';$1', $content);

            file_put_contents($this->tmpIni, $content);
        }

        // Reimplement process launching instead of calling parent::restart()
        // because XdebugHandler::doRestart() uses proc_open($cmd, [], $pipes)
        // which does not pass file descriptors to the child process on PHP 8.4+,
        // resulting in STDERR being invalid (errno=9 EBADF) in the restarted process.
        $displayCmd = \sprintf('[%s]', implode(', ', $command));
        // @phan-suppress-next-line PhanPluginRemoveDebugCall
        \fprintf(\STDERR, "[debug] Running: %s" . \PHP_EOL, $displayCmd);

        $process = \proc_open($command, [
            0 => \STDIN,
            1 => \STDOUT,
            2 => \STDERR,
        ], $pipes);

        if (\is_resource($process)) {
            $exitCode = \proc_close($process);
        }

        if (!isset($exitCode)) {
            // @phan-suppress-next-line PhanPluginRemoveDebugCall
            \fwrite(\STDERR, "[debug] Unable to restart process" . \PHP_EOL);
            $exitCode = -1;
        } else {
            // @phan-suppress-next-line PhanPluginRemoveDebugCall
            \fprintf(\STDERR, "[debug] Restarted process exited %d" . \PHP_EOL, $exitCode);
        }

        @\unlink((string) $this->tmpIni);
        exit($exitCode);
    }
}
