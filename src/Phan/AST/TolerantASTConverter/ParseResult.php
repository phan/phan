<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use ast;
use Microsoft\PhpParser\Diagnostic;

/**
 * All details about the results of parsing.
 *
 * This can be serialized and used in a cache.
 * @phan-immutable
 */
class ParseResult
{
    /** @var ast\Node the node that was parsed */
    public $node;
    /** @var Diagnostic[] diagnostics emitted */
    public $diagnostics;

    /**
     * @param Diagnostic[] $diagnostics errors seen when parsing $node
     */
    public function __construct(ast\Node $node, array $diagnostics)
    {
        $this->node = $node;
        $this->diagnostics = $diagnostics;
    }
}
