<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Element\FunctionInterface;
use Phan\Language\FQSEN;
use Phan\Language\Type;

use AssertionError;

/**
 * Phan's representation of `Closure` and of closures associated with a given function-like's FQSEN
 * @see ClosureDeclarationType for representations created from PHPDoc `Closure(MyClass):MyOtherClass`.
 */
final class ClosureType extends Type
{
    /** Not an override */
    const NAME = 'Closure';

    /**
     * @var FQSEN|null
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

    public function __clone()
    {
        if ($this->fqsen !== null) {
            throw new AssertionError('should only clone null fqsen');
        }
        $this->singleton_union_type = null;
        // same as new static($this->namespace, $this->name, $this->template_parameter_type_list, $this->is_nullable);
    }

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

    /**
     * @param bool $is_nullable
     * If true, returns a nullable instance of this closure type
     *
     * @return static
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

        if (empty($instance)) {
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
            return $this->func->asFunctionLikeDeclarationType()->__toString();
        }
        return '\Closure';
    }
}
