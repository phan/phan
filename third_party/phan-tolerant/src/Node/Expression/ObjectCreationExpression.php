<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Expression;

use Microsoft\PhpParser\Node\AttributeGroup;
use Microsoft\PhpParser\Node\ClassBaseClause;
use Microsoft\PhpParser\Node\ClassInterfaceClause;
use Microsoft\PhpParser\Node\ClassMembersNode;
use Microsoft\PhpParser\Node\DelimitedList;
use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Token;

class ObjectCreationExpression extends Expression {

    /** @var Token */
    public $newKeword;

    /** @var AttributeGroup[]|null optional attributes of an anonymous class. */
    public $attributes;

    /** @var QualifiedName|Variable|Token */
    public $classTypeDesignator;

    /** @var Token|null */
    public $openParen;

    /** @var DelimitedList\ArgumentExpressionList|null  */
    public $argumentExpressionList;

    /** @var Token|null */
    public $closeParen;

    /** @var ClassBaseClause|null */
    public $classBaseClause;

    /** @var ClassInterfaceClause|null */
    public $classInterfaceClause;

    /** @var ClassMembersNode|null */
    public $classMembers;

    const CHILD_NAMES = [
        'newKeword',
        'attributes',
        'classTypeDesignator',
        'openParen',
        'argumentExpressionList',
        'closeParen',
        'classBaseClause',
        'classInterfaceClause',
        'classMembers'
    ];
}
