<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\DelimitedList;
use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Token;

class ArrayCreationExpression extends Expression {

    /** @var Token|null */
    public $arrayKeyword;

    /** @var Token */
    public $openParenOrBracket;

    /** @var DelimitedList\ArrayElementList */
    public $arrayElements;

    /** @var Token */
    public $closeParenOrBracket;

    const CHILD_NAMES = [
        'arrayKeyword',
        'openParenOrBracket',
        'arrayElements',
        'closeParenOrBracket'
    ];
}
