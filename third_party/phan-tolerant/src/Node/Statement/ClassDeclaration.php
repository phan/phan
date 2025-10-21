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
use Microsoft\PhpParser\Node\ClassBaseClause;
use Microsoft\PhpParser\Node\ClassInterfaceClause;
use Microsoft\PhpParser\Node\ClassMembersNode;
use Microsoft\PhpParser\Node\StatementNode;
use Microsoft\PhpParser\Token;

class ClassDeclaration extends StatementNode implements NamespacedNameInterface, ClassLike {
    use NamespacedNameTrait;

    /** @var AttributeGroup[]|null */
    public $attributes;

    /** @var Token abstract/final/readonly modifier */
    public $abstractOrFinalModifier;

    /** @var Token[] additional abstract/final/readonly modifiers */
    public $modifiers;

    /** @var Token */
    public $classKeyword;

    /** @var Token */
    public $name;

    /** @var ClassBaseClause */
    public $classBaseClause;

    /** @var ClassInterfaceClause */
    public $classInterfaceClause;

    /** @var ClassMembersNode */
    public $classMembers;

    const CHILD_NAMES = [
        'attributes',
        'abstractOrFinalModifier',
        'modifiers',
        'classKeyword',
        'name',
        'classBaseClause',
        'classInterfaceClause',
        'classMembers'
    ];

    public function getNameParts() : array {
        return [$this->name];
    }
}
