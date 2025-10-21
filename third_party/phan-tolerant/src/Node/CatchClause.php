<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\DelimitedList\QualifiedNameList;
use Microsoft\PhpParser\MissingToken;
use Microsoft\PhpParser\Token;

class CatchClause extends Node {
    /** @var Token */
    public $catch;
    /** @var Token */
    public $openParen;
    /** @var QualifiedNameList[]|MissingToken */
    public $qualifiedNameList;
    /** @var Token|null */
    public $variableName;
    /** @var Token */
    public $closeParen;
    /** @var StatementNode */
    public $compoundStatement;

    const CHILD_NAMES = [
        'catch',
        'openParen',
        'qualifiedNameList',
        'variableName',
        'closeParen',
        'compoundStatement'
    ];
}
