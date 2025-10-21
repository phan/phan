<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

class RelativeSpecifier extends Node {
    /** @var Token */
    public $namespaceKeyword;

    /** @var Token|null */
    public $backslash;

    const CHILD_NAMES = [
        'namespaceKeyword',
        'backslash'
    ];
}
