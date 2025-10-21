<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

class ClassInterfaceClause extends Node {
    /** @var Token */
    public $implementsKeyword;

    /** @var DelimitedList\QualifiedNameList|null */
    public $interfaceNameList;

    const CHILD_NAMES = [
        'implementsKeyword',
        'interfaceNameList'
    ];
}
