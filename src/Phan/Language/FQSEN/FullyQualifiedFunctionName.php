<?php

declare(strict_types=1);

namespace Phan\Language\FQSEN;

use ast\Node;
use Phan\Exception\EmptyFQSENException;
use Phan\Exception\FQSENException;
use Phan\Language\Context;

/**
 * A Fully-Qualified Function Name
 */
class FullyQualifiedFunctionName extends FullyQualifiedGlobalStructuralElement implements FullyQualifiedFunctionLikeName
{

    /**
     * @return int
     * The namespace map type such as \ast\flags\USE_NORMAL or \ast\flags\USE_FUNCTION
     */
    protected static function getNamespaceMapType(): int
    {
        return \ast\flags\USE_FUNCTION;
    }

    /**
     * @return string
     * The canonical representation of the name of the object. Functions
     * and Methods, for instance, lowercase their names.
     * TODO: Separate the function used to render names in phan errors
     *       from the ones used for generating array keys.
     */
    public static function canonicalName(string $name): string
    {
        return $name;
    }

    /**
     * @param string $fqsen_string
     * An FQSEN string like '\Namespace\myfunction'
     *
     * @param Context $context
     * The context in which the FQSEN string was found
     *
     * @throws FQSENException
     * if $fqsen_string has an empty/invalid name component.
     */
    public static function fromStringInContext(
        string $fqsen_string,
        Context $context
    ): FullyQualifiedFunctionName {

        // Check to see if we're fully qualified
        if (0 === \strpos($fqsen_string, '\\')) {
            return static::fromFullyQualifiedString($fqsen_string);
        }

        // Split off the alternate ID
        $parts = \explode(',', $fqsen_string);
        $fqsen_string = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        $parts = \explode('\\', $fqsen_string); // explode returns a non-empty array, array_pop must return a string.
        $name = \array_pop($parts);

        if ($name === '') {
            throw new EmptyFQSENException("The name cannot be empty", $fqsen_string);
        }

        // Check for a name map
        if ($context->hasNamespaceMapFor(static::getNamespaceMapType(), $fqsen_string)) {
            // @phan-suppress-next-line PhanTypeMismatchReturnSuperType
            return $context->getNamespaceMapFor(
                static::getNamespaceMapType(),
                $fqsen_string
            );
        }

        // For functions we don't use the context's namespace if
        // there is no NS on the call.
        $namespace = \implode('\\', \array_filter($parts));

        return static::make(
            $namespace,
            $name,
            $alternate_id
        );
    }

    /**
     * Generates a deterministic FQSEN for the closure of the passed in node.
     * @param Node $node a Node type AST_CLOSURE, within the file $context->getFile()
     */
    public static function fromClosureInContext(
        Context $context,
        Node $node
    ): FullyQualifiedFunctionName {
        $hash_material =
            $context->getFile() . '|' .
            $node->lineno . '|' .
            $node->children['__declId'];

        $name = 'closure_' . \substr(\md5($hash_material), 0, 12);

        // @phan-suppress-next-line PhanThrowTypeAbsentForCall this is valid
        return static::fromStringInContext(
            $name,
            $context
        );
    }

    /**
     * @return bool
     * True if this FQSEN represents a closure
     */
    public function isClosure(): bool
    {
        return \strncmp('closure_', $this->name, 8) === 0;
    }
}
