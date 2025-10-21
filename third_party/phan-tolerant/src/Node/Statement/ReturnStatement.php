<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Statement;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Node\StatementNode;
use Microsoft\PhpParser\Token;

class ReturnStatement extends StatementNode {
    /** @var Token */
    public $returnKeyword;
    /** @var Expression|null */
    public $expression;
    /** @var Token */
    public $semicolon;

    const CHILD_NAMES = [
        'returnKeyword',
        'expression',
        'semicolon'
    ];
}
