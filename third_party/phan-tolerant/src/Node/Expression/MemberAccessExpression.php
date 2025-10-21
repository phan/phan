<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Token;

class MemberAccessExpression extends Expression {

    /** @var Expression */
    public $dereferencableExpression;

    /** @var Token */
    public $arrowToken;

    /** @var Token */
    public $memberName;

    const CHILD_NAMES = [
        'dereferencableExpression',
        'arrowToken',
        'memberName'
    ];
}
