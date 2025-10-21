<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\ArrayElement;
use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Token;

class YieldExpression extends Expression {
    /** @var Token */
    public $yieldOrYieldFromKeyword;

    /** @var ArrayElement|null */
    public $arrayElement;

    const CHILD_NAMES = ['yieldOrYieldFromKeyword', 'arrayElement'];
}
