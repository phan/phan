<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Token;

class TernaryExpression extends Expression {

    /** @var Expression|Token (only a token when token before '?' is invalid/missing) */
    public $condition;

    /** @var Token */
    public $questionToken;

    /** @var Expression */
    public $ifExpression;

    /** @var Token */
    public $colonToken;

    /** @var Expression */
    public $elseExpression;

    const CHILD_NAMES = [
        'condition',
        'questionToken',
        'ifExpression',
        'colonToken',
        'elseExpression'
    ];
}
