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
use Microsoft\PhpParser\Node\StatementNode;
use Microsoft\PhpParser\Node\TraitMembers;
use Microsoft\PhpParser\Token;

class TraitDeclaration extends StatementNode implements NamespacedNameInterface, ClassLike {
    use NamespacedNameTrait;

    /** @var AttributeGroup[]|null */
    public $attributes;

    /** @var Token */
    public $traitKeyword;

    /** @var Token */
    public $name;

    /** @var TraitMembers */
    public $traitMembers;

    const CHILD_NAMES = [
        'attributes',
        'traitKeyword',
        'name',
        'traitMembers'
    ];

    public function getNameParts() : array {
        return [$this->name];
    }
}
