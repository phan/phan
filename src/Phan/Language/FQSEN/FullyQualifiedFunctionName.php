<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use Phan\Language\Context;
use ast\Node;

/**
 * A Fully-Qualified Function Name
 */
class FullyQualifiedFunctionName extends FullyQualifiedGlobalStructuralElement
    implements FullyQualifiedFunctionLikeName
{

    /**
     * @return int
     * The namespace map type such as \ast\flags\USE_NORMAL or \ast\flags\USE_FUNCTION
     */
    protected static function getNamespaceMapType() : int
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
    public static function canonicalName(string $name) : string
    {
        return $name;
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
        if (0 === \strpos($fqsen_string, '\\')) {
            return static::fromFullyQualifiedString($fqsen_string);
        }

        // Split off the alternate ID
        $parts = \explode(',', $fqsen_string);
        $fqsen_string = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        $parts = \explode('\\', $fqsen_string);
        $name = \array_pop($parts);

        \assert(
            !empty($name),
            "The name cannot be empty"
        );

        // Check for a name map
        if ($context->hasNamespaceMapFor(static::getNamespaceMapType(), $fqsen_string)) {
            return $context->getNamespaceMapFor(
                static::getNamespaceMapType(),
                $fqsen_string
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
        Context $context,
        Node $node
    ) : FullyQualifiedFunctionName {
        $hash_material = implode('|', [
            $context->getFile(),
            $context->getLineNumberStart(),
            $node->children['docComment'] ?? '',
            implode(',', array_map(function(Node $arg) : string {
                return serialize([$arg->children['type'], $arg->children['name'], $arg->children['default']]);
            }, $node->children['params']->children)),
            implode(',', array_map(function(Node $use) : string {
                return $use->children['name'];
            }, $node->children['uses']->children ?? [])),
            serialize($node->children['returnType']),
        ]);
        // TODO: hash args
        $name = 'closure_' . substr(md5($hash_material), 0, 12);

        return static::fromStringInContext(
            $name,
            $context
        );
    }

    /**
     * @return bool
     * True if this FQSEN represents a closure
     */
    public function isClosure() : bool {
        return (\preg_match('/^closure_/', $this->getName()) === 1);
    }

}
