<?php declare(strict_types=1);

namespace Phan;

use Phan\Daemon\ExitException;
use Phan\Exception\UsageException;

/**
 * Helper method to build instances of CLI.
 *
 * Constructing CLI instances will affect Phan's configuration and may terminate the program.
 */
class CLIBuilder
{
    /** @var associative-array<int|string,string|string[]|false> */
    private $opts = [];
    /** @var list<string> */
    private $argv = [];

    public function __construct()
    {
    }

    /**
     * Set an option and return $this.
     *
     * @param string|list<string>|false $value
     */
    public function setOption(string $opt, $value = false) : self
    {
        $this->opts[$opt] = $value;
        if (!\is_array($value)) {
            $value = [$value];
        }
        foreach ($value as $element) {
            // Hardcode the option that there are no single-letter long options.
            $opt_name = \strlen($opt) > 1 ? "--$opt" : "-$opt";
            $this->argv[] = $opt_name;
            if (\is_int($element) || \is_string($element)) {
                $this->argv[] = (string)$element;
            }
        }
        return $this;
    }

    /**
     * Create and read command line arguments, configuring
     * \Phan\Config as a side effect.
     *
     * @throws ExitException
     * @throws UsageException
     */
    public function build() : CLI
    {
        return CLI::fromRawValues($this->opts, $this->argv);
    }

    /**
     * Return options in the same format as the getopt() call returns.
     * @return associative-array<int|string,string|string[]|false>
     */
    public function getOpts() : array
    {
        return $this->opts;
    }

    /**
     * Return an array of arguments that correspond to what would cause getopt() to return $this->getOpts().
     * @return list<string>
     */
    public function getArgv() : array
    {
        return $this->argv;
    }
}
