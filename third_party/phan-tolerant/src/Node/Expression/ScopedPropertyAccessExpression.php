<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Token;

class ScopedPropertyAccessExpression extends Expression {

    /** @var Expression|QualifiedName|Token */
    public $scopeResolutionQualifier;

    /** @var Token */
    public $doubleColon;

    /** @var Token|Variable */
    public $memberName;

    const CHILD_NAMES = [
        'scopeResolutionQualifier',
        'doubleColon',
        'memberName'
    ];
}
