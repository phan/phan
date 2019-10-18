<?php declare(strict_types=1);

namespace Phan\Language\Type;

use AssertionError;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\FQSEN;
use Phan\Language\Type;

/**
 * Phan's representation of `Closure` and of closures associated with a given function-like's FQSEN
 * @see ClosureDeclarationType for representations created from PHPDoc `Closure(MyClass):MyOtherClass`.
 * @phan-pure
 */
final class ClosureType extends Type
{
    /** Not an override */
    const NAME = 'Closure';

    /**
     * @var FQSEN|null the FQSEN of the function-like from which this ClosureType was derived
     */
    private $fqsen;

    /**
     * @var ?FunctionInterface (Kept to check for type compatibility)
     * NOTE: We may use class FQSENs (for __invoke) or Method FQSENs if the closure was created via reflection
     */
    private $func;

    // Same as instance(), but guaranteed not to have memoized state.
    private static function closureInstance() : ClosureType
    {
        static $instance = null;
        if ($instance === null) {
            $instance = self::make('\\', self::NAME, [], false, self::FROM_NODE);
        }
        return $instance;
    }

    /**
     * Create an instance of Closure for the FQSEN of the passed in function/closure/method $func with FQSEN $fqsen
     * @suppress PhanAccessReadOnlyProperty this is acting on a clone
     */
    public static function instanceWithClosureFQSEN(FQSEN $fqsen, FunctionInterface $func = null) : ClosureType
    {
        static $original_instance = null;
        if ($original_instance === null) {
            $original_instance = self::closureInstance();
        }
        // Use an instance with no memoized or lazily initialized results.
        // Avoids picking up changes to ClosureType::instance(false) in the case that a result depends on asFQSEN()
        $instance = clone($original_instance);
        $instance->fqsen = $fqsen;
        $instance->func = $func;
        $instance->memoizeFlushAll();
        return $instance;
    }

    /**
     * @suppress PhanAccessReadOnlyProperty
     */
    public function __clone()
    {
        if ($this->fqsen !== null) {
            throw new AssertionError('should only clone null fqsen');
        }
        $this->singleton_union_type = null;
        $this->singleton_real_union_type = null;
        // same as new static($this->namespace, $this->name, $this->template_parameter_type_list, $this->is_nullable);
    }

    /**
     * Is this a closure which points to a known FQSEN
     * (in internal or parsed function-likes, classes, methods, closures, etc.
     */
    public function hasKnownFQSEN() : bool
    {
        return $this->fqsen !== null;
    }

    /**
     * Override asFQSEN to return the closure's FQSEN
     */
    public function asFQSEN() : FQSEN
    {
        return $this->fqsen ?? parent::asFQSEN();
    }

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type) : bool
    {
        if ($type->isCallable()) {
            if ($type instanceof FunctionLikeDeclarationType) {
                // Check if the function declaration is known and available. It's not available for the generic \Closure.
                if ($this->func) {
                    return $this->func->asFunctionLikeDeclarationType()->canCastToNonNullableFunctionLikeDeclarationType($type);
                }
            }
            return true;
        }

        return parent::canCastToNonNullableType($type);
    }

    protected function canCastToNonNullableTypeWithoutConfig(Type $type) : bool
    {
        if ($type->isCallable()) {
            if ($type instanceof FunctionLikeDeclarationType) {
                // Check if the function declaration is known and available. It's not available for the generic \Closure.
                if ($this->func) {
                    return $this->func->asFunctionLikeDeclarationType()->canCastToNonNullableFunctionLikeDeclarationType($type);
                }
            }
            return true;
        }

        return parent::canCastToNonNullableTypeWithoutConfig($type);
    }

    /**
     * @param bool $is_nullable
     * If true, returns a nullable instance of this closure type
     *
     * @return static an instance of this closure type with appropriate nullability
     */
    public static function instance(bool $is_nullable)
    {
        if ($is_nullable) {
            static $nullable_instance = null;

            if (!$nullable_instance) {
                $nullable_instance = self::make('\\', self::NAME, [], true, Type::FROM_NODE);
            }
            if (!($nullable_instance instanceof self)) {
                throw new AssertionError("Expected ClosureType::make to return ClosureType");
            }

            return $nullable_instance;
        }

        static $instance = null;

        if ($instance === null) {
            $instance = self::make('\\', self::NAME, [], false, Type::FROM_NODE);
        }

        if (!($instance instanceof self)) {
            throw new AssertionError("Expected ClosureType::make to return ClosureType");
        }
        return $instance;
    }

    /**
     * @return bool
     * True if this type is a callable or a Closure.
     */
    public function isCallable() : bool
    {
        return true;
    }

    public function __toString()
    {
        if ($this->func) {
            $result = $this->func->asFunctionLikeDeclarationType()->__toString();
        } else {
            $result = '\Closure';
        }

        return $this->is_nullable ? "?$result" : $result;
    }

    /**
     * Returns true if this contains a type that is definitely non-callable
     * e.g. returns true for false, array, int
     *      returns false for callable, array, object, iterable, T, etc.
     */
    public function isDefiniteNonCallableType() : bool
    {
        return false;
    }

    /**
     * Gets the function-like this type was created from.
     *
     * TODO: Uses of this may keep outdated data in language server mode.
     * @deprecated use asFunctionInterfaceOrNull
     * @suppress PhanUnreferencedPublicMethod
     */
    public function getFunctionLikeOrNull() : ?FunctionInterface
    {
        return $this->func;
    }

    public function asFunctionInterfaceOrNull(CodeBase $unused_codebase, Context $unused_context) : ?FunctionInterface
    {
        return $this->func;
    }
}
