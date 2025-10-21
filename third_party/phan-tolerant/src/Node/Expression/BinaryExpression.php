<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Token;

class BinaryExpression extends Expression {

    /** @var Expression|Token */
    public $leftOperand;

    /** @var Token */
    public $operator;

    /** @var Expression|Token */
    public $rightOperand;

    const CHILD_NAMES = [
        'leftOperand',
        'operator',
        'rightOperand'
    ];
}
