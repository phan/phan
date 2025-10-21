<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Token;

class CloneExpression extends Expression {

    /** @var Token */
    public $cloneKeyword;

    /** @var Expression */
    public $expression;

    const CHILD_NAMES = [
        'cloneKeyword',
        'expression'
    ];
}
