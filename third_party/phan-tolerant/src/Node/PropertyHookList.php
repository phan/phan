<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

class PropertyHookList extends Node {
    /** @var Token|null */
    public $openBrace;

    /** @var PropertyHook[]|null */
    public $hooks;

    /** @var Token|null */
    public $closeBrace;

    const CHILD_NAMES = [
        'openBrace',
        'hooks',
        'closeBrace',
    ];
}
