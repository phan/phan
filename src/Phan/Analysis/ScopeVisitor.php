<?php

declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use ast;
use ast\Node;
use Phan\AST\AnalysisVisitor;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\FQSEN\FullyQualifiedGlobalStructuralElement;

use function implode;
use function rtrim;

/**
 * An abstract visitor with methods to track elements in the current scope.
 *
 * This tracks the current namespace and adds namespace and `use` information to the current scope.
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgument
 * @phan-file-suppress PhanPartialTypeMismatchArgumentInternal
 */
abstract class ScopeVisitor extends AnalysisVisitor
{

    /**
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    /*
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        parent::__construct($code_base, $context);
    }
     */

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node @phan-unused-param
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visit(Node $node): Context
    {
        // Many nodes don't change the context and we
        // don't need to read them.
        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_DECLARE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitDeclare(Node $node): Context
    {
        $declares = $node->children['declares'];
        $context = $this->context;
        foreach ($declares->children as $elem) {
            if (!$elem instanceof Node) {
                throw new AssertionError('Expected an array of declaration elements');
            }
            ['name' => $name, 'value' => $value] = $elem->children;
            if ('strict_types' === $name && \is_int($value)) {
                $context = $context->withStrictTypes($value);
            }
        }

        return $context;
    }

    /**
     * Visit a node with kind `\ast\AST_NAMESPACE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new context resulting from parsing the node
     */
    public function visitNamespace(Node $node): Context
    {
        $namespace = '\\' . (string)$node->children['name'];
        return $this->context->withNamespace($namespace);
    }

    /**
     * Visit a node with kind `\ast\AST_GROUP_USE`
     * such as `use \ast\Node;`.
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitGroupUse(Node $node): Context
    {
        $children = $node->children;

        $prefix = \array_shift($children);

        $context = $this->context;

        $alias_target_map = self::aliasTargetMapFromUseNode(
            $children['uses'],  // @phan-suppress-current-line PhanTypeMismatchArgumentNullable the key is also used by AST_CLOSURE
            $prefix,
            $node->flags ?? 0
        );
        foreach ($alias_target_map as $alias => [$flags, $target, $lineno]) {
            $this->warnAboutReservedUseAlias($alias, $flags, $lineno);
            $context = $context->withNamespaceMap(
                $flags,
                $alias,
                $target,
                $lineno,
                $this->code_base
            );
        }

        return $context;
    }

    /**
     * Visit a node with kind `\ast\AST_USE`
     * such as `use \ast\Node;`.
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitUse(Node $node): Context
    {
        $context = $this->context;

        foreach (self::aliasTargetMapFromUseNode($node) as $alias => [$flags, $target, $lineno]) {
            $flags = $node->flags ?: $flags;
            if (\strcasecmp($target->getNamespace(), $context->getNamespace()) === 0) {
                $this->maybeWarnSameNamespaceUse($alias, $target, $flags, $lineno);
            }
            $this->warnAboutReservedUseAlias($alias, $flags, $lineno);
            $context = $context->withNamespaceMap(
                $flags,
                $alias,
                $target,
                $lineno,
                $this->code_base
            );
        }

        return $context;
    }

    /**
     * Emit PhanDeprecatedReservedName for the declaration of a function, class, class constant or alias
     * with a name that PHP deprecates, to reserve it for future syntax.
     *
     * @param list<string> $deprecated_names the deprecated names for this kind of element, in lowercase
     * @param string $kind a description of the kind of element, e.g. 'a function'
     * @param int $min_php_version the php version id of the deprecation
     */
    protected function warnAboutNameReservedInFutureVersion(string $name, array $deprecated_names, string $kind, int $lineno, int $min_php_version = 80600): void
    {
        if (Config::get_closest_target_php_version_id() < $min_php_version) {
            return;
        }
        if (!\in_array(\strtolower($name), $deprecated_names, true)) {
            return;
        }
        $this->emitIssue(
            Issue::DeprecatedReservedName,
            $lineno,
            $name,
            $kind,
            \intdiv($min_php_version, 10000) . '.' . \intdiv($min_php_version % 10000, 100)
        );
    }

    /**
     * Emit PhanDeprecatedReservedName for the alias of a use statement (`use Foo as let;`, `use function f as is;`, `use Foo\let;`).
     */
    private function warnAboutReservedUseAlias(string $alias, int $flags, int $lineno): void
    {
        if ($flags === ast\flags\USE_FUNCTION) {
            $this->warnAboutNameReservedInFutureVersion($alias, ['let', 'is'], 'a function alias', $lineno);
        } elseif ($flags !== ast\flags\USE_CONST) {
            $this->warnAboutNameReservedInFutureVersion($alias, ['let', 'is', '_'], 'a class alias', $lineno);
        }
    }

    private function maybeWarnSameNamespaceUse(string $alias, FullyQualifiedGlobalStructuralElement $target, int $flags, int $lineno): void
    {
        if (\strcasecmp($alias, $target->getName()) !== 0) {
            return;
        }
        if ($flags === ast\flags\USE_FUNCTION) {
            if ($target->getNamespace() !== '\\') {
                return;
            }
            $issue_type = Issue::UseFunctionNoEffect;
        } elseif ($flags === ast\flags\USE_CONST) {
            if ($target->getNamespace() !== '\\') {
                return;
            }
            $issue_type = Issue::UseConstantNoEffect;
        } else {
            if ($target->getNamespace() !== '\\') {
                if (!Config::getValue('warn_about_relative_include_statement')) {
                    return;
                }
                $issue_type = Issue::UseNormalNamespacedNoEffect;
            } else {
                $issue_type = Issue::UseNormalNoEffect;
            }
        }
        $this->emitIssue(
            $issue_type,
            $lineno,
            $target
        );
    }

    /**
     * @param Node $node
     * The node with the use statement
     *
     * @param int $flags
     * An optional node flag specifying the type
     * of the use clause.
     *
     * @return array<string,array{0:int,1:FullyQualifiedGlobalStructuralElement,2:int}>
     * A map from alias to target
     *
     * @suppress PhanThrowTypeAbsentForCall
     */
    public static function aliasTargetMapFromUseNode(
        Node $node,
        string $prefix = '',
        int $flags = 0
    ): array {
        if ($node->kind !== \ast\AST_USE) {
            throw new AssertionError('Method takes AST_USE nodes');
        }

        $map = [];
        foreach ($node->children as $child_node) {
            if (!$child_node instanceof Node) {
                throw new AssertionError('Expected array of AST_USE_ELEM nodes');
            }
            $target = $child_node->children['name'];

            if (isset($child_node->children['alias'])) {
                $alias = $child_node->children['alias'];
            } else {
                if (($pos = \strrpos($target, '\\')) !== false) {
                    $alias = \substr($target, $pos + 1);
                } else {
                    $alias = $target;
                }
            }
            if (!\is_string($alias)) {
                // Should be impossible
                continue;
            }

            // if AST_USE does not have any flags set, then its AST_USE_ELEM
            // children will (this will be for AST_GROUP_USE)

            // The 'use' type can be defined on the `AST_GROUP_USE` node, the
            // `AST_USE_ELEM` or on the child element.
            $use_flag = $flags ?: $node->flags ?: $child_node->flags;

            if ($use_flag === \ast\flags\USE_FUNCTION) {
                $parts = \explode('\\', $target);
                $function_name = \array_pop($parts);
                $target = FullyQualifiedFunctionName::make(
                    rtrim($prefix, '\\') . '\\' . implode('\\', $parts),
                    $function_name
                );
            } elseif ($use_flag === \ast\flags\USE_CONST) {
                $parts = \explode('\\', $target);
                $name = \array_pop($parts);
                $target = FullyQualifiedGlobalConstantName::make(
                    rtrim($prefix, '\\') . '\\' . implode('\\', $parts),
                    $name
                );
            } elseif ($use_flag === \ast\flags\USE_NORMAL) {
                $target = FullyQualifiedClassName::fromFullyQualifiedString(
                    rtrim($prefix, '\\') . '\\' . $target
                );
            } else {
                // If we get to this spot and don't know what
                // kind of a use clause we're dealing with, its
                // likely that this is a `USE` node which is
                // a child of a `GROUP_USE` and we already
                // handled it when analyzing the parent
                // node.
                continue;
            }

            $map[$alias] = [$use_flag, $target, $child_node->lineno];
        }

        return $map;
    }
}
