<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Token;

class NumericLiteral extends Expression {
    /** @var Token */
    public $children;

    const CHILD_NAMES = [
        'children'
    ];
}
