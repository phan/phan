<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Token;

class PostfixUpdateExpression extends Expression {
    /** @var Variable */
    public $operand;

    /** @var Token */
    public $incrementOrDecrementOperator;

    const CHILD_NAMES = [
        'operand',
        'incrementOrDecrementOperator'
    ];
}
