<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Statement;

use Microsoft\PhpParser\Node\CaseStatementNode;
use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Node\StatementNode;
use Microsoft\PhpParser\Token;

class SwitchStatementNode extends StatementNode {
    /** @var Token */
    public $switchKeyword;
    /** @var Token */
    public $openParen;
    /** @var Expression */
    public $expression;
    /** @var Token */
    public $closeParen;
    /** @var Token|null */
    public $colon;
    /** @var Token|null */
    public $openBrace;
    /** @var CaseStatementNode[] */
    public $caseStatements;
    /** @var Token|null */
    public $closeBrace;
    /** @var Token|null */
    public $endswitch;
    /** @var Token|null */
    public $semicolon;

    const CHILD_NAMES = [
        'switchKeyword',
        'openParen',
        'expression',
        'closeParen',
        'colon',
        'openBrace',
        'caseStatements',
        'closeBrace',
        'endswitch',
        'semicolon'
    ];
}
