<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

use Microsoft\PhpParser\Node\NamespaceUseClause;
use Microsoft\PhpParser\Node\NamespaceUseGroupClause;
use Microsoft\PhpParser\Node\SourceFileNode;
use Microsoft\PhpParser\Node\Statement\NamespaceDefinition;
use Microsoft\PhpParser\Node\Statement\NamespaceUseDeclaration;
use ReturnTypeWillChange;

abstract class Node implements \JsonSerializable {
    const CHILD_NAMES = [];

    /** @var array[] Map from node class to array of child keys */
    private static $childNames = [];

    /** @var Node|null */
    public $parent;

    public function getNodeKindName() : string {
        // Use strrpos (rather than explode) to avoid creating a temporary array.
        return substr(static::class, strrpos(static::class, "\\") + 1);
    }

    /**
     * Gets start position of Node, not including leading comments and whitespace.
     * @return int
     * @throws \Exception
     */
    public function getStartPosition() : int {
        return $this->getChildNodesAndTokens()->current()->getStartPosition();
    }

    /**
     * Gets start position of Node, including leading comments and whitespace
     * @return int
     * @throws \Exception
     */
    public function getFullStartPosition() : int {
        foreach($this::CHILD_NAMES as $name) {

            if (($child = $this->$name) !== null) {

                if (\is_array($child)) {
                    if(!isset($child[0])) {
                        continue;
                    }
                    $child = $child[0];
                }

                return $child->getFullStartPosition();
            }
        };

        throw new \RuntimeException("Could not resolve full start position");
    }

    /**
     * Gets parent of current node (returns null if has no parent)
     * @return null|Node
     */
    public function getParent() {
        return $this->parent;
    }

    /**
     * Gets first ancestor that is an instance of one of the provided classes.
     * Returns null if there is no match.
     *
     * @param string ...$classNames
     * @return Node|null
     */
    public function getFirstAncestor(...$classNames) {
        $ancestor = $this;
        while (($ancestor = $ancestor->parent) !== null) {
            foreach ($classNames as $className) {
                if ($ancestor instanceof $className) {
                    return $ancestor;
                }
            }
        }
        return null;
    }

    /**
     * Gets first child that is an instance of one of the provided classes.
     * Returns null if there is no match.
     *
     * @param string ...$classNames
     * @return Node|null
     */
    public function getFirstChildNode(...$classNames) {
        foreach ($this::CHILD_NAMES as $name) {
            $val = $this->$name;
            foreach ($classNames as $className) {
                if (\is_array($val)) {
                    foreach ($val as $child) {
                        if ($child instanceof $className) {
                            return $child;
                        }
                    }
                    continue;
                } elseif ($val instanceof $className) {
                    return $val;
                }
            }
        }
        return null;
    }

    /**
     * Gets first descendant node that is an instance of one of the provided classes.
     * Returns null if there is no match.
     *
     * @param string ...$classNames
     * @return Node|null
     */
    public function getFirstDescendantNode(...$classNames) {
        foreach ($this->getDescendantNodes() as $descendant) {
            foreach ($classNames as $className) {
                if ($descendant instanceof $className) {
                    return $descendant;
                }
            }
        }
        return null;
    }

    /**
     * Gets root of the syntax tree (returns self if has no parents)
     * @return SourceFileNode (expect root to be SourceFileNode unless the tree was manipulated)
     */
    public function getRoot() : Node {
        $node = $this;
        while ($node->parent !== null) {
            $node = $node->parent;
        }

        /** @var SourceFileNode $node */
        return $node;
    }

    /**
     * Gets generator containing all descendant Nodes and Tokens.
     *
     * @param callable|null $shouldDescendIntoChildrenFn
     * @return \Generator|Node[]|Token[]
     */
    public function getDescendantNodesAndTokens(?callable $shouldDescendIntoChildrenFn = null) {
        // TODO - write unit tests to prove invariants
        // (concatenating all descendant Tokens should produce document, concatenating all Nodes should produce document)
        foreach ($this->getChildNodesAndTokens() as $child) {
            // Check possible types of $child, most frequent first
            if ($child instanceof Node) {
                yield $child;
                if ($shouldDescendIntoChildrenFn === null || $shouldDescendIntoChildrenFn($child)) {
                   yield from $child->getDescendantNodesAndTokens($shouldDescendIntoChildrenFn);
                }
            } elseif ($child instanceof Token) {
                yield $child;
            }
        }
    }

    /**
     * Iterate over all descendant Nodes and Tokens, calling $callback.
     * This can often be faster than getDescendantNodesAndTokens
     * if you just need to call something and don't need a generator.
     *
     * @param callable $callback a callback that accepts Node|Token
     * @param callable|null $shouldDescendIntoChildrenFn
     * @return void
     */
    public function walkDescendantNodesAndTokens(callable $callback, ?callable $shouldDescendIntoChildrenFn = null) {
        // TODO - write unit tests to prove invariants
        // (concatenating all descendant Tokens should produce document, concatenating all Nodes should produce document)
        foreach (static::CHILD_NAMES as $name) {
            $child = $this->$name;
            // Check possible types of $child, most frequent first
            if ($child instanceof Token) {
                $callback($child);
            } elseif ($child instanceof Node) {
                $callback($child);
                if ($shouldDescendIntoChildrenFn === null || $shouldDescendIntoChildrenFn($child)) {
                   $child->walkDescendantNodesAndTokens($callback, $shouldDescendIntoChildrenFn);
                }
            } elseif (\is_array($child)) {
                foreach ($child as $childElement) {
                    if ($childElement instanceof Token) {
                        $callback($childElement);
                    } elseif ($childElement instanceof Node) {
                        $callback($childElement);
                        if ($shouldDescendIntoChildrenFn === null || $shouldDescendIntoChildrenFn($childElement)) {
                           $childElement->walkDescendantNodesAndTokens($callback, $shouldDescendIntoChildrenFn);
                        }
                    }
                }
            }
        }
    }

    /**
     * Gets a generator containing all descendant Nodes.
     * @param callable|null $shouldDescendIntoChildrenFn
     * @return \Generator|Node[]
     */
    public function getDescendantNodes(?callable $shouldDescendIntoChildrenFn = null) {
        foreach ($this->getChildNodes() as $child) {
            yield $child;
            if ($shouldDescendIntoChildrenFn === null || $shouldDescendIntoChildrenFn($child)) {
                yield from $child->getDescendantNodes($shouldDescendIntoChildrenFn);
            }
        }
    }

    /**
     * Gets generator containing all descendant Tokens.
     * @param callable|null $shouldDescendIntoChildrenFn
     * @return \Generator|Token[]
     */
    public function getDescendantTokens(?callable $shouldDescendIntoChildrenFn = null) {
        foreach ($this->getChildNodesAndTokens() as $child) {
            if ($child instanceof Node) {
                if ($shouldDescendIntoChildrenFn == null || $shouldDescendIntoChildrenFn($child)) {
                    yield from $child->getDescendantTokens($shouldDescendIntoChildrenFn);
                }
            } elseif ($child instanceof Token) {
                yield $child;
            }
        }
    }

    /**
     * Gets generator containing all child Nodes and Tokens (direct descendants).
     * Does not return null elements.
     *
     * @return \Generator|Token[]|Node[]
     */
    public function getChildNodesAndTokens() : \Generator {
        foreach ($this::CHILD_NAMES as $name) {
            $val = $this->$name;

            if (\is_array($val)) {
                foreach ($val as $child) {
                    if ($child !== null) {
                        yield $name => $child;
                    }
                }
                continue;
            }
            if ($val !== null) {
                yield $name => $val;
            }
        }
    }

    /**
     * Gets generator containing all child Nodes (direct descendants)
     * @return \Generator|Node[]
     */
    public function getChildNodes() : \Generator {
        foreach ($this::CHILD_NAMES as $name) {
            $val = $this->$name;
            if (\is_array($val)) {
                foreach ($val as $child) {
                    if ($child instanceof Node) {
                        yield $child;
                    }
                }
                continue;
            } elseif ($val instanceof Node) {
                yield $val;
            }
        }
    }

    /**
     * Gets generator containing all child Tokens (direct descendants)
     *
     * @return \Generator|Token[]
     */
    public function getChildTokens() {
        foreach ($this::CHILD_NAMES as $name) {
            $val = $this->$name;
            if (\is_array($val)) {
                foreach ($val as $child) {
                    if ($child instanceof Token) {
                        yield $child;
                    }
                }
                continue;
            } elseif ($val instanceof Token) {
                yield $val;
            }
        }
    }

    /**
     * Gets array of declared child names (cached).
     *
     * This is used as an optimization when iterating over nodes: For direct iteration
     * PHP will create a properties hashtable on the object, thus doubling memory usage.
     * We avoid this by iterating over just the names instead.
     *
     * @return string[]
     */
    public function getChildNames() {
        return $this::CHILD_NAMES;
    }

    /**
     * Gets width of a Node (not including comment / whitespace trivia)
     *
     * @return int
     */
    public function getWidth() : int {
        $first = $this->getStartPosition();
        $last = $this->getEndPosition();

        return $last - $first;
    }

    /**
     * Gets width of a Node (including comment / whitespace trivia)
     *
     * @return int
     */
    public function getFullWidth() : int {
        $first = $this->getFullStartPosition();
        $last = $this->getEndPosition();

        return $last - $first;
    }

    /**
     * Gets string representing Node text (not including leading comment + whitespace trivia)
     * @return string
     */
    public function getText() : string {
        $start = $this->getStartPosition();
        $end = $this->getEndPosition();

        $fileContents = $this->getFileContents();
        return \substr($fileContents, $start, $end - $start);
    }

    /**
     * Gets full text of Node (including leading comment + whitespace trivia)
     * @return string
     */
    public function getFullText() : string {
        $start = $this->getFullStartPosition();
        $end = $this->getEndPosition();

        $fileContents = $this->getFileContents();
        return \substr($fileContents, $start, $end - $start);

    }

    /**
     * Gets string representing Node's leading comment and whitespace text.
     * @return string
     */
    public function getLeadingCommentAndWhitespaceText() : string {
        // TODO re-tokenize comments and whitespace
        $fileContents = $this->getFileContents();
        foreach ($this->getDescendantTokens() as $token) {
            return $token->getLeadingCommentsAndWhitespaceText($fileContents);
        }
        return '';
    }

    protected function getChildrenKvPairs() {
        $result = [];
        foreach ($this::CHILD_NAMES as $name) {
            $result[$name] = $this->$name;
        }
        return $result;
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize() {
        $kindName = $this->getNodeKindName();
        return ["$kindName" => $this->getChildrenKvPairs()];
    }

    /**
     * Get the end index of a Node.
     * @return int
     * @throws \Exception
     */
    public function getEndPosition() {
        // TODO test invariant - start of next node is end of previous node
        for ($i = \count($childKeys = $this::CHILD_NAMES) - 1; $i >= 0; $i--) {
            $lastChildKey = $childKeys[$i];
            $lastChild = $this->$lastChildKey;

            if (\is_array($lastChild)) {
                $lastChild = \end($lastChild);
            }

            if ($lastChild instanceof Token) {
                return $lastChild->fullStart + $lastChild->length;
            } elseif ($lastChild instanceof Node) {
                return $lastChild->getEndPosition();
            }
        }

        throw new \Exception("Unhandled node type");
    }

    public function getFileContents() : string {
        // TODO consider renaming to getSourceText
        return $this->getRoot()->fileContents;
    }

    public function getUri() : ?string {
        return $this->getRoot()->uri;
    }

    public function getLastChild() {
        $a = iterator_to_array($this->getChildNodesAndTokens());
        return \end($a);
    }

    /**
     * Searches descendants to find a Node at the given position.
     *
     * @param int $pos
     * @return Node
     */
    public function getDescendantNodeAtPosition(int $pos) {
        foreach ($this->getChildNodes() as $child) {
            if ($child->containsPosition($pos)) {
                return $child->getDescendantNodeAtPosition($pos);
            }
        }

        return $this;
    }

    /**
     * Returns true if the given Node or Token contains the given position.
     * @param int $pos
     * @return bool
     */
    private function containsPosition(int $pos): bool {
        return $this->getStartPosition() <= $pos && $pos <= $this->getEndPosition();
    }

    /**
     * Gets leading PHP Doc Comment text corresponding to the current Node.
     * Returns last doc comment in leading comment / whitespace trivia,
     * and returns null if there is no preceding doc comment.
     *
     * @return string|null
     */
    public function getDocCommentText() {
        $leadingTriviaText = $this->getLeadingCommentAndWhitespaceText();
        $leadingTriviaTokens = PhpTokenizer::getTokensArrayFromContent(
            $leadingTriviaText, ParseContext::SourceElements, $this->getFullStartPosition(), false
        );
        for ($i = \count($leadingTriviaTokens) - 1; $i >= 0; $i--) {
            $token = $leadingTriviaTokens[$i];
            if ($token->kind === TokenKind::DocCommentToken) {
                return $token->getText($this->getFileContents());
            }
        }
        return null;
    }

    public function __toString() {
        return $this->getText();
    }

    /**
     * @return array|ResolvedName[][]
     * @throws \Exception
     */
    public function getImportTablesForCurrentScope() {
        $namespaceDefinition = $this->getNamespaceDefinition();

        // Use declarations can exist in either the global scope, or inside namespace declarations.
        // http://php.net/manual/en/language.namespaces.importing.php#language.namespaces.importing.scope
        //
        // The only code allowed before a namespace declaration is a declare statement, and sub-namespaces are
        // additionally unaffected by by import rules of higher-level namespaces. Therefore, we can make the assumption
        // that we need not travel up the spine any further once we've found the current namespace.
        // http://php.net/manual/en/language.namespaces.definition.php
        if ($namespaceDefinition instanceof NamespaceDefinition) {
            $topLevelNamespaceStatements = $namespaceDefinition->compoundStatementOrSemicolon instanceof Token
                ? $namespaceDefinition->parent->statementList // we need to start from the namespace definition.
                : $namespaceDefinition->compoundStatementOrSemicolon->statements;
            $namespaceFullStart = $namespaceDefinition->getFullStartPosition();
        } else {
            $topLevelNamespaceStatements = $this->getRoot()->statementList;
            $namespaceFullStart = 0;
        }

        $nodeFullStart = $this->getFullStartPosition();

        // TODO optimize performance
        // Currently we rebuild the import tables on every call (and therefore every name resolution operation)
        // It is likely that a consumer will attempt many consecutive name resolution requests within the same file.
        // Therefore, we can consider optimizing on the basis of the "most recently used" import table set.
        // The idea: Keep a single set of import tables cached based on a unique root node id, and invalidate
        // cache whenever we attempt to resolve a qualified name with a different root node.
        //
        // In order to make this work, it will probably make sense to change the way we parse namespace definitions.
        // https://github.com/Microsoft/tolerant-php-parser/issues/81
        //
        // Currently the namespace definition only includes a compound statement or semicolon token as one if it's children.
        // Instead, we should move to a model where we parse future statements as a child rather than as a separate
        // statement. This would enable us to retrieve all the information we would need to find the fully qualified
        // name by simply traveling up the spine to find the first ancestor of type NamespaceDefinition.
        $namespaceImportTable = $functionImportTable = $constImportTable = [];
        $contents = $this->getFileContents();

        foreach ($topLevelNamespaceStatements as $useDeclaration) {
            if ($useDeclaration->getFullStartPosition() <= $namespaceFullStart) {
                continue;
            }
            if ($useDeclaration->getFullStartPosition() > $nodeFullStart) {
                break;
            } elseif (!($useDeclaration instanceof NamespaceUseDeclaration)) {
                continue;
            }

            // TODO fix getValues
            foreach ((isset($useDeclaration->useClauses) ? $useDeclaration->useClauses->getValues() : []) as $useClause) {
                $namespaceNamePartsPrefix = $useClause->namespaceName !== null ? $useClause->namespaceName->nameParts : [];

                if ($useClause->groupClauses !== null && $useClause instanceof NamespaceUseClause) {
                    // use A\B\C\{D\E};                 namespace import: ["E" => [A,B,C,D,E]]
                    // use A\B\C\{D\E as F};            namespace import: ["F" => [A,B,C,D,E]]
                    // use function A\B\C\{A, B}        function import: ["A" => [A,B,C,A], "B" => [A,B,C]]
                    // use function A\B\C\{const A}     const import: ["A" => [A,B,C,A]]
                    foreach ($useClause->groupClauses->children as $groupClause) {
                        if (!($groupClause instanceof NamespaceUseGroupClause)) {
                            continue;
                        }
                        $namespaceNameParts = \array_merge($namespaceNamePartsPrefix, $groupClause->namespaceName->nameParts);
                        $functionOrConst = $groupClause->functionOrConst ?? $useDeclaration->functionOrConst;
                        $alias = $groupClause->namespaceAliasingClause === null
                            ? $groupClause->namespaceName->getLastNamePart()->getText($contents)
                            : $groupClause->namespaceAliasingClause->name->getText($contents);

                        $this->addToImportTable(
                            $alias, $functionOrConst, $namespaceNameParts, $contents,
                            $namespaceImportTable, $functionImportTable, $constImportTable
                        );
                    }
                } else {
                    // use A\B\C;               namespace import: ["C" => [A,B,C]]
                    // use A\B\C as D;          namespace import: ["D" => [A,B,C]]
                    // use function A\B\C as D  function import: ["D" => [A,B,C]]
                    // use A\B, C\D;            namespace import: ["B" => [A,B], "D" => [C,D]]
                    $alias = $useClause->namespaceAliasingClause === null
                        ? $useClause->namespaceName->getLastNamePart()->getText($contents)
                        : $useClause->namespaceAliasingClause->name->getText($contents);
                    $functionOrConst = $useDeclaration->functionOrConst;
                    $namespaceNameParts = $namespaceNamePartsPrefix;

                    $this->addToImportTable(
                        $alias, $functionOrConst, $namespaceNameParts, $contents,
                        $namespaceImportTable, $functionImportTable, $constImportTable
                    );
                }
            }
        }

        return [$namespaceImportTable, $functionImportTable, $constImportTable];
    }

    /**
     * Gets corresponding NamespaceDefinition for Node. Returns null if in global namespace.
     *
     * @return NamespaceDefinition|null
     */
    public function getNamespaceDefinition() {
        $namespaceDefinition = ($this instanceof NamespaceDefinition || $this instanceof SourceFileNode)
            ? $this
            : $this->getFirstAncestor(NamespaceDefinition::class, SourceFileNode::class);

        /** @phpstan-ignore-next-line result is always false -- not sure this can happen */
        if ($namespaceDefinition instanceof NamespaceDefinition && !($namespaceDefinition->parent instanceof SourceFileNode)) {
            $namespaceDefinition = $namespaceDefinition->getFirstAncestor(SourceFileNode::class);
        }

        if ($namespaceDefinition === null) {
            // TODO provide a way to throw errors without crashing consumer
            throw new \Exception("Invalid tree - SourceFileNode must always exist at root of tree.");
        }

        $fullStart = $this->getFullStartPosition();
        $lastNamespaceDefinition = null;
        if ($namespaceDefinition instanceof SourceFileNode) {
            foreach ($namespaceDefinition->getChildNodes() as $childNode) {
                if ($childNode instanceof NamespaceDefinition && $childNode->getFullStartPosition() < $fullStart) {
                    $lastNamespaceDefinition = $childNode;
                }
            }
        }

        if ($lastNamespaceDefinition !== null && $lastNamespaceDefinition->compoundStatementOrSemicolon instanceof Token) {
            $namespaceDefinition = $lastNamespaceDefinition;
        } elseif ($namespaceDefinition instanceof SourceFileNode) {
            $namespaceDefinition = null;
        }

        /** @var NamespaceDefinition|null $namespaceDefinition */
        return $namespaceDefinition;
    }

    public function getPreviousSibling() {
        // TODO make more efficient
        $parent = $this->parent;
        if ($parent === null) {
            return null;
        }

        $prevSibling = null;

        foreach ($parent::CHILD_NAMES as $name) {
            $val = $parent->$name;
            if (\is_array($val)) {
                foreach ($val as $sibling) {
                    if ($sibling === $this) {
                        return $prevSibling;
                    } elseif ($sibling instanceof Node) {
                        $prevSibling = $sibling;
                    }
                }
                continue;
            } elseif ($val instanceof Node) {
                if ($val === $this) {
                    return $prevSibling;
                }
                $prevSibling = $val;
            }
        }
        return null;
    }

    /**
     * Add the alias and resolved name to the corresponding namespace, function, or const import table.
     * If the alias already exists, it will get replaced by the most recent using.
     *
     * TODO - worth throwing an error here instead?
     */
    private function addToImportTable($alias, $functionOrConst, $namespaceNameParts, $contents, & $namespaceImportTable, & $functionImportTable, & $constImportTable):array
    {
        if ($alias !== null) {
            if ($functionOrConst === null) {
                // namespaces are case-insensitive
//                $alias = \strtolower($alias);
                $namespaceImportTable[$alias] = ResolvedName::buildName($namespaceNameParts, $contents);
                return [$namespaceImportTable, $functionImportTable, $constImportTable];
            } elseif ($functionOrConst->kind === TokenKind::FunctionKeyword) {
                // functions are case-insensitive
//                $alias = \strtolower($alias);
                $functionImportTable[$alias] = ResolvedName::buildName($namespaceNameParts, $contents);
                return [$namespaceImportTable, $functionImportTable, $constImportTable];
            } elseif ($functionOrConst->kind === TokenKind::ConstKeyword) {
                // constants are case-sensitive
                $constImportTable[$alias] = ResolvedName::buildName($namespaceNameParts, $contents);
                return [$namespaceImportTable, $functionImportTable, $constImportTable];
            }
            return [$namespaceImportTable, $functionImportTable, $constImportTable];
        }
        return [$namespaceImportTable, $functionImportTable, $constImportTable];
    }

    /**
     * This is overridden in subclasses
     * @return Diagnostic|null - Callers should use DiagnosticsProvider::getDiagnostics instead
     * @internal
     */
    public function getDiagnosticForNode() {
        return null;
    }
}
