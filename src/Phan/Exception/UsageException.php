<?php

declare(strict_types=1);

namespace Phan\Exception;

use Phan\Daemon\ExitException;

/**
 * Thrown to indicate that retrieving the element for an FQSEN from the CodeBase failed.
 * @phan-immutable
 */
class UsageException extends ExitException
{
    /** @var int the type of usage to print */
    public $print_type;

    /** @var bool whether to forbid colorizing the exception message */
    public $forbid_color;

    public const PRINT_NORMAL = 10;
    public const PRINT_EXTENDED = 11;
    public const PRINT_INIT_ONLY = 12;
    public const PRINT_INVALID_ARGS = 13;

    /**
     * @param string $message
     * an optional error message to print
     *
     * @param int $code
     * the exit code of the program (EXIT_SUCCESS, EXIT_FAILURE)
     *
     * @param ?(10|11|12) $print_type
     * The type of usage to print
     */
    public function __construct(
        string $message,
        int $code,
        ?int $print_type = null,
        bool $forbid_color = false
    ) {
        parent::__construct($message, $code);
        $this->print_type = $print_type ?? self::PRINT_INVALID_ARGS;
        $this->forbid_color = $forbid_color;
    }
}
