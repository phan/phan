<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use Microsoft\PhpParser;
use Microsoft\PhpParser\Diagnostic;

/**
 * The Microsoft\PhpParser instance produced for a given file contents for the currently running php version's tokenizer.
 *
 * These are kept in memory when the language server is running.
 *
 * @phan-read-only
 */
class PhpParserNodeEntry
{
    /** @var PhpParser\Node\SourceFileNode the node generated for the given file contents */
    public $node;
    /** @var Diagnostic[] the list of diagnostics generated for the given file contents */
    public $errors;

    /**
     * @param Diagnostic[] $errors
     */
    public function __construct(PhpParser\Node\SourceFileNode $node, array $errors)
    {
        $this->node = $node;
        $this->errors = $errors;
    }
}
