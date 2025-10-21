<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Statement;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Node\StatementNode;
use Microsoft\PhpParser\Token;

class ForStatement extends StatementNode {
    /** @var Token */
    public $for;
    /** @var Token */
    public $openParen;
    /** @var Expression|null */
    public $forInitializer;
    /** @var Token */
    public $exprGroupSemicolon1;
    /** @var Expression|null */
    public $forControl;
    /** @var Token */
    public $exprGroupSemicolon2;
    /** @var Expression|null */
    public $forEndOfLoop;
    /** @var Token */
    public $closeParen;
    /** @var Token|null */
    public $colon;
    /** @var StatementNode|StatementNode[] */
    public $statements;
    /** @var Token|null */
    public $endFor;
    /** @var Token|null */
    public $endForSemicolon;

    const CHILD_NAMES = [
        'for',
        'openParen',
        'forInitializer',
        'exprGroupSemicolon1',
        'forControl',
        'exprGroupSemicolon2',
        'forEndOfLoop',
        'closeParen',
        'colon',
        'statements',
        'endFor',
        'endForSemicolon'
    ];
}
