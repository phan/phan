<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Statement;

use Microsoft\PhpParser\MissingToken;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Node\StatementNode;
use Microsoft\PhpParser\Token;
use Microsoft\PhpParser\Node\SourceFileNode;

/**
 * @property SourceFileNode $parent
 */
class NamespaceDefinition extends StatementNode {
    /** @var Token */
    public $namespaceKeyword;
    /** @var QualifiedName|null|MissingToken */
    public $name;
    /** @var CompoundStatementNode|Token */
    public $compoundStatementOrSemicolon;

    const CHILD_NAMES = [
        'namespaceKeyword',
        'name',
        'compoundStatementOrSemicolon'
    ];
}
