<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Statement;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Token;

class HaltCompilerStatement extends Expression {

    /** @var Token */
    public $haltCompilerKeyword;

    /** @var Token */
    public $openParen;

    /** @var Token */
    public $closeParen;

    /** @var Token (there is an implicit ')' before php close tags (`?>`)) */
    public $semicolonOrCloseTag;

    /** @var Token|null TokenKind::InlineHtml data unless there are no bytes (This is optional if there is nothing after the semicolon) */
    public $data;

    const CHILD_NAMES = [
        'haltCompilerKeyword',
        'openParen',
        'closeParen',
        'semicolonOrCloseTag',
        'data',
    ];

    /**
     * @return int
     */
    public function getHaltCompilerOffset() {
        // This accounts for the fact that PHP close tags may include a single newline,
        // and that $this->data may be null.
        return $this->semicolonOrCloseTag->getEndPosition();
    }
}
