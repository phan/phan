<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\NamespacedNameInterface;
use Microsoft\PhpParser\NamespacedNameTrait;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Expression\AnonymousFunctionCreationExpression;
use Microsoft\PhpParser\Node\Expression\ArrowFunctionCreationExpression;
use Microsoft\PhpParser\Node\Expression\CallExpression;
use Microsoft\PhpParser\Node\Expression\ObjectCreationExpression;
use Microsoft\PhpParser\ResolvedName;
use Microsoft\PhpParser\Token;
use Microsoft\PhpParser\TokenKind;

class QualifiedName extends Node implements NamespacedNameInterface {
    use NamespacedNameTrait;

    /** @var Token|null */
    public $globalSpecifier; // \_opt
    /** @var RelativeSpecifier|null */
    public $relativeSpecifier; // namespace\
    /** @var array */
    public $nameParts;

    const CHILD_NAMES = [
        'globalSpecifier',
        'relativeSpecifier',
        'nameParts'
    ];

    /**
     * Checks whether a QualifiedName is prefixed with a backslash global specifier.
     * @return bool
     */
    public function isFullyQualifiedName() : bool {
        return isset($this->globalSpecifier);
    }

    /**
     * Checks whether a QualifiedName begins with a "namespace" keyword
     * @return bool
     */
    public function isRelativeName() : bool {
        return isset($this->relativeSpecifier);
    }

    /**
     * Checks whether a Name includes at least one namespace separator (and is neither fully-qualified nor relative)
     * @return bool
     */
    public function isQualifiedName() : bool {
        return
            !$this->isFullyQualifiedName() &&
            !$this->isRelativeName() &&
            \count($this->nameParts) > 1; // at least one namespace separator
    }

    /**
     * Checks whether a name is does not include a namespace separator.
     * @return bool
     */
    public function isUnqualifiedName() : bool {
        return !($this->isFullyQualifiedName() || $this->isRelativeName() || $this->isQualifiedName());
    }

    /**
     * Gets resolved name based on name resolution rules defined in:
     * http://php.net/manual/en/language.namespaces.rules.php
     *
     * Returns null if one of the following conditions is met:
     * - name resolution is not valid for this element (e.g. part of the name in a namespace definition)
     * - name cannot be resolved (unqualified namespaced function/constant names that are not explicitly imported.)
     *
     * @return null|string|ResolvedName
     * @throws \Exception
     */
    public function getResolvedName($namespaceDefinition = null) {
        // Name resolution not applicable to constructs that define symbol names or aliases.
        if (($this->parent instanceof Node\Statement\NamespaceDefinition && $this->parent->name->getStartPosition() === $this->getStartPosition()) ||
            $this->parent instanceof Node\Statement\NamespaceUseDeclaration ||
            $this->parent instanceof Node\NamespaceUseClause ||
            $this->parent instanceof Node\NamespaceUseGroupClause ||
            $this->parent instanceof Node\TraitSelectOrAliasClause
        ) {
            return null;
        }

        if (in_array($lowerText = strtolower($this->getText()), ["self", "static", "parent"])) {
            return $lowerText;
        }

        // FULLY QUALIFIED NAMES
        // - resolve to the name without leading namespace separator.
        if ($this->isFullyQualifiedName()) {
            return ResolvedName::buildName($this->nameParts, $this->getFileContents());
        }

        // RELATIVE NAMES
        // - resolve to the name with namespace replaced by the current namespace.
        // - if current namespace is global, strip leading namespace\ prefix.
        if ($this->isRelativeName()) {
            return $this->getNamespacedName();
        }

        [$namespaceImportTable, $functionImportTable, $constImportTable] = $this->getImportTablesForCurrentScope();

        // QUALIFIED NAMES
        // - first segment of the name is translated according to the current class/namespace import table.
        // - If no import rule applies, the current namespace is prepended to the name.
        if ($this->isQualifiedName()) {
            return $this->tryResolveFromImportTable($namespaceImportTable) ?? $this->getNamespacedName();
        }

        // UNQUALIFIED NAMES
        // - translated according to the current import table for the respective symbol type.
        //   (class-like => namespace import table, constant => const import table, function => function import table)
        // - if no import rule applies:
        //   - all symbol types: if current namespace is global, resolve to global namespace.
        //   - class-like symbols: resolve from current namespace.
        //   - function or const: resolved at runtime (from current namespace, with fallback to global namespace).
        if ($this->isConstantName()) {
            $resolvedName = $this->tryResolveFromImportTable($constImportTable, /* case-sensitive */ true);
            $namespaceDefinition = $this->getNamespaceDefinition();
            if ($namespaceDefinition !== null && $namespaceDefinition->name === null) {
                $resolvedName = $resolvedName ?? ResolvedName::buildName($this->nameParts, $this->getFileContents());
            }
            return $resolvedName;
        } elseif ($this->parent instanceof CallExpression) {
            $resolvedName = $this->tryResolveFromImportTable($functionImportTable);
            if (($namespaceDefinition = $this->getNamespaceDefinition()) === null || $namespaceDefinition->name === null) {
                $resolvedName = $resolvedName ?? ResolvedName::buildName($this->nameParts, $this->getFileContents());
            }
            return $resolvedName;
        }

        return $this->tryResolveFromImportTable($namespaceImportTable) ?? $this->getNamespacedName();
    }

    public function getLastNamePart() {
        $parts = $this->nameParts;
        for ($i = \count($parts) - 1; $i >= 0; $i--) {
            // TODO - also handle reserved word tokens
            if ($parts[$i]->kind === TokenKind::Name) {
                return $parts[$i];
            }
        }
        return null;
    }

    /**
     * @param ResolvedName[] $importTable
     */
    private function tryResolveFromImportTable($importTable, bool $isCaseSensitive = false): ?ResolvedName {
        $content = $this->getFileContents();
        $index = $this->nameParts[0]->getText($content);
//        if (!$isCaseSensitive) {
//            $index = strtolower($index);
//        }
        if(isset($importTable[$index])) {
            $resolvedName = $importTable[$index];
            $resolvedName->addNameParts(\array_slice($this->nameParts, 1), $content);
            return $resolvedName;
        }
        return null;
    }

    private function isConstantName() : bool {
        return
            ($this->parent instanceof Node\Statement\ExpressionStatement || $this->parent instanceof Expression) &&
            !(
                $this->parent instanceof Node\Expression\MemberAccessExpression || $this->parent instanceof CallExpression ||
                $this->parent instanceof ObjectCreationExpression ||
                $this->parent instanceof Node\Expression\ScopedPropertyAccessExpression || $this->parent instanceof AnonymousFunctionCreationExpression ||
                $this->parent instanceof ArrowFunctionCreationExpression ||
                ($this->parent instanceof Node\Expression\BinaryExpression && $this->parent->operator->kind === TokenKind::InstanceOfKeyword)
            );
    }

    public function getNameParts() : array {
        return $this->nameParts;
    }
}
