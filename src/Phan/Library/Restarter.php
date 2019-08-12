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
     * @param string $disabledExtension
     */
    public function disableExtension(string $disabledExtension): void
    {
        $this->disabledExtensions[] = $disabledExtension;
    }

    /**
     * @param bool $loaded
     * @return bool
     * @override
     */
    protected function requiresRestart($loaded)
    {
        $excluded_extensions = array_filter(
            $this->disabledExtensions,
            static function (string $extension): bool {
                return extension_loaded($extension);
            }
        );
        $this->required = (bool) $excluded_extensions;
        if ($this->required) {
            \fprintf(
                \STDERR,
                "[debug] Because %s %s installed, Phan will restart." . \PHP_EOL,
                implode(' and ', $excluded_extensions),
                \count($excluded_extensions) > 1 ? 'were' : 'was'
            );
        }

        return $loaded || $this->required;
    }

    /**
     * @return void
     * @override
     */
    protected function restart($command)
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

        /** @psalm-suppress MixedArgument */
        parent::restart($command);
    }
}
