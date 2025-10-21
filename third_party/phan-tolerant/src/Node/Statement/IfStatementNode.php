<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Statement;

use Microsoft\PhpParser\Node\ElseClauseNode;
use Microsoft\PhpParser\Node\ElseIfClauseNode;
use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Node\StatementNode;
use Microsoft\PhpParser\Token;

class IfStatementNode extends StatementNode {
    /** @var Token */
    public $ifKeyword;
    /** @var Token */
    public $openParen;
    /** @var Expression */
    public $expression;
    /** @var Token */
    public $closeParen;
    /** @var Token|null */
    public $colon;
    /** @var StatementNode|StatementNode[] */
    public $statements;
    /** @var ElseIfClauseNode[] */
    public $elseIfClauses;
    /** @var ElseClauseNode|null */
    public $elseClause;
    /** @var Token|null */
    public $endifKeyword;
    /** @var Token|null */
    public $semicolon;

    const CHILD_NAMES = [
        'ifKeyword',
        'openParen',
        'expression',
        'closeParen',
        'colon',
        'statements',
        'elseIfClauses',
        'elseClause',
        'endifKeyword',
        'semicolon'
    ];
}
