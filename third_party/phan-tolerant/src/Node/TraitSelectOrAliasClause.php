<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\ModifiedTypeInterface;
use Microsoft\PhpParser\ModifiedTypeTrait;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\DelimitedList\QualifiedNameList;
use Microsoft\PhpParser\Token;

class TraitSelectOrAliasClause extends Node implements ModifiedTypeInterface {
    use ModifiedTypeTrait;

    /** @var QualifiedName|Node\Expression\ScopedPropertyAccessExpression */
    public $name;

    /** @var Token */
    public $asOrInsteadOfKeyword;

    /**
     * @var QualifiedNameList|QualifiedName depends on the keyword
     */
    public $targetNameList;

    const CHILD_NAMES = [
        'name',
        'asOrInsteadOfKeyword',
        'modifiers',
        'targetNameList',
    ];
}
