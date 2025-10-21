<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Token;

trait FunctionHeader {
    /** @var AttributeGroup[]|null */
    public $attributes;
    /** @var Token */
    public $functionKeyword;
    /** @var Token */
    public $byRefToken;
    /** @var null|Token */
    public $name;
    /** @var Token */
    public $openParen;
    /** @var DelimitedList\ParameterDeclarationList */
    public $parameters;
    /** @var Token */
    public $closeParen;
}
