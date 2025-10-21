<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\FunctionLike;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Node\FunctionHeader;
use Microsoft\PhpParser\Node\FunctionReturnType;
use Microsoft\PhpParser\Token;

class ArrowFunctionCreationExpression extends Expression implements FunctionLike {
    /** @var Token|null */
    public $staticModifier;

    use FunctionHeader, FunctionReturnType;

    /** @var Token `=>` */
    public $arrowToken;

    /** @var Node|Token */
    public $resultExpression;

    const CHILD_NAMES = [
        'attributes',
        'staticModifier',

        // FunctionHeader
        'functionKeyword',
        'byRefToken',
        'name',
        'openParen',
        'parameters',
        'closeParen',

        // FunctionReturnType
        'colonToken',
        'questionToken',
        'returnTypeList',

        // body
        'arrowToken',
        'resultExpression',
    ];
}
