<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use AssertionError;
use ast;
use ast\Node;
use Phan\AST\ASTReverter;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Debug;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Stringable;

use const PHP_MAJOR_VERSION;

/**
 * Represents the information Phan has about a declaration's attribute
 * (e.g. of a class, function, class constant, property, parameter, etc.)
 *
 * NOTE: This namespaced class depends on a different class of the same name in the global namespace,
 * but only in php 8.0+.
 *
 * @phan-file-suppress PhanUndeclaredClassConstant Attribute exists only in php 8.0+ and is not polyfilled at the time of writing.
 * @phan-file-suppress PhanUnreferencedPublicClassConstant provided for API completeness
 * @suppress PhanRedefinedInheritedInterface this uses a polyfill for Stringable
 */
final class Attribute implements Stringable
{
    /**
     * Don't bother depending on a polyfill. It's possible symfony/polyfill-80 may add Attribute and make this redundant, though.
     * https://github.com/symfony/polyfill/issues/235
     *
     * There's no guarantee the constants won't change in php 8.x or 9.x, so use the real values in php 8.0+.
     */
    const TARGET_CLASS          = PHP_MAJOR_VERSION < 8 ? 1  : \Attribute::TARGET_CLASS;
    const TARGET_FUNCTION       = PHP_MAJOR_VERSION < 8 ? 2  : \Attribute::TARGET_FUNCTION;
    const TARGET_METHOD         = PHP_MAJOR_VERSION < 8 ? 4  : \Attribute::TARGET_METHOD;
    const TARGET_PROPERTY       = PHP_MAJOR_VERSION < 8 ? 8  : \Attribute::TARGET_PROPERTY;
    const TARGET_CLASS_CONSTANT = PHP_MAJOR_VERSION < 8 ? 16 : \Attribute::TARGET_CLASS_CONSTANT;
    const TARGET_PARAMETER      = PHP_MAJOR_VERSION < 8 ? 32 : \Attribute::TARGET_PARAMETER;
    const TARGET_ALL            = PHP_MAJOR_VERSION < 8 ? 63 : \Attribute::TARGET_ALL;
    const IS_REPEATABLE         = PHP_MAJOR_VERSION < 8 ? 64 : \Attribute::IS_REPEATABLE;

    /** @var FullyQualifiedClassName  */
    private $fqsen;
    /** @var ?Node a node of kind ast\AST_ATTRIBUTE */
    private $node;
    /** @var int the start lineno where the attribute was declared */
    private $lineno;
    /** @var ?Node $group */
    private $group;

    /**
     * @param FullyQualifiedClassName $fqsen the class name of the attribute being created
     * @param ?Node $node a node of kind ast\AST_ATTRIBUTE (unless on an internal class)
     * @param int $lineno the start line where the attribute was declared
     * @param ?Node $group the group containing the attribute
     */
    public function __construct(FullyQualifiedClassName $fqsen, ?Node $node, int $lineno, ?Node $group)
    {
        $this->fqsen = $fqsen;
        $this->node = $node;
        $this->lineno = $lineno;
        $this->group = $group;
    }

    /**
     * Create an attribute from an `ast\AST_ATTRIBUTE` node
     * @suppress PhanThrowTypeAbsentForCall
     */
    public static function fromNodeForAttribute(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $group
    ): Attribute {
        if ($node->kind !== ast\AST_ATTRIBUTE) {
            throw new AssertionError("Expected AST_ATTRIBUTE but got " . Debug::nodeName($node));
        }
        $class_name = (string)UnionTypeVisitor::unionTypeFromClassNode($code_base, $context, $node->children['class']);

        // The name is fully qualified.
        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($class_name);
        return new self($class_fqsen, $node, $node->lineno, $group);
    }

    /**
     * Given a node of kind ast\AST_ATTRIBUTE_LIST, return a representation of all the attributes in the attribute list.
     * @return list<Attribute>
     */
    public static function fromNodeForAttributeList(
        CodeBase $code_base,
        Context $context,
        ?Node $node
    ): array {
        if (!$node) {
            return [];
        }
        if ($node->kind !== ast\AST_ATTRIBUTE_LIST) {
            throw new AssertionError("Expected ast\AST_ATTRIBUTE_LIST but got " . Debug::nodeName($node));
        }
        $attributes = [];
        foreach ($node->children as $group) {
            if (!$group instanceof Node) {
                throw new AssertionError("Expected ast\AST_ATTRIBUTE_GROUP but got non-node");
            }
            foreach ($group->children as $attribute_node) {
                // @phan-suppress-next-line PhanPartialTypeMismatchArgument
                $attributes[] = self::fromNodeForAttribute($code_base, $context, $attribute_node, $group);
            }
        }
        return $attributes;
    }

    /**
     * Returns the FQSEN of this attribute.
     */
    public function getFQSEN(): FullyQualifiedClassName
    {
        return $this->fqsen;
    }

    /**
     * Returns the optional argument list of this attribute (a node of kind ast\AST_ARG_LIST).
     */
    public function getArgs(): ?Node
    {
        return $this->node->children['args'] ?? null;
    }

    /**
     * Returns the optional #[] group containing this attribute (a node of kind ast\AST_ATTRIBUTE_GROUP).
     */
    public function getGroup(): ?Node
    {
        return $this->group;
    }

    /**
     * Returns the optional node defining this attribute (a node of kind ast\AST_ATTRIBUTE).
     * This is null for attributes of internal classes.
     */
    public function getNode(): ?Node
    {
        return $this->node;
    }

    /**
     * Returns the starting line number
     */
    public function getLineNumberStart(): int
    {
        return $this->lineno;
    }

    /**
     * Returns the starting line number of the group containing this attribute
     */
    public function getGroupLineNumberStart(): int
    {
        return $this->group->lineno ?? $this->lineno;
    }

    /**
     * Returns the best guess of the ending line number of the group containing this attribute
     */
    public function getGroupLineNumberEnd(): int
    {
        return $this->group ? self::guessEndLineno($this->group) : $this->lineno;
    }

    /**
     * Guess the ending line number of a node
     */
    private static function guessEndLineno(Node $node): int
    {
        $line = $node->lineno;
        foreach ($node->children as $child_node) {
            if ($child_node instanceof Node) {
                $new_line = self::guessEndLineno($child_node);
                if ($new_line > $line) {
                    $line = $new_line;
                }
            }
        }
        return $line;
    }

    public function __toString(): string
    {
        $result = $this->fqsen->__toString();
        $args = $this->node->children['args'] ?? null;
        if ($args) {
            $result .= ASTReverter::toShortTypeString($args);
        }
        return $result;
    }
}
