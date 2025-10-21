<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Statement;

use Microsoft\PhpParser\FunctionLike;
use Microsoft\PhpParser\NamespacedNameInterface;
use Microsoft\PhpParser\NamespacedNameTrait;
use Microsoft\PhpParser\Node\FunctionBody;
use Microsoft\PhpParser\Node\FunctionHeader;
use Microsoft\PhpParser\Node\FunctionReturnType;
use Microsoft\PhpParser\Node\StatementNode;

class FunctionDeclaration extends StatementNode implements NamespacedNameInterface, FunctionLike {
    use FunctionHeader, FunctionReturnType, FunctionBody;
    use NamespacedNameTrait;

    const CHILD_NAMES = [
        // FunctionHeader
        'attributes',
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

        // FunctionBody
        'compoundStatementOrSemicolon'
    ];

    public function getNameParts() : array {
        return [$this->name];
    }
}
