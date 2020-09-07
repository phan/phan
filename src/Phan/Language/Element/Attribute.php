<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use AssertionError;
use ast;
use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Debug;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;

/**
 * Represents the information Phan has about a declaration's attribute
 * (e.g. of a class, function, class constant, property, parameter, etc.)
 */
final class Attribute
{
    /** @var FullyQualifiedClassName  */
    private $fqsen;
    /** @var ?Node a node of kind ast\AST_ARG_LIST */
    private $args;
    /** @var int the start lineno where the attribute was declared */
    private $lineno;

    /**
     * @param FullyQualifiedClassName $fqsen the class name of the attribute being created
     * @param ?Node $args a node of kind ast\AST_ARG_LIST
     * @param int $lineno the start line where the attribute was declared
     */
    public function __construct(FullyQualifiedClassName $fqsen, ?Node $args, int $lineno)
    {
        $this->fqsen = $fqsen;
        $this->args = $args;
        $this->lineno = $lineno;
    }

    /**
     * Create an attribute from an `ast\AST_ATTRIBUTE` node
     * @suppress PhanThrowTypeAbsentForCall
     */
    public static function fromNodeForAttribute(
        CodeBase $code_base,
        Context $context,
        Node $node
    ): Attribute {
        if ($node->kind !== ast\AST_ATTRIBUTE) {
            throw new AssertionError("Expected AST_ATTRIBUTE but got " . Debug::nodeName($node));
        }
        $class_name = (string)UnionTypeVisitor::unionTypeFromClassNode($code_base, $context, $node->children['class']);

        // The name is fully qualified.
        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($class_name);
        return new self($class_fqsen, $node->children['args'], $node->lineno);
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
            // @phan-suppress-next-line PhanPossiblyUndeclaredProperty
            foreach ($group->children as $attribute_node) {
                // @phan-suppress-next-line PhanPartialTypeMismatchArgument
                $attributes[] = self::fromNodeForAttribute($code_base, $context, $attribute_node);
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
     * Returns the optional argument list of this attribute.
     * @suppress PhanUnreferencedPublicMethod TODO: this will be used by Phan 4.0.0
     */
    public function getArgs(): ?Node
    {
        return $this->args;
    }

    /**
     * Returns the starting line number
     */
    public function getLineno(): int
    {
        return $this->lineno;
    }
}
