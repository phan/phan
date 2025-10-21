<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

class InterfaceMembers extends Node {
    /** @var Token */
    public $openBrace;

    /** @var Node[] */
    public $interfaceMemberDeclarations;

    /** @var Token */
    public $closeBrace;

    const CHILD_NAMES = [
        'openBrace',
        'interfaceMemberDeclarations',
        'closeBrace'
    ];
}
