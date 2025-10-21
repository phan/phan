<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

class DeclareDirective extends Node {
    /** @var Token */
    public $name;
    /** @var Token */
    public $equals;
    /** @var Token */
    public $literal;

    const CHILD_NAMES = [
        'name',
        'equals',
        'literal'
    ];
}
