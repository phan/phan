<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Statement;

use Microsoft\PhpParser\Node\DelimitedList;
use Microsoft\PhpParser\Node\StatementNode;
use Microsoft\PhpParser\Token;

class GlobalDeclaration extends StatementNode {

    /** @var Token */
    public $globalKeyword;

    /** @var DelimitedList\VariableNameList */
    public $variableNameList;

    /** @var Token */
    public $semicolon;

    const CHILD_NAMES = [
        'globalKeyword',
        'variableNameList',
        'semicolon'
    ];
}
