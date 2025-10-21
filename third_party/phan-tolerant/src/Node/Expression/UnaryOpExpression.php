<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Token;

class UnaryOpExpression extends UnaryExpression {

    /** @var Token */
    public $operator;

    const CHILD_NAMES = [
        'operator',
        'operand'
    ];
}
