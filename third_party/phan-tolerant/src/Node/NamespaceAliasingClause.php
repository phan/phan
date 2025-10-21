<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

class NamespaceAliasingClause extends Node {
    /** @var Token */
    public $asKeyword;
    /** @var Token */
    public $name;

    const CHILD_NAMES = [
        'asKeyword',
        'name'
    ];
}
