<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

use Microsoft\PhpParser\Node\NamespaceUseClause;
use Microsoft\PhpParser\Node\NamespaceUseGroupClause;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Node\Statement\NamespaceDefinition;
use Microsoft\PhpParser\Node\Statement\NamespaceUseDeclaration;

trait NamespacedNameTrait {
    public abstract function getNamespaceDefinition();
    public abstract function getFileContents() : string;
    public abstract function getNameParts() : array;

    /**
     * Gets resolved name from current namespace. Note that this is not necessarily the *actual* name
     * that is resolved during compilation or at runtime. For that, see QualifiedName::getResolvedName().
     *
     * @return ResolvedName
     */
    public function getNamespacedName() : ResolvedName {
        $namespaceDefinition = $this->getNamespaceDefinition();
        $content = $this->getFileContents();
        if ($namespaceDefinition === null) {
            // global namespace -> strip namespace\ prefix
            return ResolvedName::buildName($this->getNameParts(), $content);
        }

        if ($namespaceDefinition->name instanceof QualifiedName) {
            $resolvedName = ResolvedName::buildName($namespaceDefinition->name->nameParts, $content);
        } else {
            $resolvedName = ResolvedName::buildName([], $content);
        }
        if (
            !($this instanceof QualifiedName && (
            ($this->parent instanceof NamespaceDefinition) ||
            ($this->parent instanceof NamespaceUseDeclaration) ||
            ($this->parent instanceof NamespaceUseClause) ||
            ($this->parent instanceof NamespaceUseGroupClause)))
        ) {
            $resolvedName->addNameParts($this->getNameParts(), $content);
        }
        return $resolvedName;
    }
}
