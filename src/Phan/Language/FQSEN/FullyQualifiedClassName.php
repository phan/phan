<?php

declare(strict_types=1);

namespace Phan\Language\FQSEN;

use Phan\Exception\FQSENException;
use Phan\Language\Context;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Memoize;

use function preg_match;

/**
 * A Fully-Qualified Class Name
 */
class FullyQualifiedClassName extends FullyQualifiedGlobalStructuralElement
{
    use Memoize;

    /**
     * @return int
     * The namespace map type such as \ast\flags\USE_NORMAL or \ast\flags\USE_FUNCTION
     */
    protected static function getNamespaceMapType(): int
    {
        return \ast\flags\USE_NORMAL;
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
     * Returns the class that PHP resolves a name in the code to, for a name that is not fully qualified:
     * `Foo` or `Sub\Foo` (a name node with the flag ast\flags\NAME_NOT_FQ), not `\Foo` or `namespace\Foo`.
     *
     * The first segment is replaced if it is the alias of a `use` statement. Otherwise the name is relative to the
     * namespace of $context: `Sub\Foo` is `\NS\Sub\Foo` in the namespace `NS`.
     *
     * This differs from fromStringInContext(), which takes a name with a namespace as fully qualified (`\Sub\Foo`).
     * That is right for a string that names a class, such as the callable `'Sub\Foo::method'`, and not for a name in the code.
     *
     * @param string $name the name as written in the code, without `self`, `static` and `parent`
     * @throws FQSENException if the name is not a valid class name
     */
    public static function fromNameInContext(string $name, Context $context): FullyQualifiedClassName
    {
        if ($context->hasNamespaceMapFor(\ast\flags\USE_NORMAL, $name)) {
            $fqsen = $context->getNamespaceMapFor(\ast\flags\USE_NORMAL, $name);
            if ($fqsen instanceof FullyQualifiedClassName) {
                return $fqsen;
            }
        }
        return self::fromFullyQualifiedString(\rtrim($context->getNamespace(), '\\') . '\\' . $name);
    }

    /**
     * @return FullyQualifiedClassName
     * A fully qualified class name from the given type
     */
    public static function fromType(Type $type): FullyQualifiedClassName
    {
        // @phan-suppress-next-line PhanThrowTypeAbsentForCall
        return Type::fullyQualifiedClassNameFromType($type);
    }

    public const VALID_CLASS_REGEX = '/^\\\\?[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*$/D';

    /**
     * Asserts that something is a valid class FQSEN in PHPDoc.
     * Use this for FQSENs passed in from the analyzed code.
     * @suppress PhanUnreferencedPublicMethod
     */
    public static function isValidClassFQSEN(string $type): bool
    {
        return preg_match(self::VALID_CLASS_REGEX, $type) > 0;
    }

    /**
     * @return Type
     * The type of this class
     */
    public function asType(): Type
    {
        // @phan-suppress-next-line PhanThrowTypeAbsentForCall the creation of FullyQualifiedClassName checks for FQSENException.
        return Type::fromFullyQualifiedString(
            $this->__toString()
        );
    }

    /**
     * @return UnionType
     * The union type of just this class type, as a phpdoc union type
     * @suppress PhanUnreferencedPublicMethod
     */
    public function asPHPDocUnionType(): UnionType
    {
        return $this->asType()->asPHPDocUnionType();
    }

    /**
     * @return UnionType
     * The union type of just this class type, as a real union type
     */
    public function asRealUnionType(): UnionType
    {
        return $this->asType()->asRealUnionType();
    }

    /**
     * @return FullyQualifiedClassName
     * The FQSEN for \stdClass.
     */
    public static function getStdClassFQSEN(): FullyQualifiedClassName
    {
        return self::memoizeStatic(__METHOD__, static function (): FullyQualifiedClassName {
            // @phan-suppress-next-line PhanThrowTypeAbsentForCall
            return self::fromFullyQualifiedString("\stdClass");
        });
    }

    /**
     * The names that php does not allow for a class, interface, trait, enum or the alias of one:
     * `class int {}` and `use Foo as self;` are compile errors.
     * (`array` and `callable` are tokens, and only possible as aliases)
     */
    private const RESERVED_NAMES = [
        'bool' => true,
        'false' => true,
        'float' => true,
        'int' => true,
        'null' => true,
        'parent' => true,
        'self' => true,
        'static' => true,
        'string' => true,
        'true' => true,
        'void' => true,
        'never' => true,
        'iterable' => true,
        'object' => true,
        'mixed' => true,
        'array' => true,
        'callable' => true,
    ];

    /**
     * @param string $name the name of a class, with or without a namespace
     * @return bool whether php reserves the unqualified name, so that it cannot be the name of a classlike or the alias of one
     */
    public static function isReservedName(string $name): bool
    {
        $i = \strrpos($name, '\\');
        if ($i !== false) {
            $name = \substr($name, $i + 1);
        }
        return isset(self::RESERVED_NAMES[\strtolower($name)]);
    }
}
