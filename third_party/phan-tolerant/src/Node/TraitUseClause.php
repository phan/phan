<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

class TraitUseClause extends Node {
    /** @var Token */
    public $useKeyword;

    /** @var DelimitedList\QualifiedNameList */
    public $traitNameList;

    /** @var Token */
    public $semicolonOrOpenBrace;

    /** @var DelimitedList\TraitSelectOrAliasClauseList */
    public $traitSelectAndAliasClauses;

    /** @var Token */
    public $closeBrace;

    const CHILD_NAMES = [
        'useKeyword',
        'traitNameList',
        'semicolonOrOpenBrace',
        'traitSelectAndAliasClauses',
        'closeBrace'
    ];
}
