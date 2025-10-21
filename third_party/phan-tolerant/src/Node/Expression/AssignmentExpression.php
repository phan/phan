<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Token;

class AssignmentExpression extends BinaryExpression {

    /** @var Expression|Token */
    public $leftOperand;

    /** @var Token */
    public $operator;

    /** @var Token */
    public $byRef;

    /** @var Expression */
    public $rightOperand;

    const CHILD_NAMES = [
        'leftOperand',
        'operator',
        'byRef',
        'rightOperand'
    ];
}
