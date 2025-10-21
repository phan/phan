<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\DelimitedList;
use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Token;

class CallExpression extends Expression {
    /** @var QualifiedName|Expression */
    public $callableExpression;

    /** @var Token */
    public $openParen;

    /** @var DelimitedList\ArgumentExpressionList|null */
    public $argumentExpressionList;

    /** @var Token */
    public $closeParen;

    const CHILD_NAMES = [
        'callableExpression',
        'openParen',
        'argumentExpressionList',
        'closeParen'
    ];
}
