<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Token;

class PropertyElement extends Node {
    /** @var Variable|null */
    public $variable;

    /** @var Token|null */
    public $equalsToken;

    /** @var Node|null */
    public $initializer;

    /** @var PropertyHookList|null */
    public $hookList;

    const CHILD_NAMES = [
        'variable',
        'equalsToken',
        'initializer',
        'hookList',
    ];
}
