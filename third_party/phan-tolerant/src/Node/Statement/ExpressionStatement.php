<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Statement;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Node\StatementNode;
use Microsoft\PhpParser\Token;

class ExpressionStatement extends StatementNode {
    /** @var Expression */
    public $expression;
    /** @var Token */
    public $semicolon;

    const CHILD_NAMES = [
        'expression',
        'semicolon'
    ];
}
