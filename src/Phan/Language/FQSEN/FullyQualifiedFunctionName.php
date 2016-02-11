<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use \Phan\Language\Context;

/**
 * A Fully-Qualified Function Name
 */
class FullyQualifiedFunctionName extends FullyQualifiedGlobalStructuralElement
{

    /**
     * @return int
     * The namespace map type such as T_CLASS or T_FUNCTION
     */
    protected static function getNamespaceMapType() : int
    {
        return T_FUNCTION;
    }

    /**
     * @return string
     * The canonical representation of the name of the object. Functions
     * and Methods, for instance, lowercase their names.
     */
    public static function canonicalName(string $name) : string
    {
        return strtolower($name);
    }

    /**
     * @param Context $context
     * The context in which the FQSEN string was found
     *
     * @param $fqsen_string
     * An FQSEN string like '\Namespace\Class'
     */
    public static function fromStringInContext(
        string $fqsen_string,
        Context $context
    ) : FullyQualifiedFunctionName {

        // Check to see if we're fully qualified
        if (0 === strpos($fqsen_string, '\\')) {
            return static::fromFullyQualifiedString($fqsen_string);
        }

        // Split off the alternate ID
        $parts = explode(',', $fqsen_string);
        $fqsen_string = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        assert(
            is_int($alternate_id),
            "Alternate must be an integer in $fqsen_string"
        );

        $parts = explode('\\', $fqsen_string);
        $name = array_pop($parts);

        assert(
            !empty($name),
            "The name cannot be empty in $fqsen_string"
        );

        // Check for a name map
        if ($context->hasNamespaceMapFor(static::getNamespaceMapType(), $name)) {
            return $context->getNamespaceMapFor(
                static::getNamespaceMapType(),
                $name
            );
        }

        // For functions we don't use the context's namespace if
        // there is no NS on the call.
        $namespace = implode('\\', array_filter($parts));

        return static::make(
            $namespace,
            $name,
            $alternate_id
        );
    }

    public static function fromClosureInContext(
        Context $context
    ) : FullyQualifiedFunctionName {
        $name = 'closure_' . substr(md5(implode('|', [
            $context->getFile(),
            $context->getLineNumberStart()
        ])), 0, 8);

        return static::fromStringInContext(
            $name,
            $context
        );
    }
}
