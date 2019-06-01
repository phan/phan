<?php declare(strict_types=1);

namespace Phan\Exception;

use Phan\Daemon\ExitException;

/**
 * Thrown to indicate that retrieving the element for an FQSEN from the CodeBase failed.
 */
class UsageException extends ExitException
{
    /** @var bool */
    public $print_extended_help;

    /** @var bool whether to forbid colorizing the exception message */
    public $forbid_color;

    /**
     * @param string $message
     * an optional error message to print
     *
     * @param int $code
     * the exit code of the program
     *
     * @param bool $print_extended_help
     * whether to print extended help messages
     */
    public function __construct(
        string $message = "",
        int $code = \EXIT_SUCCESS,
        bool $print_extended_help = false,
        bool $forbid_color = false
    ) {
        parent::__construct($message, $code);
        $this->print_extended_help = $print_extended_help;
        $this->forbid_color = $forbid_color;
    }
}
