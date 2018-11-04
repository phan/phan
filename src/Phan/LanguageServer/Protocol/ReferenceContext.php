<?php

namespace Phan\LanguageServer\Protocol;

/**
 * Argument for a textDocument/references request
 */
class ReferenceContext
{
    /**
     * Include the declaration of the current symbol.
     *
     * @var bool
     */
    public $includeDeclaration;
}
