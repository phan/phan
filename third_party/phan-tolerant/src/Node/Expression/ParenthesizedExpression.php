<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Token;

class ParenthesizedExpression extends Expression {

    /** @var Token */
    public $openParen;

    /** @var Expression */
    public $expression;

    /** @var Token */
    public $closeParen;

    const CHILD_NAMES = [
        'openParen',
        'expression',
        'closeParen'
    ];
}
