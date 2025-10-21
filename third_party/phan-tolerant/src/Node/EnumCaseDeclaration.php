<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

class EnumCaseDeclaration extends Node {
    /** @var AttributeGroup[]|null */
    public $attributes;

    /** @var Token */
    public $caseKeyword;

    /** @var Token */
    public $name;

    /** @var Token|null */
    public $equalsToken;

    /** @var Token|Node|null */
    public $assignment;

    /** @var Token */
    public $semicolon;

    const CHILD_NAMES = [
        'attributes',
        'caseKeyword',
        'name',
        'equalsToken',
        'assignment',
        'semicolon',
    ];
}
