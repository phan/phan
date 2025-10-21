<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

class ForeachKey extends Node {
    /** @var Expression */
    public $expression;
    /** @var Token */
    public $arrow;

    const CHILD_NAMES = [
        'expression',
        'arrow'
    ];
}
