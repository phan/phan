<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Statement;

use Microsoft\PhpParser\ClassLike;
use Microsoft\PhpParser\NamespacedNameInterface;
use Microsoft\PhpParser\NamespacedNameTrait;
use Microsoft\PhpParser\Node\AttributeGroup;
use Microsoft\PhpParser\Node\InterfaceBaseClause;
use Microsoft\PhpParser\Node\InterfaceMembers;
use Microsoft\PhpParser\Node\StatementNode;
use Microsoft\PhpParser\Token;

class InterfaceDeclaration extends StatementNode implements NamespacedNameInterface, ClassLike {
    use NamespacedNameTrait;

    /** @var AttributeGroup[]|null */
    public $attributes;

    /** @var Token */
    public $interfaceKeyword;

    /** @var Token */
    public $name;

    /** @var InterfaceBaseClause|null */
    public $interfaceBaseClause;

    /** @var InterfaceMembers */
    public $interfaceMembers;

    const CHILD_NAMES = [
        'attributes',
        'interfaceKeyword',
        'name',
        'interfaceBaseClause',
        'interfaceMembers'
    ];

    public function getNameParts() : array {
        return [$this->name];
    }
}
