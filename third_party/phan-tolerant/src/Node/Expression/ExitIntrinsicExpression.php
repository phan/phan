<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Token;

class ExitIntrinsicExpression extends Expression {

    /** @var Token */
    public $exitOrDieKeyword;

    /** @var Token|null */
    public $openParen;

    /** @var Expression|null */
    public $expression;

    /** @var Token|null */
    public $closeParen;

    const CHILD_NAMES = [
        'exitOrDieKeyword',
        'openParen',
        'expression',
        'closeParen'
    ];
}
