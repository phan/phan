<?php

declare(strict_types=1);

namespace Phan\Daemon;

/**
 * This is used to signal to Phan\AST\Parser that Phan is running in daemon mode,
 * and that the AST generated during the parse phase should be reused during the analysis phase
 * or when generating column numbers for php's error messages
 */
class ParseRequest extends Request
{
    public function __construct()
    {
        // Deliberately do not call parent::__construct
    }
}
