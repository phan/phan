<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

class FinallyClause extends Node {
    /** @var Token */
    public $finallyToken;
    /** @var StatementNode */
    public $compoundStatement;

    const CHILD_NAMES = [
        'finallyToken',
        'compoundStatement'
    ];
}
