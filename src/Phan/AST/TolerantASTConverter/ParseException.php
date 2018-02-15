<?php declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use Exception;
use Throwable;

/**
 * Should be handled similarly to parseError.
 * Note that getLine() can't be overridden
 */
class ParseException extends Exception {
    private $line_number_start;

    public function __construct(string $message, int $line_number_start, Throwable $previous = null) {
        parent::__construct($message, 0, $previous);
        $this->line_number_start = $line_number_start;
    }

    public function getLineNumberStart() : int {
        return $this->line_number_start;
    }
}
