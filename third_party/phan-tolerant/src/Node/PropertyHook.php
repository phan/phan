<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\DelimitedList\ParameterDeclarationList;
use Microsoft\PhpParser\Node\Statement\CompoundStatementNode;
use Microsoft\PhpParser\Token;

class PropertyHook extends Node {
    /** @var AttributeGroup[]|null */
    public $attributes;

    /** @var Token|null */
    public $hookKeyword;

    /** @var Token|null */
    public $openParen;

    /** @var ParameterDeclarationList|null */
    public $parameterList;

    /** @var Token|null */
    public $closeParen;

    /** @var Token|null */
    public $arrowToken;

    /** @var Node|null */
    public $expression;

    /** @var CompoundStatementNode|null */
    public $compoundStatement;

    /** @var Token|null */
    public $semicolon;

    const CHILD_NAMES = [
        'attributes',
        'hookKeyword',
        'openParen',
        'parameterList',
        'closeParen',
        'arrowToken',
        'expression',
        'compoundStatement',
        'semicolon',
    ];
}
