<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\MissingToken;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\DelimitedList\QualifiedNameList;
use Microsoft\PhpParser\Token;

class ParenthesizedIntersectionType extends Node{
    /** @var Token */
    public $openParen;

    /** @var QualifiedNameList|MissingToken */
    public $children;

    /** @var Token */
    public $closeParen;

    const CHILD_NAMES = [
        'openParen',
        'children',
        'closeParen'
    ];
}
